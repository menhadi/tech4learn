import {
  BadRequestException,
  ConflictException,
  ForbiddenException,
  Injectable,
  NotFoundException,
} from "@nestjs/common";
import { randomUUID } from "node:crypto";
import { Database, type SqlClient } from "./database.js";
import type { Account } from "./identity.service.js";
import {
  allPermissions,
  templates,
  widePermissions,
  type Access,
  type AccessRole,
  type Grant,
  type Permission,
  type Scope,
} from "./access-model.js";
import { digest, emailValue, field, token, uuid } from "./security.js";

@Injectable()
export class AccessService {
  constructor(private readonly db: Database) {}
  async initialise(sql: SqlClient, orgId: string) {
    for (const role of templates)
      await sql.query(
        "INSERT INTO access_roles(id,organisation_id,name,permissions,protected) VALUES ($1,$2,$3,$4,$5)",
        [randomUUID(), orgId, role.name, role.permissions, role.protected],
      );
  }
  async lock(sql: SqlClient, org: string) {
    uuid(org);
    if (
      !(
        await sql.query("SELECT id FROM organisations WHERE id=$1 FOR UPDATE", [
          org,
        ])
      ).rows.length
    )
      throw new NotFoundException("Organisation not found.");
  }
  async resolve(
    user: Account,
    org: string,
    sql: SqlClient = this.db,
  ): Promise<Access> {
    uuid(org);
    if (user.is_superadmin) {
      if (
        !(await sql.query("SELECT id FROM organisations WHERE id=$1", [org]))
          .rows.length
      )
        throw new NotFoundException("Organisation not found.");
      return {
        roleId: null,
        roleName: "Platform superadmin",
        permissions: allPermissions,
        scope_type: "organisation",
        scope_ids: [],
        owner: true,
      };
    }
    const { rows } = await sql.query<Access & { protected: boolean }>(
      `SELECT r.id AS "roleId",r.name AS "roleName",r.permissions,r.protected,m.scope_type,m.scope_ids FROM memberships m JOIN access_roles r ON r.id=m.role_id AND r.organisation_id=m.organisation_id WHERE m.user_id=$1 AND m.organisation_id=$2 AND m.status='active'`,
      [user.id, org],
    );
    if (!rows[0]) throw new NotFoundException("Organisation not found.");
    return { ...rows[0], owner: rows[0].protected };
  }
  async require(
    user: Account,
    org: string,
    permission: Permission,
    sql: SqlClient = this.db,
  ) {
    const access = await this.resolve(user, org, sql);
    if (
      !access.permissions.includes(permission) ||
      (widePermissions.includes(permission) &&
        access.scope_type !== "organisation")
    )
      throw new ForbiddenException(
        "You do not have permission for this action.",
      );
    if (
      permission.startsWith("learners.") ||
      permission.startsWith("attendance.")
    ) {
      const settings = (
        await sql.query<{ enabled_modules: Record<string, boolean> }>(
          "SELECT enabled_modules FROM organisation_settings WHERE organisation_id=$1",
          [org],
        )
      ).rows[0];
      if (
        permission.startsWith("attendance.") &&
        settings?.enabled_modules.attendance !== true
      )
        throw new ForbiddenException(
          "Attendance module is disabled for this organisation.",
        );
      if (
        permission.startsWith("learners.") &&
        settings?.enabled_modules.learners === false
      )
        throw new ForbiddenException(
          "Learner module is disabled for this organisation.",
        );
    }
    return access;
  }
  async audit(
    sql: SqlClient,
    user: Account,
    org: string,
    action: string,
    details: unknown,
  ) {
    await sql.query(
      "INSERT INTO audit_events(id,actor_id,organisation_id,action,details) VALUES ($1,$2,$3,$4,$5)",
      [randomUUID(), user.id, org, action, JSON.stringify(details)],
    );
  }
  async roles(user: Account, org: string) {
    await this.require(user, org, "roles.view");
    return (
      await this.db.query<AccessRole>(
        "SELECT * FROM access_roles WHERE organisation_id=$1 ORDER BY protected DESC,name",
        [org],
      )
    ).rows;
  }
  private permissions(value: unknown): Permission[] {
    if (
      !Array.isArray(value) ||
      value.length > allPermissions.length ||
      value.some((p) => !allPermissions.includes(p))
    )
      throw new BadRequestException(
        "Choose permissions from the available list.",
      );
    const permissions = [...new Set<Permission>(value)];
    if (
      permissions.includes("learners.import") &&
      !permissions.includes("learners.create")
    )
      throw new BadRequestException(
        "Importing learners also requires learners.create.",
      );
    if (
      permissions.some(
        (p) => p.startsWith("learners.") || p.startsWith("attendance."),
      ) &&
      !permissions.includes("groups.view")
    )
      throw new BadRequestException(
        "Learner and attendance access also require groups.view.",
      );
    if (
      permissions.includes("members.manage") &&
      ["roles.view", "centres.view", "groups.view"].some(
        (p) => !permissions.includes(p as Permission),
      )
    )
      throw new BadRequestException(
        "Managing staff also requires roles.view, centres.view and groups.view.",
      );
    if (
      permissions.some((p) => p.startsWith("groups.") && p !== "groups.view") &&
      !permissions.includes("centres.view")
    )
      throw new BadRequestException(
        "Managing groups also requires centres.view.",
      );
    if (!permissions.includes("organisation.view"))
      throw new BadRequestException(
        "Every role needs View organisation profile.",
      );
    for (const key of permissions) {
      const view = `${key.split(".")[0]}.view` as Permission;
      if (!permissions.includes(view))
        throw new BadRequestException(`Also enable ${view} for this action.`);
    }
    return permissions;
  }
  private subset(
    access: Access,
    role: AccessRole | { permissions: Permission[]; protected?: boolean },
  ) {
    if (
      role.permissions.some((p) => !access.permissions.includes(p)) ||
      (role.protected && !access.owner)
    )
      throw new ForbiddenException(
        "You cannot delegate or change access beyond your own permissions.",
      );
  }
  async saveRole(
    user: Account,
    org: string,
    body: Record<string, unknown>,
    id?: string,
  ) {
    const name = field(body.name, "Role name", 80);
    const permissions = this.permissions(body.permissions);
    if (id) uuid(id);
    return this.db.transaction(async (sql) => {
      await this.lock(sql, org);
      const access = await this.require(user, org, "roles.manage", sql);
      this.subset(access, { permissions });
      let before: AccessRole | undefined;
      if (id) {
        before = (
          await sql.query<AccessRole>(
            "SELECT * FROM access_roles WHERE organisation_id=$1 AND id=$2",
            [org, id],
          )
        ).rows[0];
        if (!before) throw new NotFoundException("Role not found.");
        if (before.protected || access.roleId === id)
          throw new ForbiddenException(
            "The organisation-admin safety role and your own role cannot be edited here.",
          );
        this.subset(access, before);
        if (permissions.some((p) => widePermissions.includes(p))) {
          const scoped = await sql.query(
            `SELECT 1 FROM memberships WHERE organisation_id=$1 AND role_id=$2 AND scope_type <> 'organisation' UNION ALL SELECT 1 FROM invitations WHERE organisation_id=$1 AND role_id=$2 AND scope_type <> 'organisation' AND accepted_at IS NULL AND expires_at>now()`,
            [org, id],
          );
          if (scoped.rows.length)
            throw new ConflictException(
              "This role has centre/group assignments. Organisation-wide permissions require organisation-wide assignments first.",
            );
        }
      }
      const conflict = await sql.query(
        "SELECT id FROM access_roles WHERE organisation_id=$1 AND lower(name)=lower($2) AND id<>$3",
        [org, name, id || randomUUID()],
      );
      if (conflict.rows.length)
        throw new ConflictException("A role with that name already exists.");
      const role = id
        ? (
            await sql.query<AccessRole>(
              "UPDATE access_roles SET name=$3,permissions=$4 WHERE organisation_id=$1 AND id=$2 RETURNING *",
              [org, id, name, permissions],
            )
          ).rows[0]
        : (
            await sql.query<AccessRole>(
              "INSERT INTO access_roles(id,organisation_id,name,permissions) VALUES ($1,$2,$3,$4) RETURNING *",
              [randomUUID(), org, name, permissions],
            )
          ).rows[0];
      await this.audit(sql, user, org, id ? "role.updated" : "role.created", {
        roleId: role.id,
        before: before
          ? { name: before.name, permissions: before.permissions }
          : null,
        after: { name, permissions },
      });
      return role;
    });
  }
  async validateGrant(
    sql: SqlClient,
    org: string,
    body: Record<string, unknown>,
    access: Access,
  ): Promise<Grant & { role: AccessRole }> {
    const roleId = uuid(field(body.role_id, "Role", 36));
    const role = (
      await sql.query<AccessRole>(
        "SELECT * FROM access_roles WHERE organisation_id=$1 AND id=$2",
        [org, roleId],
      )
    ).rows[0];
    if (!role)
      throw new BadRequestException("Choose a role from this organisation.");
    this.subset(access, role);
    const type = body.scope_type;
    if (!["organisation", "centres", "groups"].includes(String(type)))
      throw new BadRequestException("Choose an access scope.");
    const scope_type = type as Scope["scope_type"];
    if (
      !Array.isArray(body.scope_ids) ||
      body.scope_ids.length > 200 ||
      body.scope_ids.some((id) => typeof id !== "string")
    )
      throw new BadRequestException("Choose up to 200 scope records.");
    const ids = [...new Set((body.scope_ids as string[]).map(uuid))];
    if (
      (scope_type === "organisation" && ids.length) ||
      (scope_type !== "organisation" && !ids.length)
    )
      throw new BadRequestException(
        "Choose records for limited access, or clear them for organisation-wide access.",
      );
    if (
      scope_type !== "organisation" &&
      (role.protected ||
        role.permissions.some((p) => widePermissions.includes(p)))
    )
      throw new BadRequestException(
        "This role contains organisation-wide permissions. Choose organisation-wide access or a more limited role.",
      );
    if (scope_type !== "organisation") {
      const table = scope_type === "centres" ? "centres" : "learning_groups";
      const found = await sql.query(
        `SELECT id FROM ${table} WHERE organisation_id=$1 AND id=ANY($2::uuid[]) AND NOT archived`,
        [org, ids],
      );
      if (found.rows.length !== ids.length)
        throw new BadRequestException(
          "Scope records must be active and belong to this organisation.",
        );
    }
    if (
      body.status !== undefined &&
      !["active", "suspended"].includes(String(body.status))
    )
      throw new BadRequestException("Invalid membership status.");
    return {
      role_id: roleId,
      scope_type,
      scope_ids: ids,
      status: (body.status || "active") as Grant["status"],
      role,
    };
  }
  async members(user: Account, org: string) {
    await this.require(user, org, "members.view");
    return (
      await this.db.query(
        `SELECT m.user_id,m.role_id,m.status,m.scope_type,m.scope_ids,u.name,u.email,r.name AS role_name,r.protected FROM memberships m JOIN users u ON u.id=m.user_id JOIN access_roles r ON r.id=m.role_id AND r.organisation_id=m.organisation_id WHERE m.organisation_id=$1 ORDER BY u.name`,
        [org],
      )
    ).rows;
  }
  async saveMember(
    user: Account,
    org: string,
    target: string,
    body: Record<string, unknown>,
  ) {
    uuid(target);
    return this.db.transaction(async (sql) => {
      await this.lock(sql, org);
      const access = await this.require(user, org, "members.manage", sql);
      if (target === user.id)
        throw new ForbiddenException(
          "Another administrator must change your access.",
        );
      const before = (
        await sql.query<
          Grant & { protected: boolean; permissions: Permission[] }
        >(
          `SELECT m.*,r.protected,r.permissions FROM memberships m JOIN access_roles r ON r.id=m.role_id WHERE m.organisation_id=$1 AND m.user_id=$2`,
          [org, target],
        )
      ).rows[0];
      if (!before) throw new NotFoundException("Member not found.");
      this.subset(access, before);
      const next = await this.validateGrant(sql, org, body, access);
      if (
        before.protected &&
        before.status === "active" &&
        (!next.role.protected || next.status !== "active")
      ) {
        const owners = await sql.query(
          "SELECT m.user_id FROM memberships m JOIN access_roles r ON r.id=m.role_id WHERE m.organisation_id=$1 AND m.status=$2 AND r.protected",
          [org, "active"],
        );
        if (owners.rows.length <= 1)
          throw new ConflictException(
            "Keep at least one active organisation admin.",
          );
      }
      await sql.query(
        "UPDATE memberships SET role_id=$3,role=$4,scope_type=$5,scope_ids=$6,status=$7 WHERE organisation_id=$1 AND user_id=$2",
        [
          org,
          target,
          next.role_id,
          next.role.protected ? "organisation_admin" : "member",
          next.scope_type,
          next.scope_ids,
          next.status,
        ],
      );
      // Pending invites cannot later restore access that an administrator has just changed.
      await sql.query(
        "DELETE FROM invitations WHERE organisation_id=$1 AND email=(SELECT email FROM users WHERE id=$2) AND accepted_at IS NULL",
        [org, target],
      );
      await this.audit(sql, user, org, "membership.updated", {
        userId: target,
        before: {
          role_id: before.role_id,
          status: before.status,
          scope_type: before.scope_type,
          scope_ids: before.scope_ids,
        },
        after: {
          role_id: next.role_id,
          status: next.status,
          scope_type: next.scope_type,
          scope_ids: next.scope_ids,
        },
      });
      return { ok: true };
    });
  }
  async issue(
    sql: SqlClient,
    user: Account,
    org: string,
    email: string,
    body?: Record<string, unknown>,
  ) {
    const access = await this.require(user, org, "members.manage", sql);
    const ownerRole = (
      await sql.query<AccessRole>(
        "SELECT * FROM access_roles WHERE organisation_id=$1 AND protected",
        [org],
      )
    ).rows[0];
    const grant = await this.validateGrant(
      sql,
      org,
      body || {
        role_id: ownerRole.id,
        scope_type: "organisation",
        scope_ids: [],
      },
      access,
    );
    if (grant.status !== "active")
      throw new BadRequestException("Invitations must grant active access.");
    if (
      (
        await sql.query(
          "SELECT 1 FROM memberships m JOIN users u ON u.id=m.user_id WHERE m.organisation_id=$1 AND u.email=$2",
          [org, email],
        )
      ).rows.length
    )
      throw new ConflictException(
        "This person already belongs to the organisation. Update their staff access instead.",
      );
    await sql.query(
      "DELETE FROM invitations WHERE organisation_id=$1 AND email=$2 AND accepted_at IS NULL",
      [org, email],
    );
    const raw = token();
    const result = await sql.query<{ expires_at: Date }>(
      "INSERT INTO invitations(id,organisation_id,email,token_hash,expires_at,created_by,role_id,scope_type,scope_ids) VALUES ($1,$2,$3,$4,now()+interval '3 days',$5,$6,$7,$8) RETURNING expires_at",
      [
        randomUUID(),
        org,
        email,
        digest(raw),
        user.id,
        grant.role_id,
        grant.scope_type,
        grant.scope_ids,
      ],
    );
    await this.audit(sql, user, org, "invitation.created", {
      email,
      role_id: grant.role_id,
      scope_type: grant.scope_type,
      scope_ids: grant.scope_ids,
    });
    return { token: raw, email, expiresAt: result.rows[0].expires_at };
  }
  async invite(user: Account, org: string, body: Record<string, unknown>) {
    return this.db.transaction(async (sql) => {
      await this.lock(sql, org);
      return this.issue(
        sql,
        user,
        org,
        emailValue(body.email),
        body.role_id ? body : undefined,
      );
    });
  }
  async history(user: Account, org: string, offset = 0, exporting = false) {
    await this.require(user, org, exporting ? "audit.export" : "audit.view");
    if (!Number.isSafeInteger(offset) || offset < 0)
      throw new BadRequestException("Invalid history page.");
    if (exporting)
      await this.audit(this.db, user, org, "audit.exported", {
        offset,
        limit: 50,
      });
    return (
      await this.db.query(
        `SELECT a.id,a.action,a.details,a.created_at,u.name AS actor_name FROM audit_events a LEFT JOIN users u ON u.id=a.actor_id WHERE a.organisation_id=$1 ORDER BY a.created_at DESC,a.id LIMIT 50 OFFSET $2`,
        [org, offset],
      )
    ).rows;
  }
}
