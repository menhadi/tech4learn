import {
  BadRequestException,
  ForbiddenException,
  Injectable,
  NotFoundException,
  UnauthorizedException,
} from "@nestjs/common";
import { randomUUID } from "node:crypto";
import { Database, type SqlClient } from "./database.js";
import { AccessService } from "./access.service.js";
import { ExamContentService } from "./exam-content.service.js";
import { IdentityService, type Account } from "./identity.service.js";
import { digest, token, uuid } from "./security.js";

export type ExamStudentContext = {
  grant_id: string;
  organisation_id: string;
  learner_id: string;
  external_exam_id: string;
  exam_name: string;
  student_name: string;
  organisation_name: string;
  expires_at: Date;
};
@Injectable()
export class ExamStudentAccessService {
  constructor(
    private readonly db: Database,
    private readonly access: AccessService,
    private readonly content: ExamContentService,
    private readonly identity: IdentityService,
  ) {}
  private async enabled(sql: SqlClient, org: string) {
    const row = (
      await sql.query<any>(
        `SELECT s.enabled_modules,w.restrictions FROM organisation_settings s LEFT JOIN examelite_workspaces w ON w.organisation_id=s.organisation_id WHERE s.organisation_id=$1`,
        [org],
      )
    ).rows[0];
    if (
      row?.enabled_modules?.exams !== true ||
      row?.restrictions?.includes("taking")
    )
      throw new ForbiddenException(
        "Student exams are unavailable for this organisation.",
      );
  }
  async issue(user: Account, org: string, body: Record<string, unknown>) {
    uuid(org);
    const learner = uuid(String(body.learner_id ?? ""));
    if (
      typeof body.exam_id !== "string" ||
      !/^[1-9][0-9]{0,14}$/.test(body.exam_id) ||
      !Number.isSafeInteger(body.hours) ||
      Number(body.hours) < 1 ||
      Number(body.hours) > 168
    )
      throw new BadRequestException(
        "Choose an exam and an access duration of 1–168 hours.",
      );
    await this.access.require(user, org, "exams.manage");
    await this.access.require(user, org, "learners.view");
    if (
      !(
        await this.db.query(
          "SELECT id FROM learners WHERE organisation_id=$1 AND id=$2 AND NOT archived",
          [org, learner],
        )
      ).rows.length
    )
      throw new NotFoundException("Student not found.");
    const exam = await this.content.taxonomy(user, org, "exams", body.exam_id);
    if (
      String(exam.id) !== body.exam_id ||
      typeof exam.fields?.name !== "string"
    )
      throw new BadRequestException(
        "The exam is unavailable in this organisation.",
      );
    const secret = token(),
      id = randomUUID();
    const expires = await this.db.transaction(async (sql) => {
      await this.access.lock(sql, org);
      await this.access.require(user, org, "exams.manage", sql);
      await this.access.require(user, org, "learners.view", sql);
      await this.enabled(sql, org);
      if (
        !(
          await sql.query(
            "SELECT id FROM learners WHERE organisation_id=$1 AND id=$2 AND NOT archived FOR UPDATE",
            [org, learner],
          )
        ).rows.length
      )
        throw new NotFoundException("Student not found.");
      // Reissuing access replaces the previous link and all sessions for this paper.
      await sql.query(
        "UPDATE exam_student_grants SET revoked_at=now() WHERE organisation_id=$1 AND learner_id=$2 AND external_exam_id=$3 AND revoked_at IS NULL",
        [org, learner, body.exam_id],
      );
      const row = (
        await sql.query<{ expires_at: Date }>(
          `INSERT INTO exam_student_grants(id,organisation_id,learner_id,external_exam_id,exam_name,token_hash,expires_at,created_by) VALUES($1,$2,$3,$4,$5,$6,now()+$7*interval '1 hour',$8) RETURNING expires_at`,
          [
            id,
            org,
            learner,
            body.exam_id,
            exam.fields.name,
            digest(secret),
            body.hours,
            user.id,
          ],
        )
      ).rows[0];
      await this.access.audit(sql, user, org, "exams.student_access.issued", {
        grantId: id,
        learnerId: learner,
        examId: body.exam_id,
      });
      return row.expires_at;
    });
    return {
      id,
      expires_at: expires,
      fragment: `student-exam=${org}.${secret}`,
    };
  }
  async list(user: Account, org: string, learner: string) {
    uuid(org);
    uuid(learner);
    await this.access.require(user, org, "exams.manage");
    await this.access.require(user, org, "learners.view");
    return (
      await this.db.query(
        `SELECT id,external_exam_id,exam_name,expires_at,consumed_at,revoked_at,created_at FROM exam_student_grants WHERE organisation_id=$1 AND learner_id=$2 ORDER BY created_at DESC,id LIMIT 50`,
        [org, learner],
      )
    ).rows;
  }
  async revoke(user: Account, org: string, id: string) {
    uuid(org);
    uuid(id);
    await this.db.transaction(async (sql) => {
      await this.access.lock(sql, org);
      await this.access.require(user, org, "exams.manage", sql);
      if (
        !(
          await sql.query(
            "UPDATE exam_student_grants SET revoked_at=COALESCE(revoked_at,now()) WHERE organisation_id=$1 AND id=$2 RETURNING id",
            [org, id],
          )
        ).rows.length
      )
        throw new NotFoundException();
      await sql.query("DELETE FROM exam_student_sessions WHERE grant_id=$1", [
        id,
      ]);
      await this.access.audit(sql, user, org, "exams.student_access.revoked", {
        grantId: id,
      });
    });
    return { ok: true };
  }
  async exchange(org: string, secret: unknown, current?: string) {
    uuid(org);
    if (typeof secret !== "string" || !/^[a-f0-9]{64}$/.test(secret))
      throw new UnauthorizedException("This exam link is invalid or expired.");
    await this.identity.limit("student-exam-link-exchange", 6000, 900);
    return this.db.transaction(async (sql) => {
      const g = (
        await sql.query<any>(
          "SELECT g.* FROM exam_student_grants g JOIN learners l ON l.organisation_id=g.organisation_id AND l.id=g.learner_id WHERE g.organisation_id=$1 AND g.token_hash=$2 AND g.revoked_at IS NULL AND g.expires_at>now() AND NOT l.archived FOR UPDATE OF g",
          [org, digest(secret)],
        )
      ).rows[0];
      if (!g)
        throw new UnauthorizedException(
          "This exam link is invalid or expired.",
        );
      await this.enabled(sql, org);
      if (g.consumed_at) {
        // A same-browser retry can finish loading without reusing the one-time link.
        if (
          current &&
          /^[a-f0-9]{64}$/.test(current) &&
          (
            await sql.query(
              "SELECT 1 FROM exam_student_sessions WHERE token_hash=$1 AND grant_id=$2 AND expires_at>now()",
              [digest(current), g.id],
            )
          ).rows.length
        )
          return { secret: current };
        throw new UnauthorizedException(
          "This exam link has already been used. Request a new link from your organisation.",
        );
      }
      const session = token();
      await sql.query(
        "UPDATE exam_student_grants SET consumed_at=now() WHERE id=$1",
        [g.id],
      );
      await sql.query(
        "INSERT INTO exam_student_sessions(token_hash,grant_id,expires_at) VALUES($1,$2,LEAST($3::timestamptz,now()+interval '12 hours'))",
        [digest(session), g.id, g.expires_at],
      );
      return { secret: session };
    });
  }
  async context(org: string, secret?: string): Promise<ExamStudentContext> {
    uuid(org);
    if (!secret || !/^[a-f0-9]{64}$/.test(secret))
      throw new UnauthorizedException(
        "Open your organisation's exam link to sign in.",
      );
    const row = (
      await this.db.query<ExamStudentContext>(
        `SELECT g.id AS grant_id,g.organisation_id,g.learner_id,g.external_exam_id,g.exam_name,l.name AS student_name,o.name AS organisation_name,LEAST(s.expires_at,g.expires_at) AS expires_at FROM exam_student_sessions s JOIN exam_student_grants g ON g.id=s.grant_id JOIN learners l ON l.organisation_id=g.organisation_id AND l.id=g.learner_id JOIN organisations o ON o.id=g.organisation_id WHERE s.token_hash=$1 AND g.organisation_id=$2 AND s.expires_at>now() AND g.expires_at>now() AND g.revoked_at IS NULL AND NOT l.archived`,
        [digest(secret), org],
      )
    ).rows[0];
    if (!row)
      throw new UnauthorizedException(
        "Exam access has expired or was withdrawn.",
      );
    await this.enabled(this.db, org);
    return row;
  }
  async logout(secret?: string) {
    if (secret && /^[a-f0-9]{64}$/.test(secret))
      await this.db.query(
        "DELETE FROM exam_student_sessions WHERE token_hash=$1",
        [digest(secret)],
      );
    return { ok: true };
  }
}
