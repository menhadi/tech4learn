import { test } from "node:test";
import assert from "node:assert/strict";
import { randomUUID } from "node:crypto";
import { PGlite } from "@electric-sql/pglite";
import { migration } from "../dist/schema.js";
import { accessMigration } from "../dist/migration-access.js";
import { configurationMigration } from "../dist/migration-configuration.js";
import { learnerMigration } from "../dist/migration-learners.js";
import { createDemo, seedDemoLearners, removeDemo } from "../dist/demo.js";
import { createApp } from "../dist/bootstrap.js";
import { hashPassword } from "../dist/security.js";

test("learner scopes, contacts, transfers, custom fields and reviewed imports", async (t) => {
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
  await pg.query(
    "INSERT INTO users(id,email,name,password_hash,is_superadmin) VALUES ($1,$2,$3,$4,true)",
    [
      randomUUID(),
      "owner@learner.test",
      "Synthetic owner",
      await hashPassword("Synthetic test owner password"),
    ],
  );
  const demo = await createDemo(db);
  const seeded = await seedDemoLearners(db, demo.datasetId);
  assert.equal(seeded.count, 15);
  const app = await createApp(undefined, db);
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
  async function login(name) {
    const a = demo.accounts.find((a) => a.name === name);
    const r = await req("/auth/login", "POST", {
      email: a.email,
      password: a.password,
    });
    assert.equal(r.status, 200);
    return r.headers.get("set-cookie").split(";")[0];
  }
  const admin = await login("DEMO admin"),
    teacher = await login("DEMO teacher"),
    otherAdmin = await login("DEMO academy-admin");
  const org = demo.organisations[0].id,
    other = demo.organisations[1].id,
    p = `/organisations/${org}`;
  const gs = await ok(p + "/groups", "GET", undefined, admin),
    teacherGroup = (await ok(p + "/groups", "GET", undefined, teacher))[0],
    target = gs.find((g) => !g.archived && g.id !== teacherGroup.id);
  const payload = (code, group_id = teacherGroup.id) => ({
    code,
    name: `Synthetic ${code}`,
    age: 8,
    class_label: "Level 2",
    group_id,
    custom_values: { demo_language: "Hindi" },
  });
  let id, definition;
  try {
    await t.test(
      "demo augmentation is repeat-safe and contact values are hidden from scoped viewers",
      async () => {
        await assert.rejects(
          seedDemoLearners(db, demo.datasetId),
          /already exist/,
        );
        const list = await ok(p + "/learners", "GET", undefined, teacher);
        assert.equal(list.items.length, 3);
        assert.ok(list.items.every((l) => l.demo));
        id = list.items[0].id;
        const profile = await ok(
          p + "/learners/" + id,
          "GET",
          undefined,
          admin,
        );
        await ok(
          p + "/learners/" + id,
          "PATCH",
          {
            ...profile,
            guardian_name: "Synthetic guardian",
            guardian_phone: "00000",
          },
          admin,
        );
        const limited = await ok(
          p + "/learners/" + id,
          "GET",
          undefined,
          teacher,
        );
        assert.equal("guardian_name" in limited, false);
        assert.equal("guardian_phone" in limited, false);
        assert.equal(
          (
            await req(
              `/organisations/${other}/learners`,
              "GET",
              undefined,
              teacher,
            )
          ).status,
          404,
        );
        const outside = (
          await ok(
            `/organisations/${other}/learners`,
            "GET",
            undefined,
            otherAdmin,
          )
        ).items[0];
        assert.equal(
          (await req(p + "/learners/" + outside.id, "GET", undefined, admin))
            .status,
          404,
        );
        assert.equal(
          (
            await req(
              p + "/learners/" + id,
              "PATCH",
              payload("DENIED"),
              teacher,
            )
          ).status,
          403,
        );
      },
    );
    await t.test(
      "typed custom fields validate values, preserve history and reject incompatible definition edits",
      async () => {
        definition = await ok(
          p + "/learner-fields",
          "POST",
          {
            key: "support",
            label: "Support count",
            kind: "number",
            required: true,
            options: [],
            archived: false,
          },
          admin,
          201,
        );
        assert.equal(
          (await req(p + "/learners", "POST", payload("MISSING"), admin))
            .status,
          400,
        );
        assert.equal(
          (
            await req(
              p + "/learners",
              "POST",
              { ...payload("BADTYPE"), custom_values: { support: "2" } },
              admin,
            )
          ).status,
          400,
        );
        const created = await ok(
          p + "/learners",
          "POST",
          { ...payload("NEW001"), custom_values: { support: 2 } },
          admin,
          201,
        );
        const before = await ok(
          p + "/learners/" + created.id,
          "GET",
          undefined,
          admin,
        );
        assert.equal(
          (
            await req(
              p + "/learner-fields/" + definition.id,
              "PATCH",
              { ...definition, kind: "text" },
              admin,
            )
          ).status,
          409,
        );
        await ok(
          p + "/learner-fields/" + definition.id,
          "PATCH",
          { ...definition, archived: true },
          admin,
        );
        await ok(
          p + "/learners/" + created.id,
          "PATCH",
          { ...before, name: "Changed synthetic name", custom_values: {} },
          admin,
        );
        assert.equal(
          (await ok(p + "/learners/" + created.id, "GET", undefined, admin))
            .custom_values.support,
          2,
        );
        assert.equal(
          (
            await req(
              p + "/learners/" + created.id,
              "PATCH",
              { ...before, custom_values: { support: 3 } },
              admin,
            )
          ).status,
          409,
        );
        const date = await ok(
          p + "/learner-fields",
          "POST",
          {
            key: "joined",
            label: "Joined date",
            kind: "date",
            required: false,
            options: [],
            archived: false,
          },
          admin,
          201,
        );
        assert.equal(
          (
            await req(
              p + "/learners",
              "POST",
              {
                ...payload("DATEBAD"),
                custom_values: { joined: "2026-99-99" },
              },
              admin,
            )
          ).status,
          400,
        );
        assert.equal(
          (
            await req(
              p + "/learners",
              "POST",
              { ...payload("AGEBAD"), age: true },
              admin,
            )
          ).status,
          400,
        );
        assert.equal(
          (
            await req(
              p + "/learner-fields/" + date.id,
              "PATCH",
              { ...date, key: "other" },
              admin,
            )
          ).status,
          409,
        );
      },
    );
    await t.test(
      "transfers preserve history and remove access from the previous scoped teacher",
      async () => {
        const before = await ok(p + "/learners/" + id, "GET", undefined, admin);
        const alienGroup = (
          await ok(
            `/organisations/${other}/groups`,
            "GET",
            undefined,
            otherAdmin,
          )
        )[0];
        assert.equal(
          (
            await req(
              p + "/learners/" + id + "/transfer",
              "POST",
              {
                group_id: alienGroup.id,
                version: before.version,
                reason: "Test",
              },
              admin,
            )
          ).status,
          404,
        );
        await ok(
          p + "/learners/" + id + "/transfer",
          "POST",
          {
            group_id: target.id,
            version: before.version,
            reason: "Synthetic transfer",
          },
          admin,
          201,
        );
        assert.equal(
          (await req(p + "/learners/" + id, "GET", undefined, teacher)).status,
          404,
        );
        const after = await ok(p + "/learners/" + id, "GET", undefined, admin);
        assert.equal(after.history.length, 2);
        assert.equal(
          after.history.filter((h) => h.ended_at === null).length,
          1,
        );
        assert.equal(
          (await req(p + "/learners/" + id, "PATCH", before, admin)).status,
          409,
        );
        assert.equal(
          (
            await req(
              p + "/groups/" + target.id + "/archive",
              "POST",
              {},
              admin,
            )
          ).status,
          409,
        );
        await ok(
          p + "/learners/" + id + "/archive",
          "POST",
          { version: after.version },
          admin,
          201,
        );
        const archived = await ok(
          p + "/learners/" + id,
          "GET",
          undefined,
          admin,
        );
        assert.equal(archived.archived, true);
        assert.ok(archived.history.every((h) => h.ended_at));
      },
    );
    await t.test(
      "import preview never writes learners; errors and duplicates require review",
      async () => {
        const count = Number(
          (await pg.query("SELECT count(*) FROM learners")).rows[0].count,
        );
        const invalid = await ok(
          p + "/learner-imports/preview",
          "POST",
          { rows: [payload("SAME"), payload("SAME")] },
          admin,
          201,
        );
        assert.equal(invalid.id, null);
        assert.ok(invalid.results[1].error);
        assert.equal(
          Number(
            (await pg.query("SELECT count(*) FROM learners")).rows[0].count,
          ),
          count,
        );
        const rows = [
          payload("IMP001"),
          { ...payload("IMP002"), name: "Synthetic IMP001" },
        ];
        const preview = await ok(
          p + "/learner-imports/preview",
          "POST",
          { rows },
          admin,
          201,
        );
        assert.ok(preview.id);
        assert.ok(preview.results[1].warning);
        assert.equal(
          (
            await req(
              p + "/learner-imports/" + preview.id + "/commit",
              "POST",
              {},
              admin,
            )
          ).status,
          409,
        );
        const committed = await ok(
          p + "/learner-imports/" + preview.id + "/commit",
          "POST",
          { confirmDuplicates: true },
          admin,
          201,
        );
        assert.equal(committed.count, 2);
        assert.deepEqual(
          await ok(
            p + "/learner-imports/" + preview.id + "/commit",
            "POST",
            { confirmDuplicates: true },
            admin,
            201,
          ),
          committed,
        );
        assert.equal(
          Number(
            (await pg.query("SELECT count(*) FROM learners")).rows[0].count,
          ),
          count + 2,
        );
      },
    );
    await t.test(
      "commit rechecks changed records, actor ownership, expiry and current permissions",
      async () => {
        const preview = await ok(
          p + "/learner-imports/preview",
          "POST",
          { rows: [payload("RACE001"), payload("RACE002")] },
          admin,
          201,
        );
        const backup = await login("DEMO backup-admin");
        assert.equal(
          (
            await req(
              p + "/learner-imports/" + preview.id + "/commit",
              "POST",
              {},
              backup,
            )
          ).status,
          404,
        );
        await ok(p + "/learners", "POST", payload("RACE002"), admin, 201);
        assert.equal(
          (
            await req(
              p + "/learner-imports/" + preview.id + "/commit",
              "POST",
              {},
              admin,
            )
          ).status,
          409,
        );
        assert.equal(
          (await pg.query("SELECT id FROM learners WHERE code='RACE001'")).rows
            .length,
          0,
        );
        const expiry = await ok(
          p + "/learner-imports/preview",
          "POST",
          { rows: [payload("EXP001")] },
          admin,
          201,
        );
        await pg.query(
          "UPDATE learner_imports SET expires_at=now()-interval '1 minute' WHERE id=$1",
          [expiry.id],
        );
        assert.equal(
          (
            await req(
              p + "/learner-imports/" + expiry.id + "/commit",
              "POST",
              {},
              admin,
            )
          ).status,
          404,
        );
        const forbidden = await ok(
          p + "/learner-imports/preview",
          "POST",
          { rows: [payload("REVOKED")] },
          admin,
          201,
        );
        const account = (await ok("/auth/me", "GET", undefined, admin)).user;
        await pg.query(
          "UPDATE memberships SET status='suspended' WHERE user_id=$1 AND organisation_id=$2",
          [account.id, org],
        );
        assert.equal(
          (
            await req(
              p + "/learner-imports/" + forbidden.id + "/commit",
              "POST",
              {},
              admin,
            )
          ).status,
          404,
        );
        await pg.query(
          "UPDATE memberships SET status='active' WHERE user_id=$1 AND organisation_id=$2",
          [account.id, org],
        );
      },
    );
    await t.test(
      "demo removal includes learners, definitions, enrolment history and import previews",
      async () => {
        await removeDemo(db, demo.datasetId);
        for (const table of [
          "learners",
          "learner_fields",
          "learner_enrolments",
          "learner_imports",
        ])
          assert.equal(
            (await pg.query(`SELECT * FROM ${table}`)).rows.length,
            0,
          );
      },
    );
  } finally {
    await app.close();
    await pg.close();
  }
});
