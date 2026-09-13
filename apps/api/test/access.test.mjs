import { academicMigration } from "../dist/migration-academic.js";
import { configurationMigration } from "../dist/migration-configuration.js";
import { learnerMigration } from "../dist/migration-learners.js";
import { test } from "node:test";
import assert from "node:assert/strict";
import { randomUUID } from "node:crypto";
import { PGlite } from "@electric-sql/pglite";
import { createApp } from "../dist/bootstrap.js";
import { migration } from "../dist/schema.js";
import { accessMigration } from "../dist/migration-access.js";
import { digest, hashPassword } from "../dist/security.js";

test("role upgrade, delegation and location scopes through authenticated HTTP", async (t) => {
  const pg = new PGlite();
  await pg.exec(migration);
  const ownerId = randomUUID(),
    adminId = randomUUID(),
    orgId = randomUUID(),
    legacyToken = "a".repeat(64),
    password = "synthetic access test passphrase";
  const hash = await hashPassword(password);
  await pg.query(
    "INSERT INTO users(id,email,name,password_hash,is_superadmin) VALUES ($1,$2,$3,$4,true),($5,$6,$7,$4,false)",
    [
      ownerId,
      "owner@access.test",
      "Owner",
      hash,
      adminId,
      "admin@access.test",
      "Admin",
    ],
  );
  await pg.query("INSERT INTO organisations(id,name,slug) VALUES ($1,$2,$3)", [
    orgId,
    "Legacy organisation",
    "legacy",
  ]);
  await pg.query(
    "INSERT INTO memberships(user_id,organisation_id,role) VALUES ($1,$2,'organisation_admin')",
    [adminId, orgId],
  );
  await pg.query(
    "INSERT INTO invitations(id,organisation_id,email,token_hash,expires_at,created_by) VALUES ($1,$2,$3,$4,now()+interval '1 day',$5)",
    [randomUUID(), orgId, "legacy@access.test", digest(legacyToken), ownerId],
  );
  await pg.exec(accessMigration);
  await pg.exec(learnerMigration);
  await pg.exec(configurationMigration);
  await pg.exec(academicMigration);
  const adapter = {
    query: (s, p) => pg.query(s, p),
    transaction: (run) =>
      pg.transaction((c) =>
        run({
          query: (s, p) =>
            p?.length ? c.query(s, p) : c.exec(s).then((r) => r.at(-1)),
        }),
      ),
    onModuleDestroy: async () => {},
  };
  const app = await createApp(undefined, adapter);
  app.useLogger(false);
  await app.listen(0, "127.0.0.1");
  const base = `${await app.getUrl()}/api/v1`;
  async function req(path, method = "GET", body, cookie) {
    return fetch(base + path, {
      method,
      headers: {
        Origin: "http://localhost:5173",
        ...(body === undefined
          ? {}
          : {
              "Content-Type": "application/json",
              "X-Tech4Learn-Request": "1",
            }),
        ...(cookie ? { Cookie: cookie } : {}),
      },
      body: body === undefined ? undefined : JSON.stringify(body),
    });
  }
  async function ok(path, method = "GET", body, cookie, status = 200) {
    const r = await req(path, method, body, cookie);
    assert.equal(r.status, status, await r.clone().text());
    return r.json();
  }
  async function login(email) {
    const r = await req("/auth/login", "POST", { email, password });
    assert.equal(r.status, 200, await r.clone().text());
    return r.headers.get("set-cookie").split(";")[0];
  }
  let owner,
    admin,
    other,
    roles,
    c1,
    c2,
    g1,
    g2,
    coordinator,
    staffId,
    custom,
    delegate,
    delegateId,
    secondAdmin;
  const prefix = `/organisations/${orgId}`;
  const grant = (role, scope_type = "organisation", scope_ids = []) => ({
    role_id: role.id,
    scope_type,
    scope_ids,
  });
  async function invite(email, body, issuer = admin) {
    const i = await ok(
      prefix + "/invitations",
      "POST",
      { email, ...body },
      issuer,
      201,
    );
    await ok("/invitations/accept", "POST", {
      token: i.token,
      name: email.split("@")[0],
      password,
    });
    return login(email);
  }
  try {
    await t.test(
      "version-one admins and pending invitations keep their intended access",
      async () => {
        owner = await login("owner@access.test");
        admin = await login("admin@access.test");
        roles = await ok(prefix + "/roles", "GET", undefined, admin);
        assert.equal(roles.length, 6);
        assert.equal(
          (await ok(prefix + "/access", "GET", undefined, admin)).access.owner,
          true,
        );
        assert.equal(
          (await ok("/invitations/preview", "POST", { token: legacyToken }))
            .roleName,
          "Organisation admin",
        );
        await ok("/invitations/accept", "POST", {
          token: legacyToken,
          name: "Legacy admin",
          password,
        });
        secondAdmin = await login("legacy@access.test");
        other = (
          await ok(
            "/organisations",
            "POST",
            { name: "Other", slug: "other", adminEmail: "other@access.test" },
            owner,
            201,
          )
        ).organisation;
      },
    );
    await t.test(
      "centre approval cannot be forged and coordinate changes clear approval",
      async () => {
        const body = {
          name: "North",
          address: "Village",
          latitude: 22.5,
          longitude: 88.3,
          radius: 100,
          location_approved: true,
        };
        c1 = await ok(prefix + "/centres", "POST", body, admin, 201);
        assert.equal(c1.location_approved, false);
        await ok(`${prefix}/centres/${c1.id}/approve`, "POST", {}, admin, 201);
        const locationOnly = await ok(`${prefix}/centres/${c1.id}/location`, "PATCH", {latitude:22.55,longitude:88.35,radius:150,name:"Must not replace",address:"Must not replace",centre_type:"other",location_approved:true}, admin);
        assert.equal(locationOnly.name, "North");
        assert.equal(locationOnly.address, "Village");
        assert.equal(locationOnly.centre_type, c1.centre_type);
        assert.equal(locationOnly.latitude, 22.55);
        assert.equal(locationOnly.radius, 150);
        assert.equal(locationOnly.location_approved, false);
        assert.equal((await req(`${prefix}/centres/${c1.id}/location`, "PATCH", {latitude:91,longitude:88.3,radius:100}, admin)).status, 400);
        c1 = await ok(
          `${prefix}/centres/${c1.id}`,
          "PATCH",
          { ...body, latitude: 22.6 },
          admin,
        );
        assert.equal(c1.location_approved, false);
        assert.equal(
          (
            await req(
              prefix + "/centres",
              "POST",
              { ...body, latitude: 91 },
              admin,
            )
          ).status,
          400,
        );
        assert.equal(
          (
            await req(
              prefix + "/centres",
              "POST",
              { ...body, longitude: null },
              admin,
            )
          ).status,
          400,
        );
        c2 = await ok(
          prefix + "/centres",
          "POST",
          { ...body, name: "South" },
          admin,
          201,
        );
        g1 = await ok(
          prefix + "/groups",
          "POST",
          { name: "Group North", centre_id: c1.id },
          admin,
          201,
        );
        g2 = await ok(
          prefix + "/groups",
          "POST",
          { name: "Group South", centre_id: c2.id },
          admin,
          201,
        );
        assert.equal(
          (await req(`${prefix}/centres/${c1.id}/archive`, "POST", {}, admin))
            .status,
          409,
        );
        assert.equal(
          (
            await req(
              `${prefix}/groups/${g1.id}`,
              "PATCH",
              { name: "Moved", centre_id: c2.id },
              admin,
            )
          ).status,
          400,
        );
      },
    );
    await t.test(
      "centre and group scopes constrain both lists and mutations",
      async () => {
        custom = await ok(
          prefix + "/roles",
          "POST",
          {
            name: "Local coordinator",
            permissions: roles.find((r) => r.name === "Centre coordinator")
              .permissions,
          },
          admin,
          201,
        );
        coordinator = await invite(
          "staff@access.test",
          grant(custom, "centres", [c1.id]),
        );
        staffId = (await ok("/auth/me", "GET", undefined, coordinator)).user.id;
        assert.deepEqual(
          (await ok(prefix + "/centres", "GET", undefined, coordinator)).map(
            (c) => c.id,
          ),
          [c1.id],
        );
        assert.deepEqual(
          (await ok(prefix + "/groups", "GET", undefined, coordinator)).map(
            (g) => g.id,
          ),
          [g1.id],
        );
        await ok(
          `${prefix}/groups/${g1.id}`,
          "PATCH",
          { name: "Updated", organisation_id: other.id },
          coordinator,
        );
        assert.equal(
          (
            await req(
              `${prefix}/groups/${g2.id}`,
              "PATCH",
              { name: "Denied" },
              coordinator,
            )
          ).status,
          404,
        );
        assert.equal(
          (
            await req(
              prefix + "/groups",
              "POST",
              { name: "Denied", centre_id: c2.id },
              coordinator,
            )
          ).status,
          404,
        );
        for (const path of ["/roles", "/members", "/audit", "/audit/export"])
          assert.equal(
            (await req(prefix + path, "GET", undefined, coordinator)).status,
            403,
          );
        assert.equal(
          (
            await req(
              prefix,
              "PATCH",
              { name: "Denied", colour: "#112233", centre_label: "Hub" },
              coordinator,
            )
          ).status,
          403,
        );
        await ok(
          `${prefix}/members/${staffId}`,
          "PATCH",
          grant(custom, "groups", [g1.id]),
          admin,
        );
        await ok(
          `${prefix}/groups/${g1.id}`,
          "PATCH",
          { name: "Group scoped edit" },
          coordinator,
        );
        assert.equal(
          (
            await req(
              prefix + "/groups",
              "POST",
              { name: "Denied", centre_id: c1.id },
              coordinator,
            )
          ).status,
          403,
        );
      },
    );
    await t.test(
      "cross-tenant IDs and invented permissions are rejected",
      async () => {
        const otherRoles = await ok(
          `/organisations/${other.id}/roles`,
          "GET",
          undefined,
          owner,
        );
        assert.equal(
          (
            await req(
              prefix + "/invitations",
              "POST",
              { email: "bad@access.test", ...grant(otherRoles[0]) },
              admin,
            )
          ).status,
          400,
        );
        const alien = await ok(
          `/organisations/${other.id}/centres`,
          "POST",
          {
            name: "Alien",
            address: "",
            latitude: null,
            longitude: null,
            radius: 100,
          },
          owner,
          201,
        );
        assert.equal((await req(`${prefix}/centres/${alien.id}/location`, "PATCH", {latitude:22.5,longitude:88.3,radius:100}, admin)).status, 404);
        assert.equal(
          (
            await req(
              prefix + "/invitations",
              "POST",
              {
                email: "bad@access.test",
                ...grant(custom, "centres", [alien.id]),
              },
              admin,
            )
          ).status,
          400,
        );
        assert.equal(
          (
            await req(
              prefix + "/groups",
              "POST",
              { name: "Alien", centre_id: alien.id },
              admin,
            )
          ).status,
          404,
        );
        assert.equal(
          (
            await req(
              `/organisations/${other.id}/access`,
              "GET",
              undefined,
              coordinator,
            )
          ).status,
          404,
        );
        assert.equal(
          (
            await req(
              prefix + "/roles",
              "POST",
              {
                name: "Hacker",
                permissions: ["organisation.view", "platform.superadmin"],
              },
              admin,
            )
          ).status,
          400,
        );
        await assert.rejects(
          pg.query(
            "INSERT INTO learning_groups(id,organisation_id,centre_id,name) VALUES ($1,$2,$3,$4)",
            [randomUUID(), orgId, alien.id, "Invalid FK"],
          ),
        );
      },
    );
    await t.test(
      "protected roles, self-access and the last active administrator are guarded",
      async () => {
        const safety = roles.find((r) => r.protected),
          viewer = roles.find((r) => r.name === "Viewer");
        assert.equal(
          (
            await req(
              `${prefix}/roles/${safety.id}`,
              "PATCH",
              { name: "Changed", permissions: safety.permissions },
              admin,
            )
          ).status,
          403,
        );
        assert.equal(
          (
            await req(
              `${prefix}/members/${adminId}`,
              "PATCH",
              grant(viewer),
              admin,
            )
          ).status,
          403,
        );
        const legacyId = (await ok("/auth/me", "GET", undefined, secondAdmin))
          .user.id;
        await ok(
          `${prefix}/members/${legacyId}`,
          "PATCH",
          { ...grant(safety), status: "suspended" },
          admin,
        );
        assert.equal(
          (
            await req(
              `${prefix}/members/${adminId}`,
              "PATCH",
              { ...grant(safety), status: "suspended" },
              owner,
            )
          ).status,
          409,
        );
        assert.equal(
          (await req(prefix + "/access", "GET", undefined, secondAdmin)).status,
          404,
        );
        assert.equal(
          (await ok("/auth/me", "GET", undefined, secondAdmin)).organisations
            .length,
          0,
        );
      },
    );
    await t.test(
      "delegated managers cannot promote themselves or assign stronger roles",
      async () => {
        const permissions = [
          "organisation.view",
          "centres.view",
          "groups.view",
          "roles.view",
          "roles.manage",
          "members.view",
          "members.manage",
        ];
        const role = await ok(
          prefix + "/roles",
          "POST",
          { name: "Access helper", permissions },
          admin,
          201,
        );
        delegate = await invite("delegate@access.test", grant(role));
        delegateId = (await ok("/auth/me", "GET", undefined, delegate)).user.id;
        assert.equal(
          (
            await req(
              `${prefix}/roles/${role.id}`,
              "PATCH",
              { name: "Self edit", permissions },
              delegate,
            )
          ).status,
          403,
        );
        assert.equal(
          (
            await req(
              `${prefix}/roles/${custom.id}`,
              "PATCH",
              { name: "Change stronger", permissions: custom.permissions },
              delegate,
            )
          ).status,
          403,
        );
        assert.equal(
          (
            await req(
              prefix + "/invitations",
              "POST",
              {
                email: "promoted@access.test",
                ...grant(roles.find((r) => r.protected)),
              },
              delegate,
            )
          ).status,
          403,
        );
        assert.equal(
          (
            await req(
              `${prefix}/members/${adminId}`,
              "PATCH",
              grant(roles.find((r) => r.name === "Viewer")),
              delegate,
            )
          ).status,
          403,
        );
        const pending = await ok(
          prefix + "/invitations",
          "POST",
          {
            email: "pending@access.test",
            ...grant(roles.find((r) => r.name === "Viewer")),
          },
          delegate,
          201,
        );
        await ok(
          `${prefix}/members/${delegateId}`,
          "PATCH",
          { ...grant(role), status: "suspended" },
          admin,
        );
        assert.equal(
          (
            await req("/invitations/accept", "POST", {
              token: pending.token,
              name: "Pending",
              password,
            })
          ).status,
          404,
        );
        assert.equal(
          (
            await pg.query(
              "SELECT id FROM users WHERE email='pending@access.test'",
            )
          ).rows.length,
          0,
        );
      },
    );
    await t.test(
      "role edits and membership changes affect existing sessions immediately",
      async () => {
        assert.equal(
          (
            await req(
              `${prefix}/roles/${custom.id}`,
              "PATCH",
              {
                name: custom.name,
                permissions: [...custom.permissions, "members.view"],
              },
              admin,
            )
          ).status,
          409,
        );
        const permissions = [
          "organisation.view",
          "centres.view",
          "groups.view",
        ];
        await ok(
          `${prefix}/roles/${custom.id}`,
          "PATCH",
          { name: custom.name, permissions },
          admin,
        );
        assert.equal(
          (
            await req(
              `${prefix}/groups/${g1.id}`,
              "PATCH",
              { name: "Revoked" },
              coordinator,
            )
          ).status,
          403,
        );
        await ok(
          `${prefix}/members/${staffId}`,
          "PATCH",
          { ...grant(custom, "groups", [g1.id]), status: "suspended" },
          admin,
        );
        assert.equal(
          (await req(prefix + "/groups", "GET", undefined, coordinator)).status,
          404,
        );
        await ok(
          `${prefix}/members/${staffId}`,
          "PATCH",
          { ...grant(custom, "groups", [g1.id]), status: "active" },
          admin,
        );
        assert.equal(
          (await req(prefix + "/groups", "GET", undefined, coordinator)).status,
          200,
        );
        assert.equal(
          (
            await req(
              prefix + "/invitations",
              "POST",
              { email: "staff@access.test", ...grant(custom) },
              admin,
            )
          ).status,
          409,
        );
      },
    );
    await t.test(
      "archiving retains records and history contains permission changes without secrets",
      async () => {
        await ok(`${prefix}/groups/${g1.id}/archive`, "POST", {}, admin, 201);
        await ok(`${prefix}/centres/${c1.id}/archive`, "POST", {}, admin, 201);
        assert.equal(
          (
            await req(
              `${prefix}/groups/${g1.id}`,
              "PATCH",
              { name: "Archived" },
              admin,
            )
          ).status,
          409,
        );
        assert.equal(
          (
            await req(
              prefix + "/invitations",
              "POST",
              {
                email: "archived@access.test",
                ...grant(custom, "centres", [c1.id]),
              },
              admin,
            )
          ).status,
          400,
        );
        const history = await ok(prefix + "/audit", "GET", undefined, admin);
        const changed = history.find((h) => h.action === "role.updated");
        assert.ok(changed.details.before.permissions);
        assert.ok(changed.details.after.permissions);
        assert.equal(changed.actor_name, "Admin");
        assert.ok(history.some((h) => h.action === "membership.updated"));
        assert.ok(history.some((h) => h.action === "centre.archived"));
        const json = JSON.stringify(history);
        assert.ok(!json.includes(hash));
        assert.ok(!json.includes(legacyToken));
        assert.equal(
          (await req(prefix + "/audit?offset=-1", "GET", undefined, admin))
            .status,
          400,
        );
        assert.ok(
          Array.isArray(
            await ok(prefix + "/audit/export", "GET", undefined, admin),
          ),
        );
      },
    );
  } finally {
    await app.close();
    await pg.close();
  }
});
