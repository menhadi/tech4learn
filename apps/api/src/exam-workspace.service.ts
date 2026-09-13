import {
  Injectable,
  ForbiddenException,
  BadRequestException,
  NotFoundException,
  ConflictException,
  ServiceUnavailableException,
} from "@nestjs/common";
import { randomUUID } from "node:crypto";
import { Database } from "./database.js";
import { AccessService } from "./access.service.js";
import { ExamEliteService } from "./examelite.service.js";
import type { Account } from "./identity.service.js";

export const workspaceFeatures = [
  "subjects",
  "questions",
  "exams",
  "taking",
  "results",
] as const;
const entries = {
  subjects: "subjects",
  questions: "questions",
  exams: "exams",
  results: "results",
} as const;
@Injectable()
export class ExamWorkspaceService {
  constructor(
    private readonly db: Database,
    private readonly access: AccessService,
    private readonly remote: ExamEliteService,
  ) {}
  private id(id: string) {
    if (
      !/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/.test(id)
    )
      throw new BadRequestException("Invalid record.");
  }
  private admin(user: Account) {
    if (!user.is_superadmin)
      throw new ForbiddenException(
        "Only superadmin can restrict exam features.",
      );
  }
  private async config() {
    const c = await this.remote.configuration("_platform");
    if (!c?.central)
      throw new ServiceUnavailableException(
        "Central ExamElite setup is required.",
      );
    return c;
  }
  private async organisation(org: string) {
    this.id(org);
    const row = (
      await this.db.query<any>(
        "SELECT id,name FROM organisations WHERE id=$1",
        [org],
      )
    ).rows[0];
    if (!row) throw new NotFoundException();
    return row;
  }
  private async rules(org: string) {
    return (
      (
        await this.db.query<any>(
          "SELECT restrictions,revision FROM examelite_workspaces WHERE organisation_id=$1",
          [org],
        )
      ).rows[0] ?? { restrictions: [], revision: 0 }
    );
  }
  async status(user: Account, org: string) {
    await this.access.require(user, org, "exams.manage");
    const rules = await this.rules(org);
    return { ...rules, features: workspaceFeatures, defaults: "all" };
  }
  async students(user: Account, org: string, search: string) {
    await this.access.require(user, org, "exams.manage");
    await this.access.require(user, org, "learners.view");
    if (typeof search !== "string" || search.length > 120)
      throw new BadRequestException("Search is too long.");
    return (
      await this.db.query(
        "SELECT id,name,code FROM learners WHERE organisation_id=$1 AND NOT archived AND (name ILIKE $2 OR code ILIKE $2) ORDER BY name,id LIMIT 50",
        [org, "%" + search.replace(/[\\%_]/g, "\\$&") + "%"],
      )
    ).rows;
  }
  async restrict(user: Account, org: string, b: Record<string, unknown>) {
    this.admin(user);
    await this.organisation(org);
    if (
      !Array.isArray(b.restrictions) ||
      b.restrictions.length > workspaceFeatures.length ||
      b.restrictions.some((x) => !workspaceFeatures.includes(x as any)) ||
      !Number.isSafeInteger(b.revision)
    )
      throw new BadRequestException("Choose valid feature restrictions.");
    const restrictions = [...new Set(b.restrictions as string[])].sort();
    const c = await this.config();
    await this.db.transaction(async (sql) => {
      await sql.query("SELECT id FROM organisations WHERE id=$1 FOR UPDATE", [
        org,
      ]);
      const prior = (
        await sql.query<any>(
          "SELECT revision FROM examelite_workspaces WHERE organisation_id=$1",
          [org],
        )
      ).rows[0];
      if ((prior?.revision ?? 0) !== b.revision)
        throw new ConflictException(
          "Restrictions changed. Reload before saving.",
        );
      // Provider applies restrictions to existing sessions. On a transport failure, do not claim a saved update.
      await this.remote.request(c, org, `workspace/${org}/restrictions`, {
        restrictions,
        revision: Number(b.revision) + 1,
      });
      await sql.query(
        `INSERT INTO examelite_workspaces(organisation_id,restrictions,revision) VALUES($1,$2,1)
        ON CONFLICT(organisation_id) DO UPDATE SET restrictions=$2,revision=examelite_workspaces.revision+1,updated_at=now()`,
        [org, restrictions],
      );
      await sql.query(
        "INSERT INTO audit_events(id,actor_id,organisation_id,action) VALUES($1,$2,$3,$4)",
        [randomUUID(), user.id, org, "examelite.workspace.restricted"],
      );
    });
    return this.status(user, org);
  }
  async launch(user: Account, org: string, b: Record<string, unknown>) {
    await this.access.require(user, org, "exams.manage");
    const organisation = await this.organisation(org),
      rules = await this.rules(org);
    const feature = String(b.feature);
    if (
      !workspaceFeatures.includes(feature as any) ||
      rules.restrictions.includes(feature)
    )
      throw new ForbiddenException("This exam feature is restricted.");
    let learner: Record<string, unknown> | undefined;
    if (feature === "taking") {
      this.id(String(b.learner));
      await this.access.require(user, org, "learners.view");
      learner = (
        await this.db.query(
          "SELECT id,name FROM learners WHERE organisation_id=$1 AND id=$2 AND NOT archived",
          [org, b.learner],
        )
      ).rows[0];
      if (!learner) throw new NotFoundException("Student not found.");
    }
    const c = await this.config();
    const response = await this.remote.request(
      c,
      org,
      `workspace/${org}/launch`,
      {
        organisation_name: organisation.name,
        actor_id: user.id,
        actor_name: user.name,
        feature,
        entry:
          feature === "taking"
            ? "student/dashboard"
            : entries[feature as keyof typeof entries],
        restrictions: rules.restrictions,
        revision: rules.revision,
        learner,
      },
    );
    const host = `t4l-${org.replaceAll("-", "")}.examelite.com`;
    if (
      response.host !== host ||
      typeof response.ticket !== "string" ||
      !/^[a-f0-9]{64}$/.test(response.ticket)
    )
      throw new ServiceUnavailableException("Invalid exam workspace response.");
    await this.access.audit(
      this.db,
      user,
      org,
      feature === "taking"
        ? "examelite.student.launch.issued"
        : "examelite.staff.launch.issued",
      { feature, learnerId: learner?.id },
    );
    return {
      url: `https://${host}/tech4learn/launch#${response.ticket}`,
      expiresIn: 120,
    };
  }
}
