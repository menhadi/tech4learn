import {
  BadRequestException,
  ConflictException,
  Injectable,
  NotFoundException,
  ForbiddenException,
} from "@nestjs/common";
import { randomUUID } from "node:crypto";
import { Database, type SqlClient } from "./database.js";
import { AccessService } from "./access.service.js";
import type { Account } from "./identity.service.js";
import type { Access } from "./access-model.js";
import { field, uuid } from "./security.js";
interface Centre {
  id: string;
  organisation_id: string;
  name: string;
  address: string;
  latitude: number | null;
  longitude: number | null;
  radius: number;
  location_approved: boolean;
  archived: boolean;
}
interface Group {
  id: string;
  organisation_id: string;
  centre_id: string;
  name: string;
  archived: boolean;
}
@Injectable()
export class RecordsService {
  constructor(
    private readonly db: Database,
    private readonly access: AccessService,
  ) {}
  private async centreAllowed(
    sql: SqlClient,
    org: string,
    id: string,
    access: Access,
    write = false,
  ) {
    uuid(id);
    const centre = (
      await sql.query<Centre>(
        "SELECT * FROM centres WHERE organisation_id=$1 AND id=$2",
        [org, id],
      )
    ).rows[0];
    let allowed =
      access.scope_type === "organisation" ||
      (access.scope_type === "centres" && access.scope_ids.includes(id));
    if (!write && access.scope_type === "groups")
      allowed =
        (
          await sql.query(
            "SELECT id FROM learning_groups WHERE organisation_id=$1 AND centre_id=$2 AND id=ANY($3::uuid[])",
            [org, id, access.scope_ids],
          )
        ).rows.length > 0;
    if (!centre || !allowed)
      throw new NotFoundException("Centre not found in your access scope.");
    return centre;
  }
  async centres(user: Account, org: string) {
    const a = await this.access.require(user, org, "centres.view");
    return (
      await this.db.query<Centre>(
        `SELECT c.* FROM centres c WHERE organisation_id=$1 AND ($2='organisation' OR ($2='centres' AND c.id=ANY($3::uuid[])) OR ($2='groups' AND EXISTS (SELECT 1 FROM learning_groups g WHERE g.organisation_id=c.organisation_id AND g.centre_id=c.id AND g.id=ANY($3::uuid[])))) ORDER BY c.archived,c.name`,
        [org, a.scope_type, a.scope_ids],
      )
    ).rows;
  }
  async saveCentre(
    user: Account,
    org: string,
    body: Record<string, unknown>,
    id?: string,
  ) {
    const name = field(body.name, "Centre name");
    if (typeof body.address !== "string" || body.address.length > 500)
      throw new BadRequestException(
        "Address must be text of up to 500 characters.",
      );
    const address = body.address.trim();
    const number = (
      value: unknown,
      min: number,
      max: number,
      label: string,
      nullable = false,
    ) => {
      if (nullable && (value === null || value === "")) return null;
      if (
        typeof value !== "number" ||
        !Number.isFinite(value) ||
        value < min ||
        value > max
      )
        throw new BadRequestException(`Invalid ${label}.`);
      return value;
    };
    const lat = number(body.latitude, -90, 90, "latitude", true),
      lon = number(body.longitude, -180, 180, "longitude", true),
      radius = number(body.radius, 10, 10000, "radius");
    if ((lat === null) !== (lon === null) || !Number.isInteger(radius))
      throw new BadRequestException(
        "Provide both coordinates and a whole-number radius in metres.",
      );
    return this.db.transaction(async (sql) => {
      await this.access.lock(sql, org);
      const a = await this.access.require(
        user,
        org,
        id ? "centres.edit" : "centres.create",
        sql,
      );
      const before = id
        ? await this.centreAllowed(sql, org, id, a, true)
        : null;
      if (before?.archived)
        throw new ConflictException("Archived centres cannot be edited.");
      const approved =
        !!before?.location_approved &&
        before.latitude === lat &&
        before.longitude === lon &&
        before.radius === radius;
      const row = id
        ? (
            await sql.query<Centre>(
              "UPDATE centres SET name=$3,address=$4,latitude=$5,longitude=$6,radius=$7,location_approved=$8 WHERE organisation_id=$1 AND id=$2 RETURNING *",
              [org, id, name, address, lat, lon, radius, approved],
            )
          ).rows[0]
        : (
            await sql.query<Centre>(
              "INSERT INTO centres(id,organisation_id,name,address,latitude,longitude,radius) VALUES ($1,$2,$3,$4,$5,$6,$7) RETURNING *",
              [randomUUID(), org, name, address, lat, lon, radius],
            )
          ).rows[0];
      await this.access.audit(
        sql,
        user,
        org,
        id ? "centre.updated" : "centre.created",
        { centreId: row.id, before, after: row },
      );
      return row;
    });
  }
  async centreAction(
    user: Account,
    org: string,
    id: string,
    action: "archive" | "approve",
  ) {
    return this.db.transaction(async (sql) => {
      await this.access.lock(sql, org);
      const a = await this.access.require(
        user,
        org,
        action === "archive" ? "centres.archive" : "centres.approve",
        sql,
      );
      const centre = await this.centreAllowed(sql, org, id, a, true);
      if (centre.archived)
        throw new ConflictException("Centre is already archived.");
      if (action === "approve" && centre.latitude === null)
        throw new BadRequestException(
          "Add coordinates before approving the location.",
        );
      if (
        action === "archive" &&
        (
          await sql.query(
            "SELECT id FROM learning_groups WHERE organisation_id=$1 AND centre_id=$2 AND NOT archived",
            [org, id],
          )
        ).rows.length
      )
        throw new ConflictException(
          "Archive active groups in this centre first.",
        );
      await sql.query(
        action === "archive"
          ? "UPDATE centres SET archived=true WHERE organisation_id=$1 AND id=$2"
          : "UPDATE centres SET location_approved=true WHERE organisation_id=$1 AND id=$2",
        [org, id],
      );
      await this.access.audit(
        sql,
        user,
        org,
        `centre.${action === "archive" ? "archived" : "location_approved"}`,
        {
          centreId: id,
          latitude: centre.latitude,
          longitude: centre.longitude,
          radius: centre.radius,
        },
      );
      return { ok: true };
    });
  }
  async groups(user: Account, org: string) {
    const a = await this.access.require(user, org, "groups.view");
    return (
      await this.db.query<Group>(
        `SELECT g.*,c.name AS centre_name FROM learning_groups g JOIN centres c ON c.id=g.centre_id AND c.organisation_id=g.organisation_id WHERE g.organisation_id=$1 AND ($2='organisation' OR ($2='centres' AND g.centre_id=ANY($3::uuid[])) OR ($2='groups' AND g.id=ANY($3::uuid[]))) ORDER BY g.archived,g.name`,
        [org, a.scope_type, a.scope_ids],
      )
    ).rows;
  }
  private async groupAllowed(
    sql: SqlClient,
    org: string,
    id: string,
    a: Access,
  ) {
    const row = (
      await sql.query<Group>(
        "SELECT * FROM learning_groups WHERE organisation_id=$1 AND id=$2",
        [org, uuid(id)],
      )
    ).rows[0];
    if (
      !row ||
      !(
        a.scope_type === "organisation" ||
        (a.scope_type === "centres" && a.scope_ids.includes(row.centre_id)) ||
        (a.scope_type === "groups" && a.scope_ids.includes(id))
      )
    )
      throw new NotFoundException("Group not found in your access scope.");
    return row;
  }
  async saveGroup(
    user: Account,
    org: string,
    body: Record<string, unknown>,
    id?: string,
  ) {
    const name = field(body.name, "Group name");
    return this.db.transaction(async (sql) => {
      await this.access.lock(sql, org);
      const a = await this.access.require(
        user,
        org,
        id ? "groups.edit" : "groups.create",
        sql,
      );
      if (!id && a.scope_type === "groups")
        throw new ForbiddenException(
          "Group-scoped access cannot create additional groups.",
        );
      const before = id ? await this.groupAllowed(sql, org, id, a) : null;
      if (before?.archived)
        throw new ConflictException("Archived groups cannot be edited.");
      // Moving groups can silently expand another person's scope. It is intentionally unsupported.
      const centreId =
        before?.centre_id || uuid(field(body.centre_id, "Centre", 36));
      if (before && body.centre_id !== undefined && body.centre_id !== centreId)
        throw new BadRequestException(
          "Groups cannot be moved between centres.",
        );
      const centre = await this.centreAllowed(sql, org, centreId, a);
      if (centre.archived)
        throw new ConflictException("Choose an active centre.");
      const row = id
        ? (
            await sql.query<Group>(
              "UPDATE learning_groups SET name=$3 WHERE organisation_id=$1 AND id=$2 RETURNING *",
              [org, id, name],
            )
          ).rows[0]
        : (
            await sql.query<Group>(
              "INSERT INTO learning_groups(id,organisation_id,centre_id,name) VALUES ($1,$2,$3,$4) RETURNING *",
              [randomUUID(), org, centreId, name],
            )
          ).rows[0];
      await this.access.audit(
        sql,
        user,
        org,
        id ? "group.updated" : "group.created",
        { groupId: row.id, before, after: row },
      );
      return row;
    });
  }
  async archiveGroup(user: Account, org: string, id: string) {
    return this.db.transaction(async (sql) => {
      await this.access.lock(sql, org);
      const a = await this.access.require(user, org, "groups.archive", sql);
      const row = await this.groupAllowed(sql, org, id, a);
      if (row.archived)
        throw new ConflictException("Group is already archived.");
      if (
        (
          await sql.query(
            "SELECT id FROM learners WHERE organisation_id=$1 AND group_id=$2 AND NOT archived",
            [org, id],
          )
        ).rows.length
      )
        throw new ConflictException(
          "Transfer or archive active learners before archiving their group.",
        );
      await sql.query(
        "UPDATE learning_groups SET archived=true WHERE organisation_id=$1 AND id=$2",
        [org, id],
      );
      await this.access.audit(sql, user, org, "group.archived", {
        groupId: id,
      });
      return { ok: true };
    });
  }
}
