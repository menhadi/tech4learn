import { readFile } from "node:fs/promises";
import {
  Injectable,
  BadRequestException,
  ServiceUnavailableException,
  ForbiddenException,
  NotFoundException,
  ConflictException,
} from "@nestjs/common";
import { AccessService } from "./access.service.js";
import { LearnersService } from "./learners.service.js";
import type { Account } from "./identity.service.js";
import { Database } from "./database.js";
import { randomUUID } from "node:crypto";

type Connection = { token: string; organization_id: number; central?: boolean };
const unavailable = () =>
  new ServiceUnavailableException(
    "ExamElite connection unavailable. Check the dedicated credential and organisation mapping.",
  );

export function connectionFor(config: unknown, org: string): Connection | null {
  if (!config || typeof config !== "object" || Array.isArray(config))
    throw unavailable();
  const c = Object.hasOwn(config, org)
    ? (config as Record<string, any>)[org]
    : null;
  if (!c || c.enabled === false) return null;
  if (
    c.enabled !== true ||
    !/^[a-f0-9]{64}$/.test(c.token || "") ||
    !Number.isSafeInteger(c.organization_id) ||
    c.organization_id <= 0
  )
    throw unavailable();
  return { token: c.token, organization_id: c.organization_id };
}

@Injectable()
export class ExamEliteService {
  constructor(
    private readonly access: AccessService,
    private readonly learners: LearnersService,
    private readonly db: Database,
  ) {}

  private admin(user: Account) {
    if (!user.is_superadmin)
      throw new ForbiddenException(
        "Only superadmin can manage ExamElite sharing.",
      );
  }
  private uuid(value: string) {
    if (
      !/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/.test(
        value,
      )
    )
      throw new BadRequestException("Invalid record identifier.");
  }
  private async central() {
    const c = await this.configuration("_platform");
    if (!c?.central)
      throw new ServiceUnavailableException(
        "The central ExamElite connection requires the server setup step.",
      );
    return c;
  }
  private async sharing(org: string) {
    this.uuid(org);
    const row = (
      await this.db.query<any>(
        "SELECT enabled,exam_ids,revision FROM examelite_sharing WHERE organisation_id=$1",
        [org],
      )
    ).rows[0];
    return row
      ? { ...row, exam_ids: row.exam_ids.map(Number) }
      : { enabled: false, exam_ids: [], revision: 0 };
  }
  async platformStatus(user: Account) {
    this.admin(user);
    const c = await this.configuration("_platform");
    const organisations = (
      await this.db
        .query(`SELECT o.id,o.name,COALESCE(s.enabled,false) AS enabled
      FROM organisations o LEFT JOIN examelite_sharing s ON s.organisation_id=o.id ORDER BY o.name,o.id`)
    ).rows;
    if (!c?.central)
      return {
        connected: false,
        organisations,
        message: "Run the central connection setup on the server once.",
      };
    await this.request(c, "_platform", "platform/status");
    return { connected: true, organisations };
  }
  async platformExams(user: Account, after: string, search: string) {
    this.admin(user);
    if (!/^\d{1,15}$/.test(after) || search.length > 150)
      throw new BadRequestException("Invalid exam search.");
    const data = await this.request(
      await this.central(),
      "_platform",
      `platform/exams?after=${after}&search=${encodeURIComponent(search)}`,
    );
    if (
      !Array.isArray(data.items) ||
      data.items.length > 50 ||
      (data.next !== null &&
        (!Number.isSafeInteger(data.next) || data.next <= Number(after)))
    )
      throw unavailable();
    return {
      items: data.items.map((r: any) => {
        if (
          !Number.isSafeInteger(r.id) ||
          r.id <= Number(after) ||
          typeof r.name !== "string" ||
          r.name.length > 1000
        )
          throw unavailable();
        return { id: r.id, name: r.name };
      }),
      next: data.next,
    };
  }
  async platformOrganisation(user: Account, org: string, search: string) {
    this.admin(user);
    this.uuid(org);
    if (search.length > 150)
      throw new BadRequestException("Search is too long.");
    if (
      !(await this.db.query("SELECT 1 FROM organisations WHERE id=$1", [org]))
        .rows.length
    )
      throw new NotFoundException();
    const students = (
      await this.db.query(
        `SELECT l.id,l.name,l.code,(s.learner_id IS NOT NULL) AS connected
      FROM learners l LEFT JOIN examelite_students s ON s.organisation_id=l.organisation_id AND s.learner_id=l.id
      WHERE l.organisation_id=$1 AND NOT l.archived AND (l.name ILIKE $2 OR l.code ILIKE $2)
      ORDER BY l.name,l.id LIMIT 50`,
        [org, `%${search}%`],
      )
    ).rows;
    return { ...(await this.sharing(org)), students };
  }
  async platformShare(user: Account, org: string, b: Record<string, unknown>) {
    this.admin(user);
    this.uuid(org);
    if (
      typeof b.enabled !== "boolean" ||
      !Number.isSafeInteger(b.revision) ||
      Number(b.revision) < 0 ||
      !Array.isArray(b.exam_ids) ||
      b.exam_ids.length > 1000 ||
      b.exam_ids.some((id) => !Number.isSafeInteger(id) || id <= 0)
    )
      throw new BadRequestException(
        "Choose exams and a valid sharing setting.",
      );
    const ids = [...new Set(b.exam_ids as number[])].sort((a, b) => a - b);
    // Ask the owning engine to validate every selected exam; no client-supplied catalogue records are trusted.
    if (b.enabled && ids.length) {
      const verified = await this.request(
        await this.central(),
        "_platform",
        "platform/validate-exams",
        { exam_ids: ids },
      );
      if (verified.valid !== true)
        throw new BadRequestException(
          "One or more selected exams are unavailable.",
        );
    }
    await this.db.transaction(async (sql) => {
      if (
        !(
          await sql.query(
            "SELECT id FROM organisations WHERE id=$1 FOR UPDATE",
            [org],
          )
        ).rows.length
      )
        throw new NotFoundException();
      const current = (
        await sql.query<any>(
          "SELECT revision FROM examelite_sharing WHERE organisation_id=$1",
          [org],
        )
      ).rows[0];
      if ((current?.revision ?? 0) !== b.revision)
        throw new ConflictException(
          "Sharing changed in another session. Reload before saving.",
        );
      await sql.query(
        `INSERT INTO examelite_sharing(organisation_id,enabled,exam_ids) VALUES($1,$2,$3)
        ON CONFLICT(organisation_id) DO UPDATE SET enabled=$2,exam_ids=$3,revision=examelite_sharing.revision+1,updated_at=now()`,
        [org, b.enabled, ids],
      );
      await sql.query(
        "INSERT INTO audit_events(id,actor_id,organisation_id,action) VALUES($1,$2,$3,$4)",
        [
          randomUUID(),
          user.id,
          org,
          b.enabled
            ? "examelite.sharing.updated"
            : "examelite.sharing.disabled",
        ],
      );
    });
    return this.platformOrganisation(user, org, "");
  }
  async platformConnect(user: Account, org: string, learner: string) {
    this.admin(user);
    this.uuid(org);
    this.uuid(learner);
    const sharing = await this.sharing(org);
    if (!sharing.enabled)
      throw new ForbiddenException(
        "Enable exam sharing for this organisation first.",
      );
    const row = (
      await this.db.query<any>(
        "SELECT name FROM learners WHERE organisation_id=$1 AND id=$2 AND NOT archived",
        [org, learner],
      )
    ).rows[0];
    if (!row)
      throw new NotFoundException(
        "Student was not found in this organisation.",
      );
    const c = await this.central();
    // The provider owns the stable (organisation,learner) key. Retrying after a lost response reuses it.
    const response = await this.request(
      c,
      org,
      `platform/organisations/${org}/learners/${learner}`,
      { name: row.name },
    );
    if (
      response.learner_id !== learner ||
      response.tech4learn_organisation_id !== org ||
      !Number.isSafeInteger(response.student_id) ||
      response.student_id <= 0
    )
      throw unavailable();
    await this.db.transaction(async (sql) => {
      await sql.query("SELECT id FROM organisations WHERE id=$1 FOR UPDATE", [
        org,
      ]);
      const grant = (
        await sql.query<any>(
          "SELECT enabled FROM examelite_sharing WHERE organisation_id=$1",
          [org],
        )
      ).rows[0];
      if (!grant?.enabled)
        throw new ConflictException(
          "Exam sharing was disabled. No local access was granted.",
        );
      if (
        !(
          await sql.query(
            "SELECT id FROM learners WHERE organisation_id=$1 AND id=$2 AND NOT archived FOR UPDATE",
            [org, learner],
          )
        ).rows.length
      )
        throw new NotFoundException();
      const old = (
        await sql.query<any>(
          "SELECT external_student_id,external_organisation_id FROM examelite_students WHERE organisation_id=$1 AND learner_id=$2",
          [org, learner],
        )
      ).rows[0];
      if (
        old &&
        (Number(old.external_student_id) !== response.student_id ||
          Number(old.external_organisation_id) !== c.organization_id)
      )
        throw new ConflictException(
          "Existing student link differs; administrator review is required.",
        );
      if (!old) {
        await sql.query(
          "INSERT INTO examelite_students(organisation_id,learner_id,external_organisation_id,external_student_id) VALUES($1,$2,$3,$4)",
          [org, learner, c.organization_id, response.student_id],
        );
        await sql.query(
          "INSERT INTO audit_events(id,actor_id,organisation_id,action) VALUES($1,$2,$3,$4)",
          [randomUUID(), user.id, org, "examelite.student.connected"],
        );
      }
    });
    return { connected: true };
  }

  async configuration(org: string) {
    try {
      const path = process.env.T4L_EXAMELITE_CONFIG;
      if (!path) return null;
      const config = JSON.parse(await readFile(path, "utf8"));
      if (Object.hasOwn(config, "_platform")) {
        const central = connectionFor(config, "_platform");
        return central ? { ...central, central: true } : null;
      }
      return connectionFor(config, org);
    } catch {
      throw unavailable();
    }
  }

  async request(c: Connection, org: string, path: string, payload?: unknown) {
    try {
      const response = await fetch(
        "https://examelite.com/api/tech4learn/v1/" + path,
        {
          method: payload === undefined ? "GET" : "POST",
          body: payload === undefined ? undefined : JSON.stringify(payload),
          headers: {
            Authorization: `Bearer ${c.token}`,
            "X-Tech4Learn-Organisation": org,
            Accept: "application/json",
            ...(payload === undefined
              ? {}
              : { "Content-Type": "application/json" }),
          },
          redirect: "error",
          signal: AbortSignal.timeout(10000),
        },
      );
      if (!response.ok || !response.body) throw unavailable();
      const reader = response.body.getReader();
      let size = 0;
      const chunks: Uint8Array[] = [];
      try {
        while (true) {
          const part = await reader.read();
          if (part.done) break;
          size += part.value.length;
          if (size > 512000) throw unavailable();
          chunks.push(part.value);
        }
      } finally {
        await reader.cancel();
      }
      const body = JSON.parse(Buffer.concat(chunks).toString("utf8"));
      if (body.version !== 1 || body.organization_id !== c.organization_id)
        throw unavailable();
      return body;
    } catch {
      throw unavailable();
    }
  }

  async status(user: Account, org: string) {
    await this.access.require(user, org, "configuration.view");
    const c = await this.configuration(org);
    if (!c)
      return {
        connected: false,
        message:
          "Dedicated ExamElite access has not been configured for this organisation.",
      };
    if (c.central) {
      const sharing = await this.sharing(org);
      if (!sharing.enabled)
        return {
          connected: false,
          message:
            "Exam access has not been enabled for this organisation by superadmin.",
        };
      await this.request(c, org, "platform/status");
      await this.access.require(user, org, "learners.view");
      const linkedLearners = (
        await this.db.query(
          `SELECT l.id,l.name,l.code FROM learners l
        JOIN examelite_students s ON s.organisation_id=l.organisation_id AND s.learner_id=l.id
        WHERE l.organisation_id=$1 AND NOT l.archived ORDER BY l.name,l.id`,
          [org],
        )
      ).rows;
      return { connected: true, linkedLearners };
    }
    const body = await this.request(c, org, "status");
    if (
      !Array.isArray(body.linked_learners) ||
      body.linked_learners.length > 1000 ||
      body.linked_learners.some(
        (id: unknown) =>
          typeof id !== "string" ||
          !/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/.test(
            id,
          ),
      )
    )
      throw unavailable();
    const a = await this.access.resolve(user, org);
    if (a.permissions.includes("learners.view"))
      await this.access.require(user, org, "learners.view");
    const linkedLearners =
      a.permissions.includes("learners.view") && a.scope_type === "organisation"
        ? (
            await this.db.query(
              "SELECT id,name,code FROM learners WHERE organisation_id=$1 AND id=ANY($2::uuid[]) AND NOT archived ORDER BY name,id",
              [org, body.linked_learners],
            )
          ).rows
        : [];
    return { connected: true, linkedLearners };
  }

  async list(
    user: Account,
    org: string,
    after: string = "0",
    learner?: string,
  ) {
    await this.access.require(user, org, "configuration.view");
    if (!/^\d{1,15}$/.test(after))
      throw new BadRequestException("Invalid page cursor.");
    if (learner) await this.learners.detail(user, org, learner);
    const c = await this.configuration(org);
    if (!c) throw unavailable();
    let centralPath: string | undefined;
    if (c.central) {
      const sharing = await this.sharing(org);
      if (!sharing.enabled)
        throw new ForbiddenException(
          "Exam access is disabled for this organisation.",
        );
      if (
        learner &&
        !(
          await this.db.query(
            "SELECT 1 FROM examelite_students WHERE organisation_id=$1 AND learner_id=$2",
            [org, learner],
          )
        ).rows.length
      )
        throw new NotFoundException(
          "This student has not been connected to ExamElite.",
        );
      centralPath = `platform/organisations/${org}/${learner ? `learners/${learner}/results` : "exams"}?after=${after}&exams=${sharing.exam_ids.join(",")}`;
    }
    const data = await this.request(
      c,
      org,
      centralPath ??
        (learner
          ? `learners/${encodeURIComponent(learner)}/results?after=${after}`
          : `exams?after=${after}`),
    );
    if (
      !Array.isArray(data.items) ||
      data.items.length > 50 ||
      (data.next !== null &&
        (!Number.isSafeInteger(data.next) || data.next <= Number(after))) ||
      (learner && data.learner_id !== learner)
    )
      throw unavailable();
    // Allowlist response fields; never forward arbitrary provider data to the browser.
    const items = data.items.map((r: any) => {
      if (!r || !Number.isSafeInteger(r.id) || r.id <= Number(after))
        throw unavailable();
      if (learner) {
        if (
          !Number.isSafeInteger(r.exam_id) ||
          typeof r.exam_name !== "string" ||
          r.exam_name.length > 1000 ||
          !["Pass", "Fail"].includes(r.result) ||
          !Number.isFinite(Number(r.percent)) ||
          typeof r.end_time !== "string"
        )
          throw unavailable();
        return {
          id: r.id,
          exam_id: r.exam_id,
          exam_name: r.exam_name,
          percent: Number(r.percent),
          result: r.result,
          end_time: r.end_time,
        };
      }
      if (
        typeof r.name !== "string" ||
        r.name.length > 1000 ||
        !Number.isFinite(Number(r.duration)) ||
        ![r.start_date, r.end_date].every(
          (v) => v === null || typeof v === "string",
        )
      )
        throw unavailable();
      return {
        id: r.id,
        name: r.name,
        duration: Number(r.duration),
        start_date: r.start_date,
        end_date: r.end_date,
      };
    });
    return { items, next: data.next };
  }
}
