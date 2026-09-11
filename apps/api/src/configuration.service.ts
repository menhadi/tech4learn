import {
  Injectable,
  BadRequestException,
  ConflictException,
  ForbiddenException,
  NotFoundException,
} from "@nestjs/common";
import { Resolver } from "node:dns/promises";
import { Database, type SqlClient } from "./database.js";
import { AccessService } from "./access.service.js";
import { RecordsService } from "./records.service.js";
import { LearnersService } from "./learners.service.js";
import type { Account } from "./identity.service.js";
import type { Permission } from "./access-model.js";
import { field, token, uuid } from "./security.js";
import { customValues } from "./custom-values.js";

export const fieldModules = [
  "organisation",
  "centres",
  "groups",
  "staff",
  "learners",
  "attendance",
  "fln",
  "exams",
] as const;
const planned = ["attendance", "fln", "exams"];
const defaults = {
  kind: "other",
  template: "community",
  welcome: "",
  logo: "",
  enabled_modules: {
    learners: true,
    attendance: false,
    fln: false,
    exams: false,
  },
  version: 0,
};
type Settings = typeof defaults;
type Body = Record<string, unknown>;
@Injectable()
export class ConfigurationService {
  constructor(
    private readonly db: Database,
    private readonly access: AccessService,
    private readonly records: RecordsService,
    private readonly learners: LearnersService,
  ) {}
  async settings(org: string, sql: SqlClient = this.db) {
    return (
      (
        await sql.query<Settings>(
          "SELECT * FROM organisation_settings WHERE organisation_id=$1",
          [org],
        )
      ).rows[0] || { ...defaults }
    );
  }
  async read(user: Account, org: string) {
    await this.access.require(user, org, "configuration.view");
    return {
      ...(await this.settings(org)),
      domain:
        (
          await this.db.query(
            "SELECT * FROM organisation_domains WHERE organisation_id=$1",
            [org],
          )
        ).rows[0] || null,
      fieldModules,
      plannedModules: planned,
    };
  }
  async save(user: Account, org: string, b: Body) {
    return this.db.transaction(async (sql) => {
      await this.access.lock(sql, org);
      await this.access.require(user, org, "configuration.manage", sql);
      const before = await this.settings(org, sql);
      if (b.version !== before.version)
        throw new ConflictException("Setup changed. Reload before saving.");
      if (
        !["ngo", "coaching", "csr", "government", "other"].includes(
          String(b.kind),
        ) ||
        !["community", "academy", "minimal"].includes(String(b.template))
      )
        throw new BadRequestException(
          "Choose an organisation type and template.",
        );
      if (typeof b.welcome !== "string" || b.welcome.length > 400)
        throw new BadRequestException("Welcome text: maximum 400 characters.");
      if (
        typeof b.logo !== "string" ||
        b.logo.length > 180000 ||
        (b.logo &&
          !/^data:image\/(png|jpeg|webp);base64,[A-Za-z0-9+/]+={0,2}$/.test(
            b.logo,
          ))
      )
        throw new BadRequestException(
          "Choose a PNG, JPEG or WebP logo under 130 KB.",
        );
      const modules =
        b.enabled_modules === undefined
          ? before.enabled_modules
          : b.enabled_modules;
      if (
        !modules ||
        typeof modules !== "object" ||
        Array.isArray(modules) ||
        Object.keys(modules).sort().join() !==
          "attendance,exams,fln,learners" ||
        Object.values(modules).some((v) => typeof v !== "boolean")
      )
        throw new BadRequestException("Invalid module settings.");
      if (
        !user.is_superadmin &&
        JSON.stringify(modules) !== JSON.stringify(before.enabled_modules)
      ) {
        if (
          Object.keys(before.enabled_modules).some(
            (k) =>
              (modules as Body)[k] !==
              before.enabled_modules[k as keyof typeof before.enabled_modules],
          )
        )
          throw new ForbiddenException(
            "Only platform superadmins can change enabled modules.",
          );
      }
      const row = (
        await sql.query(
          "INSERT INTO organisation_settings(organisation_id,kind,template,welcome,logo,enabled_modules) VALUES ($1,$2,$3,$4,$5,$6) ON CONFLICT(organisation_id) DO UPDATE SET kind=$2,template=$3,welcome=$4,logo=$5,enabled_modules=$6,version=organisation_settings.version+1 RETURNING *",
          [
            org,
            b.kind,
            b.template,
            b.welcome.trim(),
            b.logo,
            JSON.stringify(modules),
          ],
        )
      ).rows[0];
      await this.access.audit(sql, user, org, "configuration.saved", {
        template: b.template,
        enabledModules: modules,
      });
      return row;
    });
  }
  async branding(slug: string) {
    if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slug) || slug.length > 60)
      throw new NotFoundException("Organisation address not found.");
    const org = (
      await this.db.query<{
        id: string;
        name: string;
        slug: string;
        colour: string;
      }>("SELECT id,name,slug,colour FROM organisations WHERE slug=$1", [slug])
    ).rows[0];
    if (!org) throw new NotFoundException("Organisation address not found.");
    const s = await this.settings(org.id);
    return { ...org, template: s.template, welcome: s.welcome, logo: s.logo };
  }
  async host(host: string) {
    return (
      (
        await this.db.query<{ id: string; slug: string; hostname: string }>(
          "SELECT o.id,o.slug,d.hostname FROM organisation_domains d JOIN organisations o ON o.id=d.organisation_id WHERE d.hostname=$1 AND d.active AND d.verified_at IS NOT NULL",
          [host.toLowerCase()],
        )
      ).rows[0] || null
    );
  }
  async domain(user: Account, org: string, b: Body) {
    await this.access.require(user, org, "configuration.manage");
    const hostname = field(b.hostname, "Hostname", 253).toLowerCase();
    const main = new URL(process.env.ADMIN_ORIGIN || "http://localhost:5173")
      .hostname;
    if (
      hostname === main ||
      !/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/.test(
        hostname,
      ) ||
      /\.(localhost|local|internal|test|invalid)$/.test(hostname)
    )
      throw new BadRequestException(
        "Enter a public hostname without a scheme, path, wildcard or port.",
      );
    return this.db.transaction(async (sql) => {
      await this.access.lock(sql, org);
      await this.access.require(user, org, "configuration.manage", sql);
      const old = (
        await sql.query<{ hostname: string }>(
          "SELECT hostname FROM organisation_domains WHERE organisation_id=$1",
          [org],
        )
      ).rows[0];
      if (old?.hostname === hostname)
        return (
          await sql.query(
            "SELECT * FROM organisation_domains WHERE organisation_id=$1",
            [org],
          )
        ).rows[0];
      if (old)
        throw new ConflictException(
          "Remove the existing domain before assigning another.",
        );
      const row = (
        await sql.query(
          "INSERT INTO organisation_domains(organisation_id,hostname,challenge) VALUES ($1,$2,$3) ON CONFLICT DO NOTHING RETURNING *",
          [org, hostname, token()],
        )
      ).rows[0];
      if (!row)
        throw new ConflictException("This hostname is already assigned.");
      await this.access.audit(sql, user, org, "domain.requested", { hostname });
      return row;
    });
  }
  async verify(user: Account, org: string, activate = false) {
    await this.access.require(user, org, "configuration.manage");
    if (activate && !user.is_superadmin)
      throw new ForbiddenException(
        "Only platform superadmins can activate domains.",
      );
    const d = (
      await this.db.query<{ hostname: string; challenge: string }>(
        "SELECT * FROM organisation_domains WHERE organisation_id=$1",
        [org],
      )
    ).rows[0];
    if (!d) throw new NotFoundException("Set a domain first.");
    const resolver = new Resolver({ timeout: 2000, tries: 1 });
    let found = false;
    try {
      found = (await resolver.resolveTxt("_tech4learn." + d.hostname)).some(
        (r) => r.join("") === "tech4learn-verification=" + d.challenge,
      );
    } catch {
      /* Show a useful retry message for missing records and DNS outages. */
    }
    if (!found)
      throw new BadRequestException(
        "Verification TXT record not found. Check DNS and try again.",
      );
    return this.db.transaction(async (sql) => {
      await this.access.lock(sql, org);
      await this.access.require(user, org, "configuration.manage", sql);
      const row = (
        await sql.query(
          "UPDATE organisation_domains SET verified_at=now(),active=CASE WHEN $4 THEN true ELSE active END WHERE organisation_id=$1 AND hostname=$2 AND challenge=$3 RETURNING *",
          [org, d.hostname, d.challenge, activate],
        )
      ).rows[0];
      if (!row)
        throw new ConflictException("Domain changed. Retry verification.");
      await this.access.audit(
        sql,
        user,
        org,
        activate ? "domain.activated" : "domain.verified",
        { hostname: d.hostname },
      );
      return row;
    });
  }
  async removeDomain(user: Account, org: string) {
    return this.db.transaction(async (sql) => {
      await this.access.lock(sql, org);
      await this.access.require(user, org, "configuration.manage", sql);
      await sql.query(
        "DELETE FROM organisation_domains WHERE organisation_id=$1",
        [org],
      );
      await this.access.audit(sql, user, org, "domain.removed", {});
      return { ok: true };
    });
  }
  module(module: string) {
    if (!(fieldModules as readonly string[]).includes(module))
      throw new NotFoundException("Unknown field module.");
    return module;
  }
  async definitions(user: Account, org: string, module: string) {
    this.module(module);
    const a = await this.access.resolve(user, org);
    const view = (
      {
        organisation: "organisation.view",
        centres: "centres.view",
        groups: "groups.view",
        staff: "members.view",
        learners: "learners.view",
      } as Record<string, string>
    )[module];
    if (
      !a.permissions.includes("fields.view") &&
      !a.permissions.includes(view as Permission)
    )
      throw new ForbiddenException("You cannot view these fields.");
    return this.learners.definitions(this.db, org, module);
  }
  async saveField(
    user: Account,
    org: string,
    module: string,
    b: Body,
    id?: string,
  ) {
    this.module(module);
    return this.learners.saveField(user, org, b, id, module);
  }
  private async record(
    user: Account,
    org: string,
    module: string,
    id: string,
    write: boolean,
    sql: SqlClient,
  ) {
    this.module(module);
    uuid(id);
    const permission = (
      {
        organisation: write ? "organisation.edit" : "organisation.view",
        centres: write ? "centres.edit" : "centres.view",
        groups: write ? "groups.edit" : "groups.view",
        staff: write ? "members.manage" : "members.view",
      } as Record<string, Permission>
    )[module];
    if (!permission)
      throw new BadRequestException(
        "Use the learner profile for learner values. Other module records are not available yet.",
      );
    const a = await this.access.require(user, org, permission, sql);
    if (module === "organisation") {
      if (id !== org) throw new NotFoundException("Organisation not found.");
      return;
    }
    if (module === "staff") {
      if (
        !(
          await sql.query(
            "SELECT user_id FROM memberships WHERE organisation_id=$1 AND user_id=$2",
            [org, id],
          )
        ).rows.length
      )
        throw new NotFoundException("Staff member not found.");
      return;
    }
    if (module === "centres") {
      const c = (
        await sql.query<{ archived: boolean }>(
          "SELECT c.archived FROM centres c WHERE c.organisation_id=$1 AND c.id=$2 AND ($3='organisation' OR ($3='centres' AND c.id=ANY($4::uuid[])) OR ($3='groups' AND NOT $5 AND EXISTS (SELECT 1 FROM learning_groups g WHERE g.organisation_id=$1 AND g.centre_id=c.id AND g.id=ANY($4::uuid[]))))",
          [org, id, a.scope_type, a.scope_ids, write],
        )
      ).rows[0];
      if (!c) throw new NotFoundException("Centre not found in your scope.");
      if (write && c.archived)
        throw new ConflictException("Archived centres cannot be edited.");
      return;
    }
    const g = (
      await sql.query<{ archived: boolean; centre_archived: boolean }>(
        "SELECT g.archived,c.archived AS centre_archived FROM learning_groups g JOIN centres c ON c.id=g.centre_id AND c.organisation_id=g.organisation_id WHERE g.organisation_id=$1 AND g.id=$2 AND ($3='organisation' OR ($3='centres' AND g.centre_id=ANY($4::uuid[])) OR ($3='groups' AND g.id=ANY($4::uuid[])))",
        [org, id, a.scope_type, a.scope_ids],
      )
    ).rows[0];
    if (!g) throw new NotFoundException("Group not found in your scope.");
    if (write && (g.archived || g.centre_archived))
      throw new ConflictException("Archived groups cannot be edited.");
  }
  async values(user: Account, org: string, module: string, id: string) {
    await this.record(user, org, module, id, false, this.db);
    const row = (
      await this.db.query(
        "SELECT values,version FROM record_field_values WHERE organisation_id=$1 AND module=$2 AND record_id=$3",
        [org, module, id],
      )
    ).rows[0] || { values: {}, version: 0 };
    await this.access.audit(this.db, user, org, "custom_values.viewed", {
      module,
      recordId: id,
    });
    return row;
  }
  async saveValues(
    user: Account,
    org: string,
    module: string,
    id: string,
    b: Body,
  ) {
    return this.db.transaction(async (sql) => {
      await this.access.lock(sql, org);
      await this.record(user, org, module, id, true, sql);
      const before = (
        await sql.query<{ values: Body; version: number }>(
          "SELECT values,version FROM record_field_values WHERE organisation_id=$1 AND module=$2 AND record_id=$3",
          [org, module, id],
        )
      ).rows[0] || { values: {}, version: 0 };
      if (b.version !== before.version)
        throw new ConflictException("Details changed. Reload before saving.");
      const values = customValues(
        await this.learners.definitions(sql, org, module),
        b.values,
        before.values,
      );
      const row = (
        await sql.query(
          "INSERT INTO record_field_values(organisation_id,module,record_id,values) VALUES ($1,$2,$3,$4) ON CONFLICT(organisation_id,module,record_id) DO UPDATE SET values=$4,version=record_field_values.version+1 RETURNING values,version",
          [org, module, id, JSON.stringify(values)],
        )
      ).rows[0];
      await this.access.audit(sql, user, org, "custom_values.saved", {
        module,
        recordId: id,
      });
      return row;
    });
  }
}
