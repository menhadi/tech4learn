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

test("identity and organisation permissions through HTTP with the PostgreSQL engine", async (t) => {
  const pg = new PGlite();
  await pg.exec(migration);
  await pg.exec(accessMigration);
  await pg.exec(learnerMigration);
  await pg.exec(configurationMigration);
  await pg.exec(academicMigration);
  const adapter = {
    query: (sql, params) => pg.query(sql, params),
    transaction: (run) =>
      pg.transaction((client) =>
        run({
          query: (sql, params) =>
            params?.length
              ? client.query(sql, params)
              : client.exec(sql).then((results) => results.at(-1)),
        }),
      ),
    onModuleDestroy: async () => {},
  };
  const superId = randomUUID();
  const password = "a long synthetic test password";
  await pg.query(
    "INSERT INTO users(id,email,name,password_hash,is_superadmin) VALUES ($1,$2,$3,$4,true)",
    [superId, "owner@example.test", "Test owner", await hashPassword(password)],
  );
  let app = await createApp(undefined, adapter);
  await app.listen(0, "127.0.0.1");
  let base = `${await app.getUrl()}/api/v1`;
  async function request(path, method = "GET", body, cookie, extra = {}) {
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
        ...extra,
      },
      body: body === undefined ? undefined : JSON.stringify(body),
    });
  }
  async function login(email, pw = password) {
    const response = await request("/auth/login", "POST", {
      email,
      password: pw,
    });
    assert.equal(response.status, 200, await response.clone().text());
    assert.match(response.headers.get("set-cookie"), /HttpOnly/);
    assert.match(response.headers.get("set-cookie"), /SameSite=Lax/);
    assert.equal(response.headers.get("cache-control"), "no-store");
    return response.headers.get("set-cookie").split(";")[0];
  }
  let owner, admin, orgA, orgB, inviteA;
  try {
    await t.test(
      "unauthenticated, hostile origins, bad credentials and spoofed roles are denied",
      async () => {
        assert.equal((await request("/organisations")).status, 401);
        assert.equal(
          (
            await request(
              "/auth/login",
              "POST",
              { email: "owner@example.test", password },
              undefined,
              { Origin: "https://evil.example" },
            )
          ).status,
          403,
        );
        assert.equal(
          (
            await request(
              "/auth/login",
              "POST",
              { email: "owner@example.test", password },
              undefined,
              { "X-Tech4Learn-Request": "" },
            )
          ).status,
          403,
        );
        const wrong = await request("/auth/login", "POST", {
          email: "owner@example.test",
          password: "incorrect",
        });
        const unknown = await request("/auth/login", "POST", {
          email: "missing@example.test",
          password: "incorrect",
        });
        assert.equal(wrong.status, 401);
        assert.deepEqual(await wrong.json(), await unknown.json());
        owner = await login("OWNER@example.test");
      },
    );
    await t.test(
      "superadmin creates organisations atomically with hashed invitations",
      async () => {
        const first = await request(
          "/organisations",
          "POST",
          {
            name: "Learning A",
            slug: "learning-a",
            adminEmail: "admin-a@example.test",
          },
          owner,
        );
        assert.equal(first.status, 201);
        const result = await first.json();
        orgA = result.organisation;
        inviteA = result.invitation;
        const second = await request(
          "/organisations",
          "POST",
          {
            name: "Learning B",
            slug: "learning-b",
            adminEmail: "admin-b@example.test",
          },
          owner,
        );
        orgB = (await second.json()).organisation;
        assert.equal(
          (
            await request(
              "/organisations",
              "POST",
              {
                name: "Duplicate",
                slug: "learning-a",
                adminEmail: "other@example.test",
              },
              owner,
            )
          ).status,
          409,
        );
        assert.equal(
          (await pg.query("SELECT * FROM organisations")).rows.length,
          2,
        );
        const row = (
          await pg.query("SELECT token_hash FROM invitations WHERE email=$1", [
            "admin-a@example.test",
          ])
        ).rows[0];
        assert.equal(row.token_hash, digest(inviteA.token));
        assert.notEqual(row.token_hash, inviteA.token);
      },
    );
    await t.test(
      "invitations create only org admins and cannot be replayed",
      async () => {
        const body = {
          token: inviteA.token,
          name: "Org admin",
          password,
          is_superadmin: true,
          organisation_id: orgB.id,
        };
        assert.equal(
          (
            await request("/invitations/accept", "POST", {
              ...body,
              password: "short",
            })
          ).status,
          400,
        );
        assert.equal(
          (await request("/invitations/accept", "POST", body)).status,
          200,
        );
        assert.equal(
          (await request("/invitations/accept", "POST", body)).status,
          404,
        );
        admin = await login("admin-a@example.test");
        const me = await (
          await request("/auth/me", "GET", undefined, admin)
        ).json();
        assert.equal(me.user.is_superadmin, false);
        assert.deepEqual(
          me.organisations.map((org) => org.id),
          [orgA.id],
        );
        assert.equal("password_hash" in me.user, false);
        assert.equal(
          (
            await pg.query("SELECT password_hash FROM users WHERE email=$1", [
              "admin-a@example.test",
            ])
          ).rows[0].password_hash.startsWith("scrypt-v1$"),
          true,
        );
      },
    );
    await t.test(
      "cross-organisation reads, writes, invites and platform actions are denied",
      async () => {
        assert.equal(
          (await request(`/organisations/${orgB.id}`, "GET", undefined, admin))
            .status,
          404,
        );
        const profile = {
          name: "Renamed",
          colour: "#224455",
          centre_label: "Hub",
          organisation_id: orgB.id,
          is_superadmin: true,
        };
        assert.equal(
          (await request(`/organisations/${orgB.id}`, "PATCH", profile, admin))
            .status,
          404,
        );
        assert.equal(
          (
            await request(
              "/organisations",
              "POST",
              {
                name: "Invalid",
                slug: "invalid",
                adminEmail: "x@example.test",
              },
              admin,
            )
          ).status,
          403,
        );
        assert.equal(
          (
            await request(
              `/organisations/${orgB.id}/invitations`,
              "POST",
              { email: "x@example.test" },
              admin,
            )
          ).status,
          404,
        );
        assert.equal(
          (await request(`/organisations/${orgA.id}`, "PATCH", profile, admin))
            .status,
          200,
        );
        assert.equal(
          (
            await pg.query("SELECT name FROM organisations WHERE id=$1", [
              orgB.id,
            ])
          ).rows[0].name,
          "Learning B",
        );
        assert.equal(
          (await request("/organisations/not-a-uuid", "GET", undefined, admin))
            .status,
          400,
        );
        assert.equal(
          (
            await request(
              `/organisations/${orgA.id}`,
              "PATCH",
              { ...profile, colour: "red" },
              admin,
            )
          ).status,
          400,
        );
      },
    );
    await t.test(
      "expired and replaced invitations fail; existing users must prove identity",
      async () => {
        const issue = async (email) =>
          await (
            await request(
              `/organisations/${orgB.id}/invitations`,
              "POST",
              { email },
              owner,
            )
          ).json();
        const old = await issue("new@example.test");
        const fresh = await issue("new@example.test");
        assert.equal(
          (await request("/invitations/preview", "POST", { token: old.token }))
            .status,
          404,
        );
        await pg.query(
          "UPDATE invitations SET expires_at=now() - interval '1 minute' WHERE token_hash=$1",
          [digest(fresh.token)],
        );
        assert.equal(
          (
            await request("/invitations/accept", "POST", {
              token: fresh.token,
              name: "New",
              password,
            })
          ).status,
          404,
        );
        const existing = await issue("admin-a@example.test");
        assert.equal(
          (
            await request("/invitations/accept", "POST", {
              token: existing.token,
              password,
            })
          ).status,
          401,
        );
        assert.equal(
          (
            await request(
              "/invitations/accept",
              "POST",
              { token: existing.token },
              owner,
            )
          ).status,
          403,
        );
        assert.equal(
          (
            await request(
              "/invitations/accept",
              "POST",
              { token: existing.token },
              admin,
            )
          ).status,
          200,
        );
        assert.equal(
          (
            await (
              await request("/organisations", "GET", undefined, admin)
            ).json()
          ).length,
          2,
        );
      },
    );
    await t.test(
      "sessions survive API restart and logout invalidates the server session",
      async () => {
        await app.close();
        app = await createApp(undefined, adapter);
        await app.listen(0, "127.0.0.1");
        base = `${await app.getUrl()}/api/v1`;
        assert.equal(
          (await request("/auth/me", "GET", undefined, admin)).status,
          200,
        );
        assert.equal(
          (await request("/auth/logout", "POST", {}, admin)).status,
          200,
        );
        assert.equal(
          (await request("/auth/me", "GET", undefined, admin)).status,
          401,
        );
        admin = await login("admin-a@example.test");
      },
    );
    await t.test(
      "password changes revoke every session and retain exact password bytes",
      async () => {
        const second = await login("admin-a@example.test");
        const newPassword = "  another long test password  ";
        assert.equal(
          (
            await request(
              "/auth/password",
              "POST",
              { currentPassword: password, password: newPassword },
              admin,
            )
          ).status,
          200,
        );
        assert.equal(
          (await request("/auth/me", "GET", undefined, second)).status,
          401,
        );
        assert.equal(
          (
            await request("/auth/login", "POST", {
              email: "admin-a@example.test",
              password,
            })
          ).status,
          401,
        );
        admin = await login("admin-a@example.test", newPassword);
        await pg.query(
          "UPDATE sessions SET expires_at=now() - interval '1 second' WHERE user_id=(SELECT id FROM users WHERE email='admin-a@example.test')",
        );
        assert.equal(
          (await request("/auth/me", "GET", undefined, admin)).status,
          401,
        );
      },
    );
    await t.test(
      "login throttling and security audit records persist",
      async () => {
        await pg.query(
          "INSERT INTO auth_limits(key,count,expires_at) VALUES ($1,10,now()+interval '15 minutes')",
          [digest("login:blocked@example.test")],
        );
        assert.equal(
          (
            await request("/auth/login", "POST", {
              email: "blocked@example.test",
              password,
            })
          ).status,
          429,
        );
        const actions = (
          await pg.query("SELECT action FROM audit_events")
        ).rows.map((row) => row.action);
        for (const action of [
          "organisation.created",
          "organisation.updated",
          "invitation.created",
          "invitation.accepted",
          "auth.login",
          "auth.password_changed",
        ])
          assert.ok(actions.includes(action));
      },
    );
    await t.test(
      "production uses secure host cookies and cannot trust a client supplied identity",
      async () => {
        const oldEnv = process.env.NODE_ENV;
        process.env.NODE_ENV = "production";
        try {
          const response = await request("/auth/login", "POST", {
            email: "owner@example.test",
            password,
          });
          assert.equal(response.status, 200);
          const cookie = response.headers.get("set-cookie");
          assert.match(cookie, /^__Host-t4l_session=/);
          assert.match(cookie, /; Secure/);
          assert.match(cookie, /; HttpOnly/);
          assert.doesNotMatch(cookie, /Domain=/);
          assert.equal(
            (
              await request(
                "/auth/me",
                "GET",
                undefined,
                `__Host-t4l_session=${"a".repeat(64)}`,
                { "X-User-Id": superId, "X-Role": "superadmin" },
              )
            ).status,
            401,
          );
        } finally {
          if (oldEnv === undefined) delete process.env.NODE_ENV;
          else process.env.NODE_ENV = oldEnv;
        }
      },
    );
  } finally {
    await app.close();
    await pg.close();
  }
});
