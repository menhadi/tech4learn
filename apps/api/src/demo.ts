import { randomBytes, randomUUID } from "node:crypto";
import type { Database, SqlClient } from "./database.js";
import { templates, type Permission, type Scope } from "./access-model.js";
import { digest, hashPassword, token, uuid } from "./security.js";

type Store = Pick<Database, "query" | "transaction">;
interface Manifest {
  organisationIds: string[];
  accounts: { id: string; email: string }[];
  invitationEmails: string[];
}
const lock = (sql: SqlClient) =>
  sql.query("SELECT pg_advisory_xact_lock(74041003)");

/** Explicit CLI operation only. No HTTP route or automatic startup seeding. */
export async function createDemo(db: Store) {
  return db.transaction(async (sql) => {
    await lock(sql);
    if (
      !(await sql.query("SELECT version FROM schema_versions WHERE version=2"))
        .rows.length
    )
      throw new Error("Apply migration 2 first.");
    const existing = await sql.query<{ id: string }>(
      "SELECT id FROM audit_events WHERE action='demo.dataset_created'",
    );
    if (existing.rows.length)
      throw new Error(
        `Demo already exists: ${existing.rows[0].id}. Use demo-inspect; existing passwords and records were not changed.`,
      );
    const owner = (
      await sql.query<{ id: string }>(
        "SELECT id FROM users WHERE is_superadmin ORDER BY created_at,id LIMIT 1",
      )
    ).rows[0];
    if (!owner) throw new Error("Create the real platform superadmin first.");
    const datasetId = randomUUID(),
      suffix = datasetId.slice(0, 8),
      organisationIds = [randomUUID(), randomUUID()];
    const manifest: Manifest = {
      organisationIds,
      accounts: [],
      invitationEmails: [],
    };
    const accounts: {
      name: string;
      email: string;
      password: string;
      organisation: string;
      role: string;
      scope: string;
      status: string;
    }[] = [];
    const organisations: { id: string; name: string; slug: string }[] = [];
    const roleMaps: Map<string, string>[] = [];
    for (const [index, id] of organisationIds.entries()) {
      const name =
        index === 0
          ? "DEMO — Community Learning NGO"
          : "DEMO — Skills Coaching Academy";
      const slug = `demo-${suffix}-${index === 0 ? "ngo" : "coaching"}`;
      await sql.query(
        "INSERT INTO organisations(id,name,slug,colour,centre_label) VALUES ($1,$2,$3,$4,$5)",
        [
          id,
          name,
          slug,
          index === 0 ? "#175d50" : "#3758a0",
          index === 0 ? "Learning centre" : "Branch",
        ],
      );
      organisations.push({ id, name, slug });
      const roles = new Map<string, string>();
      roleMaps.push(roles);
      for (const r of templates) {
        const roleId = randomUUID();
        roles.set(r.name, roleId);
        await sql.query(
          "INSERT INTO access_roles(id,organisation_id,name,permissions,protected) VALUES ($1,$2,$3,$4,$5)",
          [roleId, id, r.name, r.permissions, r.protected],
        );
      }
    }
    const org = organisationIds[0],
      other = organisationIds[1];
    const auditRole = randomUUID();
    roleMaps[0].set("Demo report reviewer", auditRole);
    const permissions: Permission[] = [
      "organisation.view",
      "centres.view",
      "groups.view",
      "audit.view",
      "audit.export",
    ];
    await sql.query(
      "INSERT INTO access_roles(id,organisation_id,name,permissions) VALUES ($1,$2,$3,$4)",
      [auditRole, org, "Demo report reviewer", permissions],
    );
    const centreIds: string[] = [];
    const centreSpecs = [
      {
        org,
        name: "DEMO North — approved location",
        latitude: 22.5,
        longitude: 88.3,
        radius: 150,
        approved: true,
        archived: false,
      },
      {
        org,
        name: "DEMO South — location needs approval",
        latitude: 22.6,
        longitude: 88.4,
        radius: 100,
        approved: false,
        archived: false,
      },
      {
        org,
        name: "DEMO Remote — coordinates missing",
        latitude: null,
        longitude: null,
        radius: 200,
        approved: false,
        archived: false,
      },
      {
        org,
        name: "DEMO Old Centre — archived",
        latitude: null,
        longitude: null,
        radius: 100,
        approved: false,
        archived: true,
      },
      {
        org: other,
        name: "DEMO Academy Branch",
        latitude: null,
        longitude: null,
        radius: 100,
        approved: false,
        archived: false,
      },
    ];
    for (const c of centreSpecs) {
      const id = randomUUID();
      centreIds.push(id);
      await sql.query(
        "INSERT INTO centres(id,organisation_id,name,address,latitude,longitude,radius,location_approved,archived) VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9)",
        [
          id,
          c.org,
          c.name,
          "Synthetic example only — not an actual teaching site",
          c.latitude,
          c.longitude,
          c.radius,
          c.approved,
          c.archived,
        ],
      );
    }
    const groupIds: string[] = [];
    const groupSpecs = [
      ["DEMO North — Morning", 0, false],
      ["DEMO North — Evening", 0, false],
      ["DEMO South — Reading", 1, false],
      ["DEMO Remote — Mixed group", 2, false],
      ["DEMO Previous batch — archived", 3, true],
      ["DEMO Academy — Foundation", 4, false],
    ] as const;
    for (const [name, centreIndex, archived] of groupSpecs) {
      const id = randomUUID();
      groupIds.push(id);
      await sql.query(
        "INSERT INTO learning_groups(id,organisation_id,centre_id,name,archived) VALUES ($1,$2,$3,$4,$5)",
        [
          id,
          centreSpecs[centreIndex].org,
          centreIds[centreIndex],
          name,
          archived,
        ],
      );
    }
    const specs: {
      key: string;
      role: string;
      orgIndex: number;
      scope: Scope;
      description: string;
      status?: string;
    }[] = [
      {
        key: "admin",
        role: "Organisation admin",
        orgIndex: 0,
        scope: { scope_type: "organisation", scope_ids: [] },
        description: "Whole NGO; full administration",
      },
      {
        key: "backup-admin",
        role: "Organisation admin",
        orgIndex: 0,
        scope: { scope_type: "organisation", scope_ids: [] },
        description:
          "Second admin for testing role changes and last-admin protection",
      },
      {
        key: "manager",
        role: "Programme manager",
        orgIndex: 0,
        scope: { scope_type: "organisation", scope_ids: [] },
        description: "All NGO centres/groups; no staff or role administration",
      },
      {
        key: "coordinator",
        role: "Centre coordinator",
        orgIndex: 0,
        scope: { scope_type: "centres", scope_ids: [centreIds[0]] },
        description: "North centre and its two groups only",
      },
      {
        key: "teacher",
        role: "Teacher",
        orgIndex: 0,
        scope: { scope_type: "groups", scope_ids: [groupIds[0]] },
        description: "View North Morning group only",
      },
      {
        key: "assessor",
        role: "Assessor",
        orgIndex: 0,
        scope: { scope_type: "organisation", scope_ids: [] },
        description:
          "View all NGO centres/groups; assessment module is not built",
      },
      {
        key: "viewer",
        role: "Viewer",
        orgIndex: 0,
        scope: { scope_type: "centres", scope_ids: [centreIds[1]] },
        description: "View South centre/group only",
      },
      {
        key: "reviewer",
        role: "Demo report reviewer",
        orgIndex: 0,
        scope: { scope_type: "organisation", scope_ids: [] },
        description: "View records and export history; no editing",
      },
      {
        key: "suspended",
        role: "Viewer",
        orgIndex: 0,
        scope: { scope_type: "organisation", scope_ids: [] },
        description:
          "Can sign in, but no organisation access until reactivated",
        status: "suspended",
      },
      {
        key: "academy-admin",
        role: "Organisation admin",
        orgIndex: 1,
        scope: { scope_type: "organisation", scope_ids: [] },
        description: "Coaching organisation only; cannot access NGO",
      },
    ];
    for (const s of specs) {
      const id = randomUUID(),
        email = `demo-${suffix}-${s.key}@example.test`,
        password = randomBytes(24).toString("base64url"),
        name = `DEMO ${s.key}`;
      await sql.query(
        "INSERT INTO users(id,email,name,password_hash) VALUES ($1,$2,$3,$4)",
        [id, email, name, await hashPassword(password)],
      );
      manifest.accounts.push({ id, email });
      await sql.query(
        "INSERT INTO memberships(user_id,organisation_id,role,role_id,scope_type,scope_ids,status) VALUES ($1,$2,$3,$4,$5,$6,$7)",
        [
          id,
          organisationIds[s.orgIndex],
          s.role === "Organisation admin" ? "organisation_admin" : "member",
          roleMaps[s.orgIndex].get(s.role),
          s.scope.scope_type,
          s.scope.scope_ids,
          s.status || "active",
        ],
      );
      accounts.push({
        name,
        email,
        password,
        organisation: organisations[s.orgIndex].name,
        role: s.role,
        scope: s.description,
        status: s.status || "active",
      });
    }
    const invitations: { email: string; token: string; state: string }[] = [];
    for (const state of ["pending", "expired"]) {
      const email = `demo-${suffix}-${state}@example.test`,
        raw = token();
      manifest.invitationEmails.push(email);
      if (
        (await sql.query("SELECT id FROM users WHERE email=$1", [email])).rows
          .length
      )
        throw new Error("Reserved demo email already exists. Nothing created.");
      await sql.query(
        "INSERT INTO invitations(id,organisation_id,email,token_hash,expires_at,created_by,role_id,scope_type,scope_ids) VALUES ($1,$2,$3,$4,now()+($5::integer * interval '1 minute'),$6,$7,'groups',$8)",
        [
          randomUUID(),
          org,
          email,
          digest(raw),
          state === "pending" ? 4320 : -1,
          manifest.accounts[0].id,
          roleMaps[0].get("Viewer"),
          [groupIds[0]],
        ],
      );
      invitations.push({ email, token: raw, state });
    }
    // Clearly labelled seed events, not fabricated records of human actions.
    for (const [index, id] of organisationIds.entries())
      await sql.query(
        "INSERT INTO audit_events(id,actor_id,organisation_id,action,details) VALUES ($1,$2,$3,$4,$5)",
        [
          randomUUID(),
          owner.id,
          id,
          "demo.records_seeded",
          JSON.stringify({
            datasetId,
            synthetic: true,
            centres: centreSpecs.filter((c) => c.org === id).length,
            groups: index === 0 ? 5 : 1,
            note: "Example location approvals and archived records were seeded for testing.",
          }),
        ],
      );
    await sql.query(
      "INSERT INTO audit_events(id,actor_id,action,details) VALUES ($1,$2,$3,$4)",
      [datasetId, owner.id, "demo.dataset_created", JSON.stringify(manifest)],
    );
    return {
      datasetId,
      organisations,
      accounts,
      invitations,
      note: "Synthetic foundation data only. Do not put real records in these demo organisations. Save passwords privately; they are shown only once.",
    };
  });
}

async function getManifest(sql: SqlClient, id: string) {
  uuid(id);
  const row = (
    await sql.query<{ details: Manifest }>(
      "SELECT details FROM audit_events WHERE id=$1 AND action='demo.dataset_created' AND organisation_id IS NULL FOR UPDATE",
      [id],
    )
  ).rows[0];
  if (!row) throw new Error("Active demo dataset not found. Nothing changed.");
  return row.details;
}
export async function inspectDemo(db: Store, id: string) {
  return db.transaction(async (sql) => {
    await lock(sql);
    const m = await getManifest(sql, id);
    const counts: Record<string, number> = {};
    for (const table of [
      "learners",
      "organisations",
      "centres",
      "learning_groups",
      "memberships",
      "invitations",
      "access_roles",
      "audit_events",
    ]) {
      const column = table === "organisations" ? "id" : "organisation_id";
      counts[table] = Number(
        (
          await sql.query<{ count: string }>(
            `SELECT count(*) FROM ${table} WHERE ${column}=ANY($1::uuid[])`,
            [m.organisationIds],
          )
        ).rows[0].count,
      );
    }
    return {
      datasetId: id,
      organisations: (
        await sql.query(
          "SELECT id,name,slug FROM organisations WHERE id=ANY($1::uuid[])",
          [m.organisationIds],
        )
      ).rows,
      counts,
      note: "Removal deletes all records inside these two demo organisations, including records added during testing. Other organisations are not selected.",
    };
  });
}

export async function seedDemoLearners(db: Store, id: string) {
  return db.transaction(async (sql) => {
    await lock(sql);
    const m = await getManifest(sql, id);
    if (
      !(await sql.query("SELECT version FROM schema_versions WHERE version=3"))
        .rows.length
    )
      throw new Error("Apply learner migration 3 first.");
    if (
      (
        await sql.query(
          "SELECT id FROM audit_events WHERE action='demo.learners_seeded' AND details->>'datasetId'=$1",
          [id],
        )
      ).rows.length
    )
      throw new Error("Demo learners already exist; nothing was reset.");
    await sql.query(
      "SELECT id FROM organisations WHERE id=ANY($1::uuid[]) ORDER BY id FOR UPDATE",
      [m.organisationIds],
    );
    let count = 0;
    for (const org of m.organisationIds) {
      await sql.query(
        "INSERT INTO learner_fields(id,organisation_id,key,label,kind,options) VALUES ($1,$2,'demo_language','DEMO language','choice',$3)",
        [randomUUID(), org, JSON.stringify(["Hindi", "English"])],
      );
      await sql.query(
        "UPDATE access_roles SET permissions=ARRAY(SELECT DISTINCT p FROM unnest(permissions||ARRAY['learners.view','fields.view']::text[]) p) WHERE organisation_id=$1 AND 'groups.view'=ANY(permissions)",
        [org],
      );
      const groups = (
        await sql.query<{ id: string }>(
          "SELECT id FROM learning_groups WHERE organisation_id=$1 AND NOT archived ORDER BY id",
          [org],
        )
      ).rows;
      for (const g of groups)
        for (let n = 1; n <= 3; n++) {
          const learnerId = randomUUID();
          count++;
          await sql.query(
            "INSERT INTO learners(id,organisation_id,code,name,age,class_label,group_id,custom_values,demo) VALUES ($1,$2,$3,$4,$5,$6,$7,$8,true)",
            [
              learnerId,
              org,
              `DEMO-L-${String(count).padStart(3, "0")}`,
              `DEMO Learner ${String(count).padStart(3, "0")}`,
              6 + n,
              `Level ${n}`,
              g.id,
              JSON.stringify({ demo_language: n === 3 ? "English" : "Hindi" }),
            ],
          );
          await sql.query(
            "INSERT INTO learner_enrolments(id,organisation_id,learner_id,group_id,reason) VALUES ($1,$2,$3,$4,$5)",
            [randomUUID(), org, learnerId, g.id, "Synthetic demo enrolment"],
          );
        }
      await sql.query(
        "INSERT INTO audit_events(id,organisation_id,action,details) VALUES ($1,$2,'demo.learners_seeded',$3)",
        [randomUUID(), org, JSON.stringify({ datasetId: id, synthetic: true })],
      );
    }
    return {
      datasetId: id,
      count,
      note: "Synthetic learners added to the recorded demo organisations. Existing staff scopes/passwords were preserved.",
    };
  });
}

export async function removeDemo(db: Store, id: string) {
  return db.transaction(async (sql) => {
    await lock(sql);
    const m = await getManifest(sql, id);
    // Same ordering as organisation mutations; all changes below commit or roll back together.
    await sql.query(
      "SELECT id FROM organisations WHERE id=ANY($1::uuid[]) ORDER BY id FOR UPDATE",
      [m.organisationIds],
    );
    const invited = (
      await sql.query<{ id: string; email: string }>(
        "SELECT id,email FROM users WHERE email=ANY($1::text[])",
        [m.invitationEmails],
      )
    ).rows;
    const accounts = [...m.accounts, ...invited],
      ids = accounts.map((a) => a.id);
    await sql.query(
      "SELECT id FROM users WHERE id=ANY($1::uuid[]) ORDER BY id FOR UPDATE",
      [ids],
    );
    if (
      (
        await sql.query(
          "SELECT id FROM users WHERE id=ANY($1::uuid[]) AND is_superadmin",
          [ids],
        )
      ).rows.length
    )
      throw new Error(
        "A demo account is now a platform superadmin. Removal refused.",
      );
    // A demo identity must not be removed if it has been used outside the sandbox.
    const external = await sql.query(
      `SELECT user_id FROM memberships WHERE user_id=ANY($1::uuid[]) AND NOT(organisation_id=ANY($2::uuid[])) UNION ALL SELECT created_by FROM invitations WHERE created_by=ANY($1::uuid[]) AND NOT(organisation_id=ANY($2::uuid[])) UNION ALL SELECT actor_id FROM audit_events WHERE actor_id=ANY($1::uuid[]) AND organisation_id IS NOT NULL AND NOT(organisation_id=ANY($2::uuid[]))`,
      [ids, m.organisationIds],
    );
    if (external.rows.length)
      throw new Error(
        "A demo account has records in another organisation. Removal refused; review its external use first.",
      );
    const outsideInvites = await sql.query(
      "SELECT id FROM invitations WHERE email=ANY($1::text[]) AND NOT(organisation_id=ANY($2::uuid[]))",
      [accounts.map((a) => a.email), m.organisationIds],
    );
    if (outsideInvites.rows.length)
      throw new Error(
        "A demo account has an invitation in another organisation. Removal refused.",
      );
    if (
      (await sql.query("SELECT version FROM schema_versions WHERE version=5"))
        .rows.length
    )
      await sql.query(
        "DELETE FROM attendance_sessions WHERE organisation_id=ANY($1::uuid[])",
        [m.organisationIds],
      );
    if (
      (await sql.query("SELECT version FROM schema_versions WHERE version=3"))
        .rows.length
    ) {
      await sql.query(
        "DELETE FROM learner_imports WHERE organisation_id=ANY($1::uuid[])",
        [m.organisationIds],
      );
      await sql.query(
        "DELETE FROM learner_enrolments WHERE organisation_id=ANY($1::uuid[])",
        [m.organisationIds],
      );
      await sql.query(
        "DELETE FROM learners WHERE organisation_id=ANY($1::uuid[])",
        [m.organisationIds],
      );
      await sql.query(
        "DELETE FROM custom_fields WHERE organisation_id=ANY($1::uuid[])",
        [m.organisationIds],
      );
    }
    await sql.query(
      "DELETE FROM invitations WHERE organisation_id=ANY($1::uuid[])",
      [m.organisationIds],
    );
    await sql.query(
      "DELETE FROM memberships WHERE organisation_id=ANY($1::uuid[])",
      [m.organisationIds],
    );
    await sql.query(
      "DELETE FROM learning_groups WHERE organisation_id=ANY($1::uuid[])",
      [m.organisationIds],
    );
    await sql.query(
      "DELETE FROM centres WHERE organisation_id=ANY($1::uuid[])",
      [m.organisationIds],
    );
    await sql.query(
      "DELETE FROM access_roles WHERE organisation_id=ANY($1::uuid[])",
      [m.organisationIds],
    );
    await sql.query(
      "DELETE FROM audit_events WHERE organisation_id=ANY($1::uuid[])",
      [m.organisationIds],
    );
    await sql.query("DELETE FROM organisations WHERE id=ANY($1::uuid[])", [
      m.organisationIds,
    ]);
    await sql.query("DELETE FROM sessions WHERE user_id=ANY($1::uuid[])", [
      ids,
    ]);
    await sql.query(
      "UPDATE audit_events SET actor_id=NULL WHERE actor_id=ANY($1::uuid[])",
      [ids],
    );
    const limitKeys = accounts.flatMap((a) => [
      `password:${a.id}`,
      `login:${a.email}`,
    ]);
    await sql.query("DELETE FROM auth_limits WHERE key=ANY($1::text[])", [
      limitKeys,
    ]);
    await sql.query("DELETE FROM users WHERE id=ANY($1::uuid[])", [ids]);
    await sql.query(
      "UPDATE audit_events SET action='demo.dataset_removed',details=$2 WHERE id=$1",
      [id, JSON.stringify({ ...m, removedAt: new Date().toISOString() })],
    );
    return {
      ok: true,
      datasetId: id,
      note: "The recorded demo organisations and demo accounts were removed. The platform removal record was retained.",
    };
  });
}
