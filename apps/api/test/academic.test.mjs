import { bulkAttendanceMigration } from "../dist/migration-bulk-attendance.js";
import { test } from "node:test";
import assert from "node:assert/strict";
import { randomUUID } from "node:crypto";
import { PGlite } from "@electric-sql/pglite";
import { migration } from "../dist/schema.js";
import { accessMigration } from "../dist/migration-access.js";
import { learnerMigration } from "../dist/migration-learners.js";
import { configurationMigration } from "../dist/migration-configuration.js";
import { attendanceMigration } from "../dist/migration-attendance.js";
import { visionMigration } from "../dist/migration-vision.js";
import { academicMigration } from "../dist/migration-academic.js";
import { createApp } from "../dist/bootstrap.js";
import { createDemo, seedDemoLearners, removeDemo } from "../dist/demo.js";
import { hashPassword } from "../dist/security.js";

test("academic structure preserves evidence and enforces tenant and section scopes", async (t) => {
  const pg = new PGlite();
  for (const s of [
    migration,
    accessMigration,
    learnerMigration,
    configurationMigration,
    attendanceMigration,
    bulkAttendanceMigration,
    visionMigration,
  ])
    await pg.exec(s);
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
    "INSERT INTO users(id,email,name,password_hash,is_superadmin) VALUES($1,'owner@academic.test','Synthetic owner',$2,true)",
    [randomUUID(), await hashPassword("Synthetic academic password")],
  );
  const demo = await createDemo(db);
  await seedDemoLearners(db, demo.datasetId);
  const org = demo.organisations[0].id,
    other = demo.organisations[1].id,
    p = `/organisations/${org}`;
  await pg.query(
    'INSERT INTO organisation_settings(organisation_id,enabled_modules) VALUES($1,\'{"learners":true,"attendance":true,"exams":false,"fln":false}\'::jsonb)',
    [org],
  );
  const old = (
    await pg.query(
      "SELECT id,centre_id,name FROM learning_groups WHERE organisation_id=$1 AND NOT archived ORDER BY id LIMIT 1",
      [org],
    )
  ).rows[0];
  const captureId = randomUUID(),
    snapshot = {
      group_name: old.name,
      roster: [],
      marker: "Before academic migration",
    };
  await pg.query(
    "INSERT INTO attendance_sessions(id,organisation_id,group_id,centre_id,snapshot) VALUES($1,$2,$3,$4,$5)",
    [captureId, org, old.id, old.centre_id, JSON.stringify(snapshot)],
  );
  await pg.query(
    "INSERT INTO attendance_photos(organisation_id,session_id,content) VALUES($1,$2,$3)",
    [org, captureId, Buffer.from("synthetic transport bytes")],
  );
  const before = (
    await pg.query("SELECT id,group_id FROM learners ORDER BY id")
  ).rows;
  await pg.exec(academicMigration);
  const app = await createApp(undefined, db);
  app.useLogger(false);
  await app.listen(0, "127.0.0.1");
  const base = (await app.getUrl()) + "/api/v1";
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
  let year, klass, section, legacy, centres;
  try {
    await t.test(
      "migration preserves existing group IDs, students and photo evidence",
      async () => {
        assert.deepEqual(
          (await pg.query("SELECT id,group_id FROM learners ORDER BY id")).rows,
          before,
        );
        assert.deepEqual(
          (
            await pg.query(
              "SELECT snapshot FROM attendance_sessions WHERE id=$1",
              [captureId],
            )
          ).rows[0].snapshot,
          snapshot,
        );
        assert.equal(
          Buffer.from(
            (
              await pg.query(
                "SELECT content FROM attendance_photos WHERE session_id=$1",
                [captureId],
              )
            ).rows[0].content,
          ).toString(),
          "synthetic transport bytes",
        );
        assert.equal(
          (
            await pg.query("SELECT class_id FROM learning_groups WHERE id=$1", [
              old.id,
            ])
          ).rows[0].class_id,
          null,
        );
      },
    );
    await t.test(
      "centre types, valid years and unique centre classes",
      async () => {
        centres = await ok(p + "/centres", "GET", undefined, admin);
        legacy = (await ok(p + "/groups", "GET", undefined, teacher))[0];
        const c = centres.find((c) => c.id === legacy.centre_id);
        const changed = await ok(
          p + "/centres/" + c.id,
          "PATCH",
          { ...c, centre_type: "school" },
          admin,
        );
        assert.equal(changed.centre_type, "school");
        assert.equal(changed.location_approved, c.location_approved);
        assert.equal(
          (
            await req(
              p + "/centres/" + c.id,
              "PATCH",
              { ...c, centre_type: "invented" },
              admin,
            )
          ).status,
          400,
        );
        const body = {
          name: "2026–27",
          starts_on: "2026-04-01",
          ends_on: "2027-03-31",
        };
        assert.equal(
          (
            await req(
              p + "/academic-years",
              "POST",
              { ...body, starts_on: "2026-02-30" },
              admin,
            )
          ).status,
          400,
        );
        assert.equal(
          (
            await req(
              p + "/academic-years",
              "POST",
              { ...body, ends_on: "2025-01-01" },
              admin,
            )
          ).status,
          400,
        );
        year = await ok(p + "/academic-years", "POST", body, admin, 201);
        assert.equal(
          (await req(p + "/academic-years", "POST", body, admin)).status,
          409,
        );
        klass = await ok(
          p + "/classes",
          "POST",
          { name: "Class 3", centre_id: c.id, academic_year_id: year.id },
          admin,
          201,
        );
        assert.equal(
          (
            await req(
              p + "/classes",
              "POST",
              { name: "class 3", centre_id: c.id, academic_year_id: year.id },
              admin,
            )
          ).status,
          409,
        );
        assert.equal(
          (
            await req(
              `/organisations/${other}/classes`,
              "POST",
              {
                name: "Wrong year",
                centre_id: (
                  await ok(
                    `/organisations/${other}/centres`,
                    "GET",
                    undefined,
                    otherAdmin,
                  )
                )[0].id,
                academic_year_id: year.id,
              },
              otherAdmin,
            )
          ).status,
          404,
        );
        assert.equal(
          (await req(p + "/classes", "GET", undefined, otherAdmin)).status,
          404,
        );
        assert.equal(
          (
            await req(
              p + "/classes",
              "POST",
              { name: "Denied", centre_id: c.id, academic_year_id: year.id },
              teacher,
            )
          ).status,
          403,
        );
      },
    );
    await t.test(
      "legacy groups can attach once; new sections use a scoped class and year",
      async () => {
        await ok(
          p + "/groups/" + legacy.id,
          "PATCH",
          { name: legacy.name, class_id: klass.id },
          admin,
        );
        assert.equal(
          (await ok(p + "/classes", "GET", undefined, teacher)).some(
            (c) => c.id === klass.id,
          ),
          true,
        );
        section = await ok(
          p + "/groups",
          "POST",
          { name: "A", centre_id: klass.centre_id, class_id: klass.id },
          admin,
          201,
        );
        assert.equal(
          (
            await req(
              p + "/groups",
              "POST",
              { name: "a", centre_id: klass.centre_id, class_id: klass.id },
              admin,
            )
          ).status,
          409,
        );
        const wrong = centres.find(
          (c) => !c.archived && c.id !== klass.centre_id,
        );
        assert.equal(
          (
            await req(
              p + "/groups",
              "POST",
              { name: "Bad link", centre_id: wrong.id, class_id: klass.id },
              admin,
            )
          ).status,
          404,
        );
        await assert.rejects(
          pg.query(
            "INSERT INTO learning_groups(id,organisation_id,centre_id,name,class_id) VALUES($1,$2,$3,'DB rejected',$4)",
            [randomUUID(), org, wrong.id, klass.id],
          ),
        );
        assert.equal(
          (
            await req(
              p + "/groups/" + section.id,
              "PATCH",
              { name: "A", class_id: null },
              admin,
            )
          ).status,
          409,
        );
        const records = await ok(p + "/groups", "GET", undefined, admin);
        assert.equal(
          records.find((g) => g.id === section.id).display_name,
          "2026–27 / Class 3 / A",
        );
        assert.equal(
          (await ok(p + "/groups", "GET", undefined, teacher)).some(
            (g) => g.id === section.id,
          ),
          false,
        );
      },
    );
    await t.test(
      "student section filters and attendance snapshots preserve the selected context",
      async () => {
        const student = await ok(
          p + "/learners",
          "POST",
          {
            code: "ACADEMIC-TEST",
            name: "Synthetic academic student",
            group_id: section.id,
            age: 8,
            custom_values: {},
          },
          admin,
          201,
        );
        const filtered = await ok(
          p + "/learners?group_id=" + section.id,
          "GET",
          undefined,
          admin,
        );
        assert.equal(filtered.items.length, 1);
        assert.equal(filtered.items[0].id, student.id);
        assert.equal(filtered.items[0].group_name, "2026–27 / Class 3 / A");
        assert.equal(
          (
            await ok(
              p + "/learners?group_id=" + section.id,
              "GET",
              undefined,
              teacher,
            )
          ).items.length,
          0,
        );
        const capture = await ok(
          p + "/attendance/captures",
          "POST",
          { group_id: section.id },
          admin,
          201,
        );
        assert.equal(capture.snapshot.group_name, "2026–27 / Class 3 / A");
        assert.deepEqual(
          (
            await pg.query(
              "SELECT snapshot FROM attendance_sessions WHERE id=$1",
              [captureId],
            )
          ).rows[0].snapshot,
          snapshot,
        );
        assert.equal(
          (
            await req(
              p + "/classes/" + klass.id + "/archive",
              "POST",
              {},
              admin,
            )
          ).status,
          409,
        );
        assert.equal(
          (
            await req(
              p + "/academic-years/" + year.id + "/archive",
              "POST",
              {},
              admin,
            )
          ).status,
          409,
        );
        const empty = await ok(
          p + "/classes",
          "POST",
          {
            name: "Empty class",
            centre_id: klass.centre_id,
            academic_year_id: year.id,
          },
          admin,
          201,
        );
        await ok(
          p + "/classes/" + empty.id + "/archive",
          "POST",
          {},
          admin,
          201,
        );
        assert.equal(
          (
            await req(
              p + "/groups",
              "POST",
              {
                name: "Blocked",
                centre_id: klass.centre_id,
                class_id: empty.id,
              },
              admin,
            )
          ).status,
          409,
        );
      },
    );
    await t.test(
      "demo cleanup removes academic records only inside its manifest",
      async () => {
        await removeDemo(db, demo.datasetId);
        assert.equal(
          (await pg.query("SELECT id FROM learning_classes")).rows.length,
          0,
        );
        assert.equal(
          (await pg.query("SELECT id FROM academic_years")).rows.length,
          0,
        );
      },
    );
  } finally {
    await app.close();
    await pg.close();
  }
});
