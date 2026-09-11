import {
  BadRequestException,
  ConflictException,
  ForbiddenException,
  Injectable,
  NotFoundException,
} from "@nestjs/common";
import { createHash, randomUUID } from "node:crypto";
import { Database, type SqlClient } from "./database.js";
import { AccessService } from "./access.service.js";
import type { Account } from "./identity.service.js";
import type { Access, Permission } from "./access-model.js";
import { uuid } from "./security.js";
import { customValues, type FieldDefinition } from "./custom-values.js";
import {
  classifyLocation,
  jpeg,
  timestamp,
  type CentreLocation,
} from "./attendance-evidence.js";

type Policy = {
  accuracy_limit: number;
  timezone: string;
  self_review: boolean;
  version: number;
};
type Snapshot = {
  group_name: string;
  centre_name: string;
  centre: CentreLocation;
  policy: Policy;
  roster: { id: string; name: string; code: string }[];
  fields: FieldDefinition[];
};
type Row = {
  id: string;
  organisation_id: string;
  group_id: string;
  centre_id: string;
  actor_id: string;
  created_at: Date | string;
  received_at: Date | string | null;
  attendance_date: string | null;
  status: string;
  snapshot: Snapshot;
  evidence: Record<string, unknown> | null;
  marks: Record<string, string>;
  version: number;
  payload_hash: string | null;
};
const scope = `( $2='organisation' OR ($2='centres' AND centre_id=ANY($3::uuid[])) OR ($2='groups' AND group_id=ANY($3::uuid[])) )`;
const hash = (v: string | Buffer) =>
  createHash("sha256").update(v).digest("hex");
const canonical = (v: unknown): string =>
  JSON.stringify(v, function (_key, value) {
    return value && typeof value === "object" && !Array.isArray(value)
      ? Object.fromEntries(
          Object.entries(value).sort(([a], [b]) => a.localeCompare(b)),
        )
      : value;
  });
const defaults: Policy = {
  accuracy_limit: 50,
  timezone: "Asia/Kolkata",
  self_review: true,
  version: 0,
};

@Injectable()
export class AttendanceService {
  constructor(
    private readonly db: Database,
    private readonly access: AccessService,
  ) {}
  private allowed(a: Access, row: { group_id: string; centre_id: string }) {
    if (
      a.scope_type !== "organisation" &&
      !a.scope_ids.includes(
        a.scope_type === "groups" ? row.group_id : row.centre_id,
      )
    )
      throw new NotFoundException("Attendance record not found.");
  }
  private async record(sql: SqlClient, org: string, id: string, a: Access) {
    const r = (
      await sql.query<Row>(
        "SELECT * FROM attendance_sessions WHERE organisation_id=$1 AND id=$2",
        [org, uuid(id)],
      )
    ).rows[0];
    if (!r) throw new NotFoundException("Attendance record not found.");
    this.allowed(a, r);
    return r;
  }
  private async policy(sql: SqlClient, org: string) {
    return (
      (
        await sql.query<Policy>(
          "SELECT accuracy_limit,timezone,self_review,version FROM attendance_policy WHERE organisation_id=$1",
          [org],
        )
      ).rows[0] || defaults
    );
  }
  async getPolicy(user: Account, org: string) {
    await this.access.require(user, org, "attendance.view");
    return this.policy(this.db, org);
  }
  async savePolicy(user: Account, org: string, b: Record<string, unknown>) {
    return this.db.transaction(async (sql) => {
      await this.access.lock(sql, org);
      await this.access.require(user, org, "attendance.policy", sql);
      const before = await this.policy(sql, org);
      if (b.version !== before.version)
        throw new ConflictException("Settings changed. Reload and try again.");
      if (
        typeof b.accuracy_limit !== "number" ||
        !Number.isInteger(b.accuracy_limit) ||
        b.accuracy_limit < 5 ||
        b.accuracy_limit > 1000 ||
        typeof b.self_review !== "boolean" ||
        typeof b.timezone !== "string" ||
        b.timezone.length > 80
      )
        throw new BadRequestException(
          "Choose a valid accuracy limit, time zone and review policy.",
        );
      try {
        new Intl.DateTimeFormat("en", { timeZone: b.timezone }).format();
      } catch {
        throw new BadRequestException(
          "Use an IANA time zone, such as Asia/Kolkata.",
        );
      }
      await sql.query(
        "INSERT INTO attendance_policy(organisation_id,accuracy_limit,timezone,self_review,version) VALUES($1,$2,$3,$4,1) ON CONFLICT(organisation_id) DO UPDATE SET accuracy_limit=$2,timezone=$3,self_review=$4,version=attendance_policy.version+1",
        [org, b.accuracy_limit, b.timezone, b.self_review],
      );
      await this.access.audit(sql, user, org, "attendance.policy_changed", {
        before,
        after: b,
      });
      return this.policy(sql, org);
    });
  }
  async start(user: Account, org: string, b: Record<string, unknown>) {
    if (typeof b.group_id !== "string")
      throw new BadRequestException("Choose a group.");
    const groupId = uuid(b.group_id);
    return this.db.transaction(async (sql) => {
      await this.access.lock(sql, org);
      const a = await this.access.require(user, org, "attendance.capture", sql);
      const group = (
        await sql.query<
          {
            group_id: string;
            centre_id: string;
            group_name: string;
            centre_name: string;
          } & CentreLocation
        >(
          `SELECT g.id AS group_id,g.centre_id,g.name AS group_name,c.name AS centre_name,c.latitude,c.longitude,c.radius,c.location_approved FROM learning_groups g JOIN centres c ON c.id=g.centre_id AND c.organisation_id=g.organisation_id WHERE g.organisation_id=$1 AND g.id=$2 AND NOT g.archived AND NOT c.archived`,
          [org, groupId],
        )
      ).rows[0];
      if (!group) throw new NotFoundException("Active group not found.");
      this.allowed(a, group);
      const roster = (
        await sql.query<{ id: string; name: string; code: string }>(
          "SELECT id,name,code FROM learners WHERE organisation_id=$1 AND group_id=$2 AND NOT archived ORDER BY name,id LIMIT 501",
          [org, group.group_id],
        )
      ).rows;
      if (!roster.length || roster.length > 500)
        throw new BadRequestException(
          "Choose a group with 1–500 active learners.",
        );
      // Abandoned intents contain no photos. Keep their volume bounded per organisation.
      await sql.query(
        "DELETE FROM attendance_sessions WHERE organisation_id=$1 AND status='draft' AND created_at < now()-interval '10 minutes'",
        [org],
      );
      const count = (
        await sql.query<{ n: string }>(
          "SELECT count(*) AS n FROM attendance_sessions WHERE organisation_id=$1 AND status='draft'",
          [org],
        )
      ).rows[0];
      if (Number(count.n) >= 100)
        throw new ConflictException(
          "Too many capture attempts. Wait ten minutes and retry.",
        );
      const fields = (
        await sql.query<FieldDefinition>(
          "SELECT * FROM custom_fields WHERE organisation_id=$1 AND module='attendance' AND NOT archived ORDER BY label",
          [org],
        )
      ).rows;
      const snapshot: Snapshot = {
        group_name: group.group_name,
        centre_name: group.centre_name,
        centre: {
          latitude: group.latitude,
          longitude: group.longitude,
          radius: group.radius,
          location_approved: group.location_approved,
        },
        policy: await this.policy(sql, org),
        roster,
        fields,
      };
      const id = randomUUID();
      const row = (
        await sql.query<Row>(
          "INSERT INTO attendance_sessions(id,organisation_id,group_id,centre_id,actor_id,snapshot) VALUES($1,$2,$3,$4,$5,$6) RETURNING *",
          [
            id,
            org,
            group.group_id,
            group.centre_id,
            user.id,
            JSON.stringify(snapshot),
          ],
        )
      ).rows[0];
      return { id, created_at: row.created_at, snapshot };
    });
  }
  async submit(
    user: Account,
    org: string,
    id: string,
    b: Record<string, unknown>,
  ) {
    return this.db.transaction(async (sql) => {
      await this.access.lock(sql, org);
      const a = await this.access.require(user, org, "attendance.capture", sql);
      const r = await this.record(sql, org, id, a);
      if (r.actor_id !== user.id)
        throw new ForbiddenException(
          "Only the capture owner can submit this photo.",
        );
      const photo = jpeg(b.photo),
        captured = timestamp(b.captured_at),
        now = Date.now();
      const payloadHash = hash(
        canonical({
          photo: hash(photo),
          captured_at: b.captured_at,
          location: b.location,
          custom_values: b.custom_values,
        }),
      );
      if (r.status !== "draft") {
        if (r.payload_hash !== payloadHash)
          throw new ConflictException(
            "This capture was already submitted. Its evidence cannot be replaced.",
          );
        return { id: r.id, status: r.status };
      }
      const started = new Date(r.created_at).getTime();
      if (
        now - started > 600000 ||
        captured < started - 5000 ||
        captured > now + 30000
      )
        throw new BadRequestException(
          "Capture expired or device time is incorrect. Start a fresh capture.",
        );
      const active = (
        await sql.query(
          "SELECT g.id FROM learning_groups g JOIN centres c ON c.id=g.centre_id AND c.organisation_id=g.organisation_id WHERE g.organisation_id=$1 AND g.id=$2 AND NOT g.archived AND NOT c.archived",
          [org, r.group_id],
        )
      ).rows.length;
      if (!active)
        throw new ConflictException("The group or centre has been archived.");
      const date = new Intl.DateTimeFormat("en-CA", {
        timeZone: r.snapshot.policy.timezone,
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
      }).format(new Date(captured));
      if (
        (
          await sql.query(
            "SELECT id FROM attendance_sessions WHERE organisation_id=$1 AND group_id=$2 AND attendance_date=$3 AND status IN ('pending','confirmed')",
            [org, r.group_id, date],
          )
        ).rows.length
      )
        throw new ConflictException(
          "This group already has attendance for this day. Open it for review or correction.",
        );
      const stored = (
        await sql.query<{ n: string }>(
          "SELECT count(*) AS n FROM attendance_photos WHERE organisation_id=$1",
          [org],
        )
      ).rows[0];
      if (Number(stored.n) >= 1000)
        throw new ConflictException(
          "Organisation photo capacity reached. Ask the platform administrator to arrange storage before further capture.",
        );
      const evidence = {
        captured_at: new Date(captured).toISOString(),
        ...classifyLocation(
          b.location,
          r.snapshot.centre,
          captured,
          now,
          r.snapshot.policy.accuracy_limit,
        ),
        custom_values: customValues(r.snapshot.fields, b.custom_values ?? {}),
        photo_sha256: hash(photo),
      };
      await sql.query(
        "UPDATE attendance_sessions SET status='pending',received_at=$3,attendance_date=$4,evidence=$5,payload_hash=$6,version=version+1 WHERE organisation_id=$1 AND id=$2",
        [org, id, new Date(now), date, JSON.stringify(evidence), payloadHash],
      );
      await sql.query(
        "INSERT INTO attendance_photos(organisation_id,session_id,content) VALUES($1,$2,$3)",
        [org, id, photo],
      );
      await this.access.audit(sql, user, org, "attendance.submitted", {
        id,
        groupId: r.group_id,
        locationStatus: evidence.location_status,
      });
      return { id, status: "pending" };
    });
  }
  async list(user: Account, org: string, date: string, offset: number) {
    const a = await this.access.require(user, org, "attendance.view");
    if (
      !/^\d{4}-\d{2}-\d{2}$/.test(date) ||
      !Number.isFinite(Date.parse(date)) ||
      new Date(date).toISOString().slice(0, 10) !== date ||
      !Number.isInteger(offset) ||
      offset < 0
    )
      throw new BadRequestException("Choose a valid date and page.");
    const params = [org, a.scope_type, a.scope_ids, date];
    const where = `organisation_id=$1 AND ${scope} AND attendance_date=$4 AND status<>'draft'`;
    const rows = (
      await this.db.query(
        `SELECT id,attendance_date,status,version,snapshot->>'group_name' AS group_name,snapshot->>'centre_name' AS centre_name,evidence->>'location_status' AS location_status,marks FROM attendance_sessions WHERE ${where} ORDER BY created_at DESC,id LIMIT 50 OFFSET $5`,
        [...params, offset],
      )
    ).rows;
    const counts = (
      await this.db.query<{ status: string; n: string }>(
        `SELECT status,count(*) AS n FROM attendance_sessions WHERE ${where} GROUP BY status`,
        params,
      )
    ).rows;
    const totals = (
      await this.db.query<{ mark: string; n: string }>(
        `SELECT m.value AS mark,count(*) AS n FROM attendance_sessions s CROSS JOIN LATERAL jsonb_each_text(s.marks) m WHERE ${where} AND status='confirmed' GROUP BY m.value`,
        params,
      )
    ).rows;
    return { rows, counts, totals };
  }
  async detail(user: Account, org: string, id: string) {
    const a = await this.access.require(user, org, "attendance.view"),
      r = await this.record(this.db, org, id, a);
    if (r.status === "draft")
      throw new NotFoundException("Attendance record not found.");
    const { payload_hash, ...safe } = r;
    const reviews = (
      await this.db.query(
        "SELECT r.id,r.decision,r.reason,r.marks,r.created_at,u.name AS actor_name FROM attendance_reviews r LEFT JOIN users u ON u.id=r.actor_id WHERE r.organisation_id=$1 AND r.session_id=$2 ORDER BY r.created_at,r.id",
        [org, id],
      )
    ).rows;
    return { ...safe, reviews };
  }
  async photo(user: Account, org: string, id: string) {
    const a = await this.access.require(user, org, "attendance.photos");
    await this.record(this.db, org, id, a);
    const row = (
      await this.db.query<{ content: Buffer }>(
        "SELECT content FROM attendance_photos WHERE organisation_id=$1 AND session_id=$2",
        [org, id],
      )
    ).rows[0];
    if (!row) throw new NotFoundException("Photo not found.");
    await this.access.audit(this.db, user, org, "attendance.photo_viewed", {
      id,
    });
    return Buffer.from(row.content);
  }
  async review(
    user: Account,
    org: string,
    id: string,
    b: Record<string, unknown>,
  ) {
    return this.db.transaction(async (sql) => {
      await this.access.lock(sql, org);
      const a = await this.access.require(user, org, "attendance.review", sql),
        r = await this.record(sql, org, id, a);
      if (
        !["pending", "confirmed"].includes(r.status) ||
        b.version !== r.version
      )
        throw new ConflictException(
          "This attendance changed. Reload before reviewing.",
        );
      // A stricter current policy also applies to old pending captures.
      const policy = await this.policy(sql, org);
      if (
        (!policy.self_review || !r.snapshot.policy.self_review) &&
        r.actor_id === user.id
      )
        throw new ForbiddenException(
          "Another authorised staff member must review this capture.",
        );
      if (b.decision !== "confirmed" && b.decision !== "rejected")
        throw new BadRequestException("Choose confirm or reject.");
      if (r.status === "confirmed" && b.decision === "rejected")
        throw new BadRequestException(
          "Correct the learner marks on confirmed attendance instead.",
        );
      const warnings = r.evidence?.warnings as string[];
      const reason = typeof b.reason === "string" ? b.reason.trim() : "";
      if (
        reason.length > 1000 ||
        ((warnings.length > 0 ||
          r.status === "confirmed" ||
          b.decision === "rejected") &&
          reason.length < 5)
      )
        throw new BadRequestException(
          "Add a review or correction reason (5–1000 characters).",
        );
      if (warnings.length && b.acknowledge_warnings !== true)
        throw new BadRequestException(
          "Acknowledge the location warnings before deciding.",
        );
      let marks: Record<string, string> = {};
      if (b.decision === "confirmed") {
        if (!b.marks || typeof b.marks !== "object" || Array.isArray(b.marks))
          throw new BadRequestException("Mark every learner explicitly.");
        marks = b.marks as Record<string, string>;
        if (
          Object.keys(marks).length !== r.snapshot.roster.length ||
          r.snapshot.roster.some(
            (l) => !["present", "absent", "excused"].includes(marks[l.id]),
          )
        )
          throw new BadRequestException(
            "Choose present, absent or excused for every learner in this capture.",
          );
      }
      await sql.query(
        "INSERT INTO attendance_reviews(id,organisation_id,session_id,actor_id,decision,reason,marks) VALUES($1,$2,$3,$4,$5,$6,$7)",
        [
          randomUUID(),
          org,
          id,
          user.id,
          b.decision,
          reason,
          JSON.stringify(marks),
        ],
      );
      await sql.query(
        "UPDATE attendance_sessions SET status=$3,marks=$4,version=version+1 WHERE organisation_id=$1 AND id=$2",
        [org, id, b.decision, JSON.stringify(marks)],
      );
      await this.access.audit(
        sql,
        user,
        org,
        r.status === "confirmed"
          ? "attendance.corrected"
          : `attendance.${b.decision}`,
        { id },
      );
      return { id, status: b.decision };
    });
  }
}
