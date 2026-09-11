import { configurationMigration } from "../dist/migration-configuration.js";
import { learnerMigration } from '../dist/migration-learners.js';
import { test } from "node:test";
import assert from "node:assert/strict";
import { randomUUID } from "node:crypto";
import { PGlite } from "@electric-sql/pglite";
import { migration } from "../dist/schema.js";
import { accessMigration } from "../dist/migration-access.js";
import { createDemo, inspectDemo, removeDemo } from "../dist/demo.js";
import { createApp } from "../dist/bootstrap.js";
import { hashPassword } from "../dist/security.js";

test("demo fixture supports real logins and safe, complete cleanup", async (t) => {
  const pg = new PGlite();
  await pg.exec(migration);
  await pg.exec(accessMigration);
  await pg.exec(learnerMigration);
  await pg.exec(configurationMigration);
  const db = {
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
  const owner = randomUUID(),
    realOrg = randomUUID(),
    realCentre = randomUUID();
  await pg.query(
    "INSERT INTO users(id,email,name,password_hash,is_superadmin) VALUES ($1,$2,$3,$4,true)",
    [
      owner,
      "real-owner@example.test",
      "Existing owner",
      await hashPassword("Synthetic existing password"),
    ],
  );
  await pg.query("INSERT INTO organisations(id,name,slug) VALUES ($1,$2,$3)", [
    realOrg,
    "DEMO named but real organisation",
    "demo-but-not-seeded",
  ]);
  await pg.query(
    "INSERT INTO centres(id,organisation_id,name) VALUES ($1,$2,$3)",
    [realCentre, realOrg, "Existing centre"],
  );
  const original = (
    await pg.query("SELECT * FROM organisations WHERE id=$1", [realOrg])
  ).rows[0];
  const app = await createApp(undefined, db);
  app.useLogger(false);
  await app.listen(0, "127.0.0.1");
  const base = `${await app.getUrl()}/api/v1`;
  const request = (path, body, cookie) =>
    fetch(base + path, {
      method: body === undefined ? "GET" : "POST",
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
  let demo, adminCookie, teacherCookie, teacherId;
  try {
    await t.test(
      "a failed seed rolls back all inserted organisations and identities",
      async () => {
        const failing = {
          ...db,
          transaction: (run) =>
            db.transaction((sql) =>
              run({
                query: (statement, params) => {
                  if (statement.startsWith("INSERT INTO memberships"))
                    throw new Error("Injected seed failure");
                  return sql.query(statement, params);
                },
              }),
            ),
        };
        await assert.rejects(createDemo(failing), /Injected seed failure/);
        assert.equal(
          (await pg.query("SELECT id FROM organisations")).rows.length,
          1,
        );
        assert.equal((await pg.query("SELECT id FROM users")).rows.length, 1);
        assert.equal(
          (
            await pg.query(
              "SELECT id FROM audit_events WHERE action='demo.dataset_created'",
            )
          ).rows.length,
          0,
        );
      },
    );
    await t.test(
      "creates labelled organisations and all fixture states without altering existing records",
      async () => {
        demo = await createDemo(db);
        assert.equal(demo.organisations.length, 2);
        assert.equal(demo.accounts.length, 10);
        const inspection = await inspectDemo(db, demo.datasetId);
        assert.equal(inspection.counts.centres, 5);
        assert.equal(inspection.counts.learning_groups, 6);
        assert.equal(inspection.counts.memberships, 10);
        assert.equal(inspection.counts.invitations, 2);
        assert.deepEqual(
          (await pg.query("SELECT * FROM organisations WHERE id=$1", [realOrg]))
            .rows[0],
          original,
        );
        assert.equal(new Set(demo.accounts.map((a) => a.password)).size, 10);
        for (const a of demo.accounts) {
          assert.ok(a.email.endsWith("@example.test"));
          assert.ok(a.password.length >= 24);
        }
        const audit = JSON.stringify(
          (await pg.query("SELECT * FROM audit_events")).rows,
        );
        for (const a of demo.accounts) assert.ok(!audit.includes(a.password));
        for (const i of demo.invitations) assert.ok(!audit.includes(i.token));
      },
    );
    await t.test(
      "a repeat seed refuses instead of duplicating data or resetting passwords",
      async () => {
        const before = (
          await pg.query("SELECT id,password_hash FROM users ORDER BY id")
        ).rows;
        await assert.rejects(createDemo(db), /Demo already exists/);
        assert.deepEqual(
          (await pg.query("SELECT id,password_hash FROM users ORDER BY id"))
            .rows,
          before,
        );
      },
    );
    await t.test(
      "generated accounts sign in with actual role and scope enforcement",
      async () => {
        for (const a of demo.accounts) {
          const r = await request("/auth/login", {
            email: a.email,
            password: a.password,
          });
          assert.equal(r.status, 200, await r.clone().text());
          const cookie = r.headers.get("set-cookie").split(";")[0];
          const me = await (
            await request("/auth/me", undefined, cookie)
          ).json();
          assert.equal(me.user.is_superadmin, false);
          if (a.name === "DEMO admin") adminCookie = cookie;
          if (a.status === "suspended") {
            assert.deepEqual(me.organisations, []);
            continue;
          }
          assert.equal(me.organisations.length, 1);
          const org = me.organisations[0].id;
          const access = await (
            await request(`/organisations/${org}/access`, undefined, cookie)
          ).json();
          assert.equal(access.access.roleName, a.role);
          assert.equal(
            (
              await request(
                `/organisations/${demo.organisations.find((o) => o.id !== org).id}/access`,
                undefined,
                cookie,
              )
            ).status,
            404,
          );
          if (a.name === "DEMO teacher") {
            teacherCookie = cookie;
            teacherId = me.user.id;
            const groups = await (
              await request(`/organisations/${org}/groups`, undefined, cookie)
            ).json();
            assert.equal(groups.length, 1);
            assert.match(groups[0].name, /Morning/);
            assert.equal(
              (
                await request(
                  `/organisations/${org}/members`,
                  undefined,
                  cookie,
                )
              ).status,
              403,
            );
          }
        }
      },
    );
    await t.test(
      "pending and expired invitation examples exercise actual acceptance rules",
      async () => {
        const pending = demo.invitations.find((i) => i.state === "pending"),
          expired = demo.invitations.find((i) => i.state === "expired");
        assert.equal(
          (await request("/invitations/preview", { token: expired.token }))
            .status,
          404,
        );
        assert.equal(
          (await request("/invitations/preview", { token: pending.token }))
            .status,
          200,
        );
        const accepted = await request("/invitations/accept", {
          token: pending.token,
          name: "DEMO invited viewer",
          password: demo.accounts[0].password,
        });
        assert.equal(accepted.status, 200, await accepted.clone().text());
        assert.equal(
          (
            await pg.query("SELECT id FROM users WHERE email=$1", [
              pending.email,
            ])
          ).rows.length,
          1,
        );
      },
    );
    await t.test(
      "cleanup refuses unknown IDs and identities used outside demo organisations",
      async () => {
        await assert.rejects(removeDemo(db, realOrg), /not found/);
        const role = randomUUID();
        await pg.query(
          "INSERT INTO access_roles(id,organisation_id,name,permissions) VALUES ($1,$2,'Real role',ARRAY['organisation.view'])",
          [role, realOrg],
        );
        await pg.query(
          "INSERT INTO memberships(user_id,organisation_id,role,role_id) VALUES ($1,$2,'member',$3)",
          [teacherId, realOrg, role],
        );
        await assert.rejects(
          removeDemo(db, demo.datasetId),
          /another organisation/,
        );
        assert.equal(
          (await inspectDemo(db, demo.datasetId)).counts.organisations,
          2,
        );
        await pg.query(
          "DELETE FROM memberships WHERE user_id=$1 AND organisation_id=$2",
          [teacherId, realOrg],
        );
        await pg.query("UPDATE users SET is_superadmin=true WHERE id=$1", [
          teacherId,
        ]);
        await assert.rejects(removeDemo(db, demo.datasetId), /superadmin/);
        await pg.query("UPDATE users SET is_superadmin=false WHERE id=$1", [
          teacherId,
        ]);
        const outsideInvite = randomUUID();
        await pg.query(
          "INSERT INTO invitations(id,organisation_id,email,token_hash,expires_at,created_by,role_id) VALUES ($1,$2,$3,$4,now()+interval '1 day',$5,$6)",
          [
            outsideInvite,
            realOrg,
            demo.accounts.find((a) => a.name === "DEMO teacher").email,
            randomUUID(),
            owner,
            role,
          ],
        );
        await assert.rejects(
          removeDemo(db, demo.datasetId),
          /invitation in another organisation/,
        );
        await pg.query("DELETE FROM invitations WHERE id=$1", [outsideInvite]);
      },
    );
    await t.test(
      "cleanup includes test additions and accepted demo invitees while retaining unrelated accounts/data",
      async () => {
        // A real user joined the demo: remove only that membership, never the real user.
        const org = demo.organisations[0].id,
          role = (
            await pg.query(
              "SELECT id FROM access_roles WHERE organisation_id=$1 AND protected",
              [org],
            )
          ).rows[0].id;
        await pg.query(
          "INSERT INTO memberships(user_id,organisation_id,role,role_id) VALUES ($1,$2,'organisation_admin',$3)",
          [owner, org, role],
        );
        const newCentre = randomUUID();
        await pg.query(
          "INSERT INTO centres(id,organisation_id,name) VALUES ($1,$2,$3)",
          [newCentre, org, "Added while testing"],
        );
        await pg.query(
          "INSERT INTO learning_groups(id,organisation_id,centre_id,name) VALUES ($1,$2,$3,$4)",
          [randomUUID(), org, newCentre, "Extra test group"],
        );
        await removeDemo(db, demo.datasetId);
        assert.deepEqual(
          (await pg.query("SELECT * FROM organisations WHERE id=$1", [realOrg]))
            .rows[0],
          original,
        );
        assert.equal(
          (await pg.query("SELECT id FROM centres WHERE id=$1", [realCentre]))
            .rows.length,
          1,
        );
        assert.deepEqual((await pg.query("SELECT id FROM users")).rows, [
          { id: owner },
        ]);
        assert.equal((await pg.query("SELECT * FROM sessions")).rows.length, 0);
        assert.equal(
          (await request("/auth/me", undefined, teacherCookie)).status,
          401,
        );
        assert.equal(
          (await request("/auth/me", undefined, adminCookie)).status,
          401,
        );
        assert.equal(
          (
            await pg.query("SELECT action FROM audit_events WHERE id=$1", [
              demo.datasetId,
            ])
          ).rows[0].action,
          "demo.dataset_removed",
        );
        await assert.rejects(removeDemo(db, demo.datasetId), /not found/);
      },
    );
  } finally {
    await app.close();
    await pg.close();
  }
});
