import {
  BadRequestException,
  ConflictException,
  ForbiddenException,
  Injectable,
  NotFoundException,
} from "@nestjs/common";
import { randomUUID } from "node:crypto";
import { Database } from "./database.js";
import { AccessService } from "./access.service.js";
import { RecordsService } from "./records.service.js";
import type { Account } from "./identity.service.js";
import { field, uuid } from "./security.js";

@Injectable()
export class AcademicService {
  constructor(
    private readonly db: Database,
    private readonly access: AccessService,
    private readonly records: RecordsService,
  ) {}
  async years(user: Account, org: string) {
    await this.access.require(user, org, "groups.view");
    // Years are shared organisation metadata, like field definitions; no student records or totals.
    return (
      await this.db.query(
        "SELECT * FROM academic_years WHERE organisation_id=$1 ORDER BY starts_on DESC,name",
        [org],
      )
    ).rows;
  }
  async createYear(user: Account, org: string, b: Record<string, unknown>) {
    const name = field(b.name, "Academic year", 80);
    const date = (v: unknown) => {
      if (
        typeof v !== "string" ||
        !/^\d{4}-\d{2}-\d{2}$/.test(v) ||
        !Number.isFinite(Date.parse(v)) ||
        new Date(v).toISOString().slice(0, 10) !== v
      )
        throw new BadRequestException(
          "Use valid start and end dates (YYYY-MM-DD).",
        );
      return v;
    };
    const start = date(b.starts_on),
      end = date(b.ends_on);
    if (end < start)
      throw new BadRequestException("End date must not precede start date.");
    return this.db.transaction(async (sql) => {
      await this.access.lock(sql, org);
      const a = await this.access.require(user, org, "groups.create", sql);
      if (a.scope_type !== "organisation")
        throw new ForbiddenException(
          "An organisation-wide administrator must create academic years.",
        );
      if (
        (
          await sql.query(
            "SELECT id FROM academic_years WHERE organisation_id=$1 AND lower(name)=lower($2)",
            [org, name],
          )
        ).rows.length
      )
        throw new ConflictException("This academic year already exists.");
      const row = (
        await sql.query(
          "INSERT INTO academic_years(id,organisation_id,name,starts_on,ends_on) VALUES($1,$2,$3,$4,$5) RETURNING *",
          [randomUUID(), org, name, start, end],
        )
      ).rows[0];
      await this.access.audit(sql, user, org, "academic_year.created", {
        yearId: row.id,
      });
      return row;
    });
  }
  async classes(user: Account, org: string) {
    const a = await this.access.require(user, org, "groups.view");
    return (
      await this.db.query(
        `SELECT k.*,c.name AS centre_name,y.name AS year_name,y.archived AS year_archived FROM learning_classes k JOIN centres c ON c.organisation_id=k.organisation_id AND c.id=k.centre_id JOIN academic_years y ON y.organisation_id=k.organisation_id AND y.id=k.academic_year_id WHERE k.organisation_id=$1 AND ($2='organisation' OR ($2='centres' AND k.centre_id=ANY($3::uuid[])) OR ($2='groups' AND EXISTS(SELECT 1 FROM learning_groups g WHERE g.organisation_id=k.organisation_id AND g.class_id=k.id AND g.id=ANY($3::uuid[])))) ORDER BY y.starts_on DESC,c.name,k.name`,
        [org, a.scope_type, a.scope_ids],
      )
    ).rows;
  }
  async createClass(user: Account, org: string, b: Record<string, unknown>) {
    const name = field(b.name, "Class name", 80),
      centre = uuid(b.centre_id as string),
      year = uuid(b.academic_year_id as string);
    return this.db.transaction(async (sql) => {
      await this.access.lock(sql, org);
      const a = await this.access.require(user, org, "groups.create", sql);
      if (a.scope_type === "groups")
        throw new ForbiddenException(
          "Section-scoped staff cannot create classes.",
        );
      const c = await this.records.centreAllowed(sql, org, centre, a, true);
      if (c.archived) throw new ConflictException("Choose an active centre.");
      const y = (
        await sql.query(
          "SELECT * FROM academic_years WHERE organisation_id=$1 AND id=$2",
          [org, year],
        )
      ).rows[0];
      if (!y) throw new NotFoundException("Academic year not found.");
      if (y.archived)
        throw new ConflictException("Choose an active academic year.");
      if (
        (
          await sql.query(
            "SELECT id FROM learning_classes WHERE organisation_id=$1 AND centre_id=$2 AND academic_year_id=$3 AND lower(name)=lower($4)",
            [org, centre, year, name],
          )
        ).rows.length
      )
        throw new ConflictException(
          "This class already exists for that centre and year.",
        );
      const row = (
        await sql.query(
          "INSERT INTO learning_classes(id,organisation_id,centre_id,academic_year_id,name) VALUES($1,$2,$3,$4,$5) RETURNING *",
          [randomUUID(), org, centre, year, name],
        )
      ).rows[0];
      await this.access.audit(sql, user, org, "class.created", {
        classId: row.id,
        centreId: centre,
        yearId: year,
      });
      return row;
    });
  }
  async archive(
    user: Account,
    org: string,
    id: string,
    kind: "class" | "year",
  ) {
    uuid(id);
    return this.db.transaction(async (sql) => {
      await this.access.lock(sql, org);
      const a = await this.access.require(user, org, "groups.archive", sql);
      if (
        a.scope_type === "groups" ||
        (kind === "year" && a.scope_type !== "organisation")
      )
        throw new ForbiddenException(
          "This archive operation requires broader scope.",
        );
      const table = kind === "year" ? "academic_years" : "learning_classes";
      const row = (
        await sql.query(
          `SELECT * FROM ${table} WHERE organisation_id=$1 AND id=$2`,
          [org, id],
        )
      ).rows[0];
      if (!row) throw new NotFoundException("Record not found.");
      if (kind === "class")
        await this.records.centreAllowed(
          sql,
          org,
          row.centre_id as string,
          a,
          true,
        );
      const children =
        kind === "year"
          ? "SELECT id FROM learning_classes WHERE organisation_id=$1 AND academic_year_id=$2 AND NOT archived"
          : "SELECT id FROM learning_groups WHERE organisation_id=$1 AND class_id=$2 AND NOT archived";
      if ((await sql.query(children, [org, id])).rows.length)
        throw new ConflictException("Archive the active child records first.");
      await sql.query(
        `UPDATE ${table} SET archived=true WHERE organisation_id=$1 AND id=$2`,
        [org, id],
      );
      await this.access.audit(sql, user, org, `${kind}.archived`, { id });
      return { ok: true };
    });
  }
}
