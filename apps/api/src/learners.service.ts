import { groupDisplaySql } from "./academic-label.js";
import {
  Injectable,
  BadRequestException,
  NotFoundException,
  ConflictException,
  ForbiddenException,
} from "@nestjs/common";
import { randomUUID } from "node:crypto";
import { Database, type SqlClient } from "./database.js";
import { AccessService } from "./access.service.js";
import type { Account } from "./identity.service.js";
import type { Access, Permission } from "./access-model.js";
import { customValues } from "./custom-values.js";
import { field, uuid } from "./security.js";
type Body = Record<string, unknown>;
interface Definition {
  id: string;
  key: string;
  label: string;
  kind: string;
  required: boolean;
  options: string[];
  archived: boolean;
}
interface Learner {
  id: string;
  code: string;
  name: string;
  age: number | null;
  class_label: string;
  guardian_name: string;
  guardian_phone: string;
  custom_values: Body;
  group_id: string;
  archived: boolean;
  demo: boolean;
  version: number;
}
const object = (v: unknown): Body => {
  if (!v || typeof v !== "object" || Array.isArray(v))
    throw new BadRequestException("Expected an object.");
  return v as Body;
};
const optional = (v: unknown, max: number) => {
  if (v === undefined || v === null) return "";
  if (typeof v !== "string" || v.length > max)
    throw new BadRequestException(`Use text of up to ${max} characters.`);
  return v.trim();
};
const scopeSql =
  "($2='organisation' OR ($2='centres' AND g.centre_id=ANY($3::uuid[])) OR ($2='groups' AND g.id=ANY($3::uuid[])))";
@Injectable()
export class LearnersService {
  constructor(
    private readonly db: Database,
    private readonly access: AccessService,
  ) {}
  private args(org: string, a: Access) {
    return [org, a.scope_type, a.scope_ids];
  }
  private async group(sql: SqlClient, org: string, id: unknown, a: Access) {
    const row = (
      await sql.query<{ id: string }>(
        `SELECT g.id FROM learning_groups g JOIN centres c ON c.id=g.centre_id AND c.organisation_id=g.organisation_id WHERE g.organisation_id=$1 AND ${scopeSql} AND g.id=$4 AND NOT g.archived AND NOT c.archived`,
        [...this.args(org, a), uuid(field(id, "Group", 36))],
      )
    ).rows[0];
    if (!row)
      throw new NotFoundException("Choose an active group in your scope.");
    return row.id;
  }
  private async get(sql: SqlClient, org: string, id: string, a: Access) {
    const row = (
      await sql.query<Learner>(
        `SELECT l.* FROM learners l JOIN learning_groups g ON g.id=l.group_id AND g.organisation_id=l.organisation_id WHERE l.organisation_id=$1 AND ${scopeSql} AND l.id=$4`,
        [...this.args(org, a), uuid(id)],
      )
    ).rows[0];
    if (!row) throw new NotFoundException("Learner not found.");
    return row;
  }
  private redact(l: Learner, a: Access) {
    if (a.permissions.includes("learners.contacts")) return l;
    const { guardian_name, guardian_phone, ...rest } = l;
    return rest;
  }
  async list(
    user: Account,
    org: string,
    search = "",
    offset = 0,
    groupId = "",
    options: {
      limit?: number;
      sort?: string;
      direction?: string;
      filters?: Record<string, string>;
    } = {},
  ) {
    const access = await this.access.require(user, org, "learners.view");
    const limit = options.limit ?? 50,
      filters = options.filters || {};
    if (
      !Number.isSafeInteger(offset) ||
      offset < 0 ||
      search.length > 120 ||
      ![50, 100, 500].includes(limit) ||
      !filters ||
      Array.isArray(filters) ||
      typeof filters !== "object" ||
      Object.keys(filters).length > 100
    )
      throw new BadRequestException("Invalid search, filters or page size.");
    const params: unknown[] = [...this.args(org, access)];
    const bind = (v: unknown) => {
      params.push(v);
      return `$${params.length}`;
    };
    const expressions: Record<string, string> = {
      name: "l.name",
      code: "l.code",
      age: "l.age",
      class_label: "l.class_label",
      group_name: groupDisplaySql,
      centre_name: "c.name",
      status: "CASE WHEN l.archived THEN 'Archived' ELSE 'Active' END",
    };
    if (access.permissions.includes("learners.contacts")) {
      expressions.guardian_name = "l.guardian_name";
      expressions.guardian_phone = "l.guardian_phone";
    }
    const definitions = await this.definitions(this.db, org);
    for (const d of definitions)
      if (
        search ||
        filters[`custom_${d.key}`] ||
        options.sort === `custom_${d.key}`
      )
        expressions[`custom_${d.key}`] = `l.custom_values ->> ${bind(d.key)}`;
    const joins =
      "FROM learners l JOIN learning_groups g ON g.id=l.group_id AND g.organisation_id=l.organisation_id JOIN centres c ON c.id=g.centre_id AND c.organisation_id=g.organisation_id";
    const scope = `l.organisation_id=$1 AND ${scopeSql}`;
    const clauses = [scope];
    if (groupId) clauses.push(`l.group_id=${bind(uuid(groupId))}`);
    if (search) {
      const key = bind(search);
      clauses.push(
        `(${Object.values(expressions)
          .map(
            (e) => `strpos(lower(COALESCE((${e})::text,'')),lower(${key}))>0`,
          )
          .join(" OR ")})`,
      );
    }
    for (const [key, v] of Object.entries(filters)) {
      if (v === "") continue;
      if (!Object.hasOwn(expressions, key) || typeof v !== "string" || v.length > 120)
        throw new BadRequestException("Invalid or unavailable filter field.");
      if (v)
        clauses.push(
          `strpos(lower(COALESCE((${expressions[key]})::text,'')),lower(${bind(v)}))>0`,
        );
    }
    const sort = options.sort || "name";
    if (
      !Object.hasOwn(expressions, sort) ||
      !["asc", "desc"].includes(options.direction || "asc")
    )
      throw new BadRequestException("Invalid or unavailable sort field.");
    const where = clauses.join(" AND ");
    // Counts and rows use the same scoped query, including empty result pages.
    const order = `${expressions[sort]} ${options.direction === "desc" ? "DESC" : "ASC"} NULLS LAST,l.id`;
    const sql = `WITH scoped AS (SELECT l.*,${groupDisplaySql} AS group_name,c.name AS centre_name ${joins} WHERE ${scope}), page AS (SELECT l.*,${groupDisplaySql} AS group_name,c.name AS centre_name ${joins} WHERE ${where} ORDER BY ${order} LIMIT ${bind(limit + 1)} OFFSET ${bind(offset)}) SELECT (SELECT count(*)::int FROM scoped) AS total,(SELECT count(*)::int ${joins} WHERE ${where}) AS filtered,COALESCE((SELECT json_agg(page) FROM page),'[]'::json) AS items`;
    const result = (
      await this.db.query<{
        total: number;
        filtered: number;
        items: Learner[];
      }>(sql, params)
    ).rows[0];
    if (user.is_superadmin)
      await this.access.audit(this.db, user, org, "learners.list_viewed", {});
    return {
      items: result.items.slice(0, limit).map((l) => this.redact(l, access)),
      total: result.total,
      filtered: result.filtered,
      hasMore: result.items.length > limit,
    };
  }
  async detail(user: Account, org: string, id: string) {
    const a = await this.access.require(user, org, "learners.view");
    const l = await this.get(this.db, org, id, a);
    const history = (
      await this.db.query(
        `SELECT e.id,e.started_at,e.ended_at,e.reason,${groupDisplaySql} AS group_name,c.name AS centre_name FROM learner_enrolments e JOIN learning_groups g ON g.id=e.group_id AND g.organisation_id=e.organisation_id JOIN centres c ON c.id=g.centre_id WHERE e.organisation_id=$1 AND ${scopeSql} AND e.learner_id=$4 ORDER BY e.started_at DESC,e.id`,
        [...this.args(org, a), id],
      )
    ).rows;
    await this.access.audit(this.db, user, org, "learner.viewed", {
      learnerId: id,
      contacts: a.permissions.includes("learners.contacts"),
    });
    return { ...this.redact(l, a), history };
  }
  async fields(user: Account, org: string) {
    const a = await this.access.resolve(user, org);
    if (
      !a.permissions.includes("learners.view") &&
      !a.permissions.includes("fields.view")
    )
      throw new ForbiddenException(
        "You cannot view learner field definitions.",
      );
    return this.definitions(this.db, org);
  }
  async definitions(sql: SqlClient, org: string, module = "learners") {
    return (
      await sql.query<Definition>(
        "SELECT * FROM custom_fields WHERE organisation_id=$1 AND module=$2 ORDER BY archived,label",
        [org, module],
      )
    ).rows;
  }
  async saveField(
    user: Account,
    org: string,
    b: Body,
    id?: string,
    module = "learners",
  ) {
    return this.db.transaction(async (sql) => {
      await this.access.lock(sql, org);
      await this.access.require(user, org, "fields.manage", sql);
      const label = field(b.label, "Label", 80);
      const key = field(b.key, "Field key", 40);
      if (
        !/^[a-z][a-z0-9_]*$/.test(key) ||
        ["__proto__", "constructor", "prototype"].includes(key)
      )
        throw new BadRequestException(
          "Use a lowercase field key with letters, numbers and underscores.",
        );
      if (
        !["text", "number", "date", "choice", "boolean"].includes(
          String(b.kind),
        ) ||
        typeof b.required !== "boolean" ||
        typeof b.archived !== "boolean"
      )
        throw new BadRequestException("Choose a valid type and settings.");
      if (
        !Array.isArray(b.options) ||
        b.options.length > 50 ||
        b.options.some(
          (v) => typeof v !== "string" || !v.trim() || v.length > 80,
        ) ||
        new Set(b.options).size !== b.options.length
      )
        throw new BadRequestException("Use up to 50 distinct options.");
      if (b.kind === "choice" && !b.options.length)
        throw new BadRequestException("Add choices.");
      const before = id
        ? (await this.definitions(sql, org, module)).find(
            (f) => f.id === uuid(id),
          )
        : undefined;
      if (id && !before) throw new NotFoundException("Field not found.");
      if (
        before &&
        (before.key !== key ||
          before.kind !== b.kind ||
          before.options.some((v) => !(b.options as string[]).includes(v)))
      )
        throw new ConflictException(
          "Keys/types and existing choices cannot change. Archive this field and create another.",
        );
      if (!id && (await this.definitions(sql, org, module)).length >= 30)
        throw new ConflictException(
          "This release supports 30 field definitions per module.",
        );
      if (
        !id &&
        (await this.definitions(sql, org, module)).some((f) => f.key === key)
      )
        throw new ConflictException(
          "That field key already exists in this module.",
        );
      const result = (
        await sql.query(
          id
            ? "UPDATE custom_fields SET label=$3,required=$4,options=$5,archived=$6 WHERE organisation_id=$1 AND id=$2 AND module=$7 RETURNING *"
            : "INSERT INTO custom_fields(organisation_id,id,label,required,options,archived,key,kind,module) VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9) RETURNING *",
          id
            ? [
                org,
                id,
                label,
                b.required,
                JSON.stringify(b.options),
                b.archived,
                module,
              ]
            : [
                org,
                randomUUID(),
                label,
                b.required,
                JSON.stringify(b.options),
                b.archived,
                key,
                b.kind,
                module,
              ],
        )
      ).rows[0];
      await this.access.audit(sql, user, org, "learner_field.saved", {
        fieldId: result.id,
        key,
      });
      return result;
    });
  }
  private async validate(
    sql: SqlClient,
    org: string,
    b: Body,
    a: Access,
    before?: Learner,
  ) {
    const name = field(b.name, "Learner name", 120),
      code = field(b.code, "Learner code", 40).toUpperCase();
    if (!/^[A-Z0-9][A-Z0-9_-]*$/.test(code))
      throw new BadRequestException(
        "Learner code: letters, numbers, underscores and hyphens only.",
      );
    const age =
      b.age === null || b.age === "" || b.age === undefined
        ? null
        : Number(b.age);
    if (
      b.age !== undefined &&
      b.age !== null &&
      typeof b.age !== "number" &&
      typeof b.age !== "string"
    )
      throw new BadRequestException("Invalid age.");
    if (age !== null && (!Number.isInteger(age) || age < 0 || age > 120))
      throw new BadRequestException(
        "Age must be a whole number from 0 to 120.",
      );
    const group_id = await this.group(sql, org, b.group_id, a);
    const academicClass = (
      await sql.query<{ name: string }>(
        "SELECT k.name FROM learning_groups g JOIN learning_classes k ON k.organisation_id=g.organisation_id AND k.id=g.class_id WHERE g.organisation_id=$1 AND g.id=$2",
        [org, group_id],
      )
    ).rows[0];
    if (before && group_id !== before.group_id)
      throw new BadRequestException(
        "Use Transfer enrolment to change the group.",
      );
    if (
      !a.permissions.includes("learners.contacts") &&
      (b.guardian_name !== undefined || b.guardian_phone !== undefined)
    )
      throw new ForbiddenException(
        "Guardian contacts require separate permission.",
      );
    const guardian_name = a.permissions.includes("learners.contacts")
        ? optional(b.guardian_name, 120)
        : before?.guardian_name || "",
      guardian_phone = a.permissions.includes("learners.contacts")
        ? optional(b.guardian_phone, 40)
        : before?.guardian_phone || "";
    if (guardian_phone && !/^[+0-9 ()-]{5,40}$/.test(guardian_phone))
      throw new BadRequestException("Invalid guardian phone.");
    const custom_values = customValues(
      await this.definitions(sql, org),
      b.custom_values ?? {},
      before?.custom_values,
    );
    const exists = await sql.query(
      "SELECT id FROM learners WHERE organisation_id=$1 AND code=$2 AND id<>$3",
      [org, code, before?.id || randomUUID()],
    );
    if (exists.rows.length)
      throw new ConflictException(
        "Learner code already exists in this organisation.",
      );
    const duplicate =
      (
        await sql.query(
          "SELECT id FROM learners WHERE organisation_id=$1 AND lower(name)=lower($2) AND age IS NOT DISTINCT FROM $3 AND id<>$4",
          [org, name, age, before?.id || randomUUID()],
        )
      ).rows.length > 0;
    return {
      code,
      name,
      age,
      class_label: academicClass?.name ?? optional(b.class_label, 80),
      guardian_name,
      guardian_phone,
      custom_values,
      group_id,
      duplicate,
    };
  }
  private async insert(
    sql: SqlClient,
    user: Account,
    org: string,
    v: Awaited<ReturnType<LearnersService["validate"]>>,
  ) {
    const id = randomUUID();
    await sql.query(
      "INSERT INTO learners(id,organisation_id,code,name,age,class_label,guardian_name,guardian_phone,custom_values,group_id) VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10)",
      [
        id,
        org,
        v.code,
        v.name,
        v.age,
        v.class_label,
        v.guardian_name,
        v.guardian_phone,
        JSON.stringify(v.custom_values),
        v.group_id,
      ],
    );
    await sql.query(
      "INSERT INTO learner_enrolments(id,organisation_id,learner_id,group_id,actor_id) VALUES ($1,$2,$3,$4,$5)",
      [randomUUID(), org, id, v.group_id, user.id],
    );
    await this.access.audit(sql, user, org, "learner.created", {
      learnerId: id,
    });
    return id;
  }
  async demoStudent(user: Account, org: string, b: Body) {
    return this.db.transaction(async (sql) => {
      await this.access.lock(sql, org);
      const a = await this.access.require(user, org, "learners.create", sql);
      const group = await this.group(sql, org, b.group_id, a);
      const existing = (
        await sql.query<{ id: string; demo: boolean; group_id: string }>(
          "SELECT id,demo,group_id FROM learners WHERE organisation_id=$1 AND code='DEMO-STUDENT-001'",
          [org],
        )
      ).rows[0];
      if (existing) {
        await this.get(sql, org, existing.id, a);
        if (!existing.demo || existing.group_id !== group)
          throw new ConflictException(
            "The demo code already exists. Open the existing record instead.",
          );
        return { id: existing.id };
      }
      const definitions = await this.definitions(sql, org);
      const custom_values = Object.fromEntries(
        definitions
          .filter((d) => !d.archived && d.required)
          .map((d) => [
            d.key,
            d.kind === "number"
              ? 0
              : d.kind === "boolean"
                ? false
                : d.kind === "date"
                  ? "2018-01-01"
                  : d.kind === "choice"
                    ? d.options[0]
                    : "DEMO value",
          ]),
      );
      const v = await this.validate(
        sql,
        org,
        {
          code: "DEMO-STUDENT-001",
          name: "DEMO — Test Student",
          age: 8,
          class_label: "Class 3",
          group_id: group,
          custom_values,
        },
        a,
      );
      const id = await this.insert(sql, user, org, v);
      await sql.query(
        "UPDATE learners SET demo=true WHERE organisation_id=$1 AND id=$2",
        [org, id],
      );
      await this.access.audit(sql, user, org, "learner.demo_created", {
        learnerId: id,
      });
      return { id };
    });
  }
  async save(user: Account, org: string, b: Body, id?: string) {
    return this.db.transaction(async (sql) => {
      await this.access.lock(sql, org);
      const a = await this.access.require(
        user,
        org,
        id ? "learners.edit" : "learners.create",
        sql,
      );
      const before = id ? await this.get(sql, org, id, a) : undefined;
      if (before?.archived)
        throw new ConflictException("Archived learners cannot be edited.");
      if (before && b.version !== before.version)
        throw new ConflictException("Profile changed. Reload before saving.");
      const v = await this.validate(sql, org, b, a, before);
      if (v.duplicate && b.confirmDuplicate !== true)
        throw new ConflictException(
          "A learner with this name and age exists. Review the duplicate warning before confirming.",
        );
      if (!id) return { id: await this.insert(sql, user, org, v) };
      await sql.query(
        "UPDATE learners SET code=$3,name=$4,age=$5,class_label=$6,guardian_name=$7,guardian_phone=$8,custom_values=$9,version=version+1 WHERE organisation_id=$1 AND id=$2",
        [
          org,
          id,
          v.code,
          v.name,
          v.age,
          v.class_label,
          v.guardian_name,
          v.guardian_phone,
          JSON.stringify(v.custom_values),
        ],
      );
      await this.access.audit(sql, user, org, "learner.updated", {
        learnerId: id,
      });
      return { id };
    });
  }
  async transfer(user: Account, org: string, id: string, b: Body) {
    return this.db.transaction(async (sql) => {
      await this.access.lock(sql, org);
      const a = await this.access.require(user, org, "learners.transfer", sql);
      const l = await this.get(sql, org, id, a);
      if (l.archived || b.version !== l.version)
        throw new ConflictException(
          "Reload the active learner before transferring.",
        );
      const target = await this.group(sql, org, b.group_id, a);
      if (target === l.group_id)
        throw new BadRequestException("Choose a different group.");
      const reason = field(b.reason, "Transfer reason", 200);
      const academicClass = (
        await sql.query<{ name: string }>(
          "SELECT k.name FROM learning_groups g JOIN learning_classes k ON k.organisation_id=g.organisation_id AND k.id=g.class_id WHERE g.organisation_id=$1 AND g.id=$2",
          [org, target],
        )
      ).rows[0];
      await sql.query(
        "UPDATE learner_enrolments SET ended_at=now() WHERE organisation_id=$1 AND learner_id=$2 AND ended_at IS NULL",
        [org, id],
      );
      await sql.query(
        "INSERT INTO learner_enrolments(id,organisation_id,learner_id,group_id,actor_id,reason) VALUES ($1,$2,$3,$4,$5,$6)",
        [randomUUID(), org, id, target, user.id, reason],
      );
      await sql.query(
        "UPDATE learners SET group_id=$3,class_label=$4,version=version+1 WHERE organisation_id=$1 AND id=$2",
        [org, id, target, academicClass?.name ?? l.class_label],
      );
      await this.access.audit(sql, user, org, "learner.transferred", {
        learnerId: id,
        from: l.group_id,
        to: target,
      });
      return { ok: true };
    });
  }
  async archive(user: Account, org: string, id: string, b: Body) {
    return this.db.transaction(async (sql) => {
      await this.access.lock(sql, org);
      const a = await this.access.require(user, org, "learners.archive", sql);
      const l = await this.get(sql, org, id, a);
      if (l.archived || b.version !== l.version)
        throw new ConflictException("Reload the active learner first.");
      await sql.query(
        "UPDATE learners SET archived=true,version=version+1 WHERE organisation_id=$1 AND id=$2",
        [org, id],
      );
      await sql.query(
        "UPDATE learner_enrolments SET ended_at=now() WHERE organisation_id=$1 AND learner_id=$2 AND ended_at IS NULL",
        [org, id],
      );
      await this.access.audit(sql, user, org, "learner.archived", {
        learnerId: id,
      });
      return { ok: true };
    });
  }
  private async review(
    sql: SqlClient,
    user: Account,
    org: string,
    rows: unknown,
    a: Access,
  ) {
    if (!Array.isArray(rows) || !rows.length || rows.length > 100)
      throw new BadRequestException("Import 1–100 learners at a time.");
    const codes = new Set<string>(),
      names = new Set<string>();
    const results = [];
    for (const [i, row] of rows.entries()) {
      try {
        const v = await this.validate(sql, org, object(row), a);
        if (codes.has(v.code))
          throw new BadRequestException(
            "Duplicate learner code within this file.",
          );
        codes.add(v.code);
        const key = JSON.stringify([v.name.toLowerCase(), v.age]);
        const warning =
          v.duplicate || names.has(key)
            ? "Possible duplicate name and age. Review before importing."
            : null;
        names.add(key);
        results.push({ row: i + 2, value: v, error: null, warning });
      } catch (e) {
        if (
          !(
            e instanceof BadRequestException ||
            e instanceof ConflictException ||
            e instanceof ForbiddenException ||
            e instanceof NotFoundException
          )
        )
          throw e;
        results.push({ row: i + 2, error: e.message, warning: null });
      }
    }
    return results;
  }
  async preview(user: Account, org: string, b: Body) {
    return this.db.transaction(async (sql) => {
      await this.access.lock(sql, org);
      const a = await this.access.require(user, org, "learners.import", sql);
      await this.access.require(user, org, "learners.create", sql);
      const results = await this.review(sql, user, org, b.rows, a);
      let id: string | null = null;
      if (results.every((r) => !r.error)) {
        id = randomUUID();
        await sql.query(
          "DELETE FROM learner_imports WHERE organisation_id=$1 AND expires_at<now()",
          [org],
        );
        await sql.query(
          "INSERT INTO learner_imports(id,organisation_id,actor_id,rows) VALUES ($1,$2,$3,$4)",
          [id, org, user.id, JSON.stringify(b.rows)],
        );
      }
      return { id, results };
    });
  }
  async commit(user: Account, org: string, id: string, b: Body) {
    return this.db.transaction(async (sql) => {
      await this.access.lock(sql, org);
      const a = await this.access.require(user, org, "learners.import", sql);
      await this.access.require(user, org, "learners.create", sql);
      const batch = (
        await sql.query<{ rows: Body[]; result: unknown }>(
          "SELECT rows,result FROM learner_imports WHERE organisation_id=$1 AND id=$2 AND actor_id=$3 AND expires_at>now() FOR UPDATE",
          [org, uuid(id), user.id],
        )
      ).rows[0];
      if (!batch)
        throw new NotFoundException(
          "Preview expired or unavailable. Preview again.",
        );
      if (batch.result) return batch.result;
      const reviewed = await this.review(sql, user, org, batch.rows, a);
      if (reviewed.some((r) => r.error))
        throw new ConflictException(
          "Data or permissions changed. Preview the file again.",
        );
      if (reviewed.some((r) => r.warning) && b.confirmDuplicates !== true)
        throw new ConflictException(
          "Review possible duplicates before importing.",
        );
      const ids = [];
      for (const r of reviewed)
        ids.push(await this.insert(sql, user, org, r.value!));
      const result = { count: ids.length, ids };
      await sql.query(
        "UPDATE learner_imports SET result=$3,rows=$4 WHERE organisation_id=$1 AND id=$2",
        [org, id, JSON.stringify(result), "[]"],
      );
      return result;
    });
  }
}
