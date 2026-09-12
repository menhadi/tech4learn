import { test } from "node:test";
import assert from "node:assert/strict";
import { PGlite } from "@electric-sql/pglite";
import { migration } from "../dist/schema.js";
import { accessMigration } from "../dist/migration-access.js";
import { learnerMigration } from "../dist/migration-learners.js";
import { configurationMigration } from "../dist/migration-configuration.js";
import { attendanceMigration } from "../dist/migration-attendance.js";
import { visionMigration } from "../dist/migration-vision.js";
import { createApp } from "../dist/bootstrap.js";
import { createDemo, seedDemoLearners, removeDemo } from "../dist/demo.js";
import {
  classifyLocation,
  distanceMetres,
  jpeg,
} from "../dist/attendance-evidence.js";
import { hashPassword } from "../dist/security.js";

// Synthetic JPEG container assembled for parser/transport tests, not a learner photo.
const photo = () =>
  Buffer.from([
    255, 216, 255, 192, 0, 17, 8, 0, 1, 0, 1, 3, 1, 17, 0, 2, 17, 0, 3, 17, 0,
    255, 218, 0, 12, 3, 1, 0, 2, 0, 3, 0, 0, 63, 0, 0, 255, 217,
  ]).toString("base64");
test("location decisions preserve uncertainty and reject malformed inputs", () => {
  const now = Date.now(),
    centre = {
      latitude: 0,
      longitude: 0,
      radius: 100,
      location_approved: true,
    };
  const reading = {
    latitude: 0,
    longitude: 0,
    accuracy: 10,
    timestamp: new Date(now).toISOString(),
  };
  assert.equal(
    classifyLocation(reading, centre, now, now, 50).location_status,
    "within-radius",
  );
  assert.ok(Math.abs(distanceMetres(0, 0, 0, 1) - 111195) < 2);
  for (const r of [
    null,
    { ...reading, accuracy: 200 },
    { ...reading, longitude: 1 },
    { ...reading, timestamp: new Date(now - 120000).toISOString() },
  ])
    assert.ok(classifyLocation(r, centre, now, now, 50).warnings.length);
  assert.ok(
    classifyLocation(
      reading,
      { ...centre, location_approved: false },
      now,
      now,
      50,
    ).warnings.length,
  );
  assert.ok(
    classifyLocation(
      { ...reading, longitude: 0.00085 },
      centre,
      now,
      now,
      50,
    ).warnings.some((w) => w.includes("uncertainty")),
  );
  assert.ok(
    classifyLocation(reading, centre, now, now + 400000, 50).warnings.length,
  );
  assert.throws(() =>
    classifyLocation({ ...reading, latitude: NaN }, centre, now, now, 50),
  );
  assert.throws(() =>
    classifyLocation({ ...reading, timestamp: "bad" }, centre, now, now, 50),
  );
  assert.throws(() => jpeg("PHN2Zz4="));
  assert.throws(() => jpeg("a".repeat(400000)));
  assert.ok(jpeg(photo()).length);
  const malformed = Buffer.alloc(32, 255);
  malformed[1] = 216;
  malformed[31] = 217;
  assert.throws(
    () => jpeg(malformed.toString("base64")),
    (e) => e.getStatus() === 400,
  );
});

test("attendance HTTP workflow, tenant scopes, immutable evidence and corrections", async (t) => {
  const pg = new PGlite();
  for (const s of [
    migration,
    accessMigration,
    learnerMigration,
    configurationMigration,
    attendanceMigration,
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
    "INSERT INTO users(id,email,name,password_hash,is_superadmin) VALUES(gen_random_uuid(),'owner@attendance.test','Synthetic owner',$1,true)",
    [await hashPassword("Synthetic attendance password")],
  );
  const demo = await createDemo(db);
  await seedDemoLearners(db, demo.datasetId);
  const org = demo.organisations[0].id,
    other = demo.organisations[1].id,
    p = `/organisations/${org}/attendance`;
  const app = await createApp(undefined, db);
  app.useLogger(false);
  await app.listen(0, "127.0.0.1");
  const base = await app.getUrl();
  async function req(path, method = "GET", body, cookie) {
    return fetch(base + "/api/v1" + path, {
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
  async function ok(path, method, body, cookie, status = 200) {
    const r = await req(path, method, body, cookie);
    assert.equal(r.status, status, await r.clone().text());
    return r.json();
  }
  async function login(name) {
    const a = demo.accounts.find((a) => a.name === name),
      r = await req("/auth/login", "POST", {
        email: a.email,
        password: a.password,
      });
    assert.equal(r.status, 200);
    return r.headers.get("set-cookie").split(";")[0];
  }
  const admin = await login("DEMO admin"),
    teacher = await login("DEMO teacher"),
    otherAdmin = await login("DEMO academy-admin");
  const teacherGroup = (
    await ok(`/organisations/${org}/groups`, "GET", undefined, teacher)
  )[0];
  const groups = await ok(
      `/organisations/${org}/groups`,
      "GET",
      undefined,
      admin,
    ),
    outside = groups.find((g) => !g.archived && g.id !== teacherGroup.id);
  let intent, body, detail;
  try {
    await t.test("module and capture permissions are enforced", async () => {
      assert.equal(
        (
          await req(
            p + "/captures",
            "POST",
            { group_id: teacherGroup.id },
            admin,
          )
        ).status,
        403,
      );
      await pg.query(
        'INSERT INTO organisation_settings(organisation_id,enabled_modules) VALUES($1,\'{"learners":true,"attendance":true,"fln":false,"exams":false}\')',
        [org],
      );
      assert.equal(
        (
          await req(
            p + "/captures",
            "POST",
            { group_id: teacherGroup.id },
            teacher,
          )
        ).status,
        403,
      );
      await pg.query(
        "UPDATE access_roles SET permissions=permissions||ARRAY['attendance.view','attendance.capture']::text[] WHERE organisation_id=$1 AND name='Teacher'",
        [org],
      );
      assert.equal(
        (await req(p + "/captures", "POST", { group_id: outside.id }, teacher))
          .status,
        404,
      );
      assert.equal(
        (
          await req(
            p + "/captures",
            "POST",
            { group_id: teacherGroup.id },
            otherAdmin,
          )
        ).status,
        404,
      );
      await pg.query(
        "INSERT INTO custom_fields(id,organisation_id,module,key,label,kind,required,options) VALUES(gen_random_uuid(),$1,'attendance','activity','Activity','choice',true,to_jsonb(ARRAY['Reading','Numbers']))",
        [org],
      );
      intent = await ok(
        p + "/captures",
        "POST",
        { group_id: teacherGroup.id },
        teacher,
        201,
      );
      assert.equal(intent.snapshot.roster.length, 3);
      body = {
        photo: photo(),
        captured_at: new Date().toISOString(),
        location: null,
        custom_values: { activity: "Reading" },
      };
      assert.equal(
        (
          await req(
            `${p}/captures/${intent.id}/submit`,
            "POST",
            { ...body, custom_values: {} },
            teacher,
          )
        ).status,
        400,
      );
      assert.equal(
        (await req(`${p}/captures/${intent.id}/submit`, "POST", body, admin))
          .status,
        403,
      );
      await ok(`${p}/captures/${intent.id}/submit`, "POST", body, teacher, 201);
      await ok(`${p}/captures/${intent.id}/submit`, "POST", body, teacher, 201);
      assert.equal(
        (
          await req(
            `${p}/captures/${intent.id}/submit`,
            "POST",
            { ...body, custom_values: { activity: "Numbers" } },
            teacher,
          )
        ).status,
        409,
      );
      detail = await ok(`${p}/${intent.id}`, "GET", undefined, admin);
      assert.equal(detail.status, "pending");
      assert.deepEqual(detail.marks, {});
      assert.ok(detail.evidence.warnings.length);
    });
    await t.test(
      "private media and records follow fresh grants and scope",
      async () => {
        assert.equal((await req(`${p}/${intent.id}/photo`, "GET")).status, 401);
        assert.equal(
          (await req(`${p}/${intent.id}/photo`, "GET", undefined, teacher))
            .status,
          403,
        );
        assert.equal(
          (await req(`${p}/${intent.id}`, "GET", undefined, otherAdmin)).status,
          404,
        );
        const media = await req(
          `${p}/${intent.id}/photo`,
          "GET",
          undefined,
          admin,
        );
        assert.equal(media.status, 200);
        assert.equal(media.headers.get("cache-control"), "no-store");
        assert.equal(media.headers.get("content-type"), "image/jpeg");
        assert.deepEqual(
          Buffer.from(await media.arrayBuffer()),
          Buffer.from(body.photo, "base64"),
        );
        await pg.query(
          "UPDATE memberships SET status='suspended' WHERE user_id=(SELECT id FROM users WHERE email=$1) AND organisation_id=$2",
          [demo.accounts.find((a) => a.name === "DEMO teacher").email, org],
        );
        assert.equal(
          (await req(`${p}/${intent.id}`, "GET", undefined, teacher)).status,
          404,
        );
        await pg.query(
          "UPDATE memberships SET status='active' WHERE user_id=(SELECT id FROM users WHERE email=$1) AND organisation_id=$2",
          [demo.accounts.find((a) => a.name === "DEMO teacher").email, org],
        );
      },
    );
    await t.test(
      "daily duplicate, mandatory review, roster validation and correction history",
      async () => {
        const second = await ok(
          p + "/captures",
          "POST",
          { group_id: teacherGroup.id },
          teacher,
          201,
        );
        assert.equal(
          (
            await req(
              `${p}/captures/${second.id}/submit`,
              "POST",
              { ...body, captured_at: new Date().toISOString() },
              teacher,
            )
          ).status,
          409,
        );
        const marks = Object.fromEntries(
          detail.snapshot.roster.map((l, i) => [
            l.id,
            i ? "present" : "absent",
          ]),
        );
        const review = {
          version: detail.version,
          decision: "confirmed",
          marks,
          reason: "Checked the group in person",
          acknowledge_warnings: true,
        };
        assert.equal(
          (await req(`${p}/${intent.id}/review`, "POST", review, teacher))
            .status,
          403,
        );
        assert.equal(
          (
            await req(
              `${p}/${intent.id}/review`,
              "POST",
              { ...review, acknowledge_warnings: false },
              admin,
            )
          ).status,
          400,
        );
        assert.equal(
          (
            await req(
              `${p}/${intent.id}/review`,
              "POST",
              { ...review, marks: {} },
              admin,
            )
          ).status,
          400,
        );
        await ok(`${p}/${intent.id}/review`, "POST", review, admin, 201);
        assert.equal(
          (await req(`${p}/${intent.id}/review`, "POST", review, admin)).status,
          409,
        );
        let d = await ok(`${p}/${intent.id}`, "GET", undefined, admin);
        assert.equal(d.reviews.length, 1);
        const evidence = d.evidence;
        await ok(
          `${p}/${intent.id}/review`,
          "POST",
          {
            ...review,
            version: d.version,
            marks: Object.fromEntries(
              d.snapshot.roster.map((l) => [l.id, "present"]),
            ),
            reason: "Corrected after checking the roll",
          },
          admin,
          201,
        );
        d = await ok(`${p}/${intent.id}`, "GET", undefined, admin);
        assert.equal(d.reviews.length, 2);
        assert.deepEqual(d.evidence, evidence);
        assert.equal(
          Object.values(d.reviews[0].marks).filter((v) => v === "absent")
            .length,
          1,
        );
        const list = await ok(
          `${p}?date=${d.attendance_date}`,
          "GET",
          undefined,
          teacher,
        );
        assert.equal(list.rows.length, 1);
        assert.deepEqual(
          list.totals.map((t) => ({ ...t, n: Number(t.n) })),
          [{ mark: "present", n: 3 }],
        );
        assert.equal(list.rows[0].evidence, undefined);
        assert.equal(
          (await req(`${p}?date=2026-02-31`, "GET", undefined, admin)).status,
          400,
        );
        await pg.query(
          "UPDATE learners SET group_id=$1 WHERE organisation_id=$2 AND group_id=$3",
          [outside.id, org, teacherGroup.id],
        );
        assert.equal(
          (await ok(`${p}/${intent.id}`, "GET", undefined, teacher)).snapshot
            .roster.length,
          3,
        );
      },
    );
    await t.test(
      "AI analysis is scoped, cached and never confirms attendance",
      async () => {
        const nativeFetch = globalThis.fetch,
          key = process.env.T4L_OPENAI_API_KEY,
          model = process.env.T4L_OPENAI_VISION_MODEL;
        let calls = 0;
        process.env.T4L_OPENAI_API_KEY = "synthetic-secret";
        process.env.T4L_OPENAI_VISION_MODEL = "synthetic-vision";
        globalThis.fetch = async (input, init) => {
          if (String(input) === "https://api.openai.com/v1/responses") {
            calls++;
            return Response.json({
              output: [
                {
                  content: [
                    {
                      type: "output_text",
                      text: JSON.stringify({
                        visible_people: null,
                        quality: "Synthetic test register",
                        warnings: [],
                        entries: [
                          {
                            code: detail.snapshot.roster[0].code,
                            name: detail.snapshot.roster[0].name,
                            mark: "absent",
                          },
                        ],
                      }),
                    },
                  ],
                },
              ],
            });
          }
          return nativeFetch(input, init);
        };
        try {
          const providers = await ok(
            p + "/ai/providers",
            "GET",
            undefined,
            admin,
          );
          assert.equal(providers.length, 4);
          assert.equal(providers[0].configured, true);
          assert.ok(!JSON.stringify(providers).includes("synthetic-secret"));
          assert.equal(
            (
              await req(
                `${p}/${intent.id}/analyse`,
                "POST",
                { provider: "openai", mode: "register" },
                teacher,
              )
            ).status,
            403,
          );
          assert.equal(
            (
              await req(
                `${p}/${intent.id}/analyse`,
                "POST",
                { provider: "openai", mode: "register" },
                otherAdmin,
              )
            ).status,
            404,
          );
          const before = await ok(`${p}/${intent.id}`, "GET", undefined, admin);
          const first = await ok(
            `${p}/${intent.id}/analyse`,
            "POST",
            { provider: "openai", mode: "register" },
            admin,
            201,
          );
          assert.equal(
            first.result.suggestions[detail.snapshot.roster[0].id],
            "absent",
          );
          await ok(
            `${p}/${intent.id}/analyse`,
            "POST",
            { provider: "openai", mode: "register" },
            admin,
            201,
          );
          assert.equal(calls, 1);
          const after = await ok(`${p}/${intent.id}`, "GET", undefined, admin);
          assert.deepEqual(after.marks, before.marks);
          assert.equal(after.version, before.version);
          assert.equal(
            (await ok(`${p}/${intent.id}/analyses`, "GET", undefined, admin))
              .length,
            1,
          );
          assert.equal(
            (await req(`${p}/${intent.id}/analyses`, "GET", undefined, teacher))
              .status,
            403,
          );
        } finally {
          globalThis.fetch = nativeFetch;
          if (key === undefined) delete process.env.T4L_OPENAI_API_KEY;
          else process.env.T4L_OPENAI_API_KEY = key;
          if (model === undefined) delete process.env.T4L_OPENAI_VISION_MODEL;
          else process.env.T4L_OPENAI_VISION_MODEL = model;
        }
      },
    );
    await t.test(
      "policy versioning, expired capture and scoped lists",
      async () => {
        const pol = await ok(p + "/policy", "GET", undefined, admin);
        assert.equal(
          (
            await req(
              p + "/policy",
              "PATCH",
              { ...pol, accuracy_limit: 75 },
              teacher,
            )
          ).status,
          403,
        );
        assert.equal(
          (
            await req(
              p + "/policy",
              "PATCH",
              { ...pol, timezone: "invalid-zone" },
              admin,
            )
          ).status,
          400,
        );
        await ok(
          p + "/policy",
          "PATCH",
          { ...pol, accuracy_limit: 75, self_review: false },
          admin,
        );
        assert.equal(
          (await req(p + "/policy", "PATCH", pol, admin)).status,
          409,
        );
        const fresh = await ok(
          p + "/captures",
          "POST",
          { group_id: outside.id },
          admin,
          201,
        );
        await pg.query(
          "UPDATE attendance_sessions SET created_at=now()-interval '11 minutes' WHERE id=$1",
          [fresh.id],
        );
        assert.equal(
          (await req(`${p}/captures/${fresh.id}/submit`, "POST", body, admin))
            .status,
          400,
        );
        const next = await ok(
          p + "/captures",
          "POST",
          { group_id: outside.id },
          admin,
          201,
        );
        await ok(
          `${p}/captures/${next.id}/submit`,
          "POST",
          { ...body, captured_at: new Date().toISOString() },
          admin,
          201,
        );
        const nd = await ok(`${p}/${next.id}`, "GET", undefined, admin);
        assert.equal(
          (await req(`${p}/${next.id}`, "GET", undefined, teacher)).status,
          404,
        );
        assert.equal(
          (
            await req(
              `${p}/${next.id}/review`,
              "POST",
              {
                version: nd.version,
                decision: "rejected",
                reason: "Cannot verify location",
                acknowledge_warnings: true,
              },
              admin,
            )
          ).status,
          403,
        );
        const list = await ok(
          `${p}?date=${nd.attendance_date}`,
          "GET",
          undefined,
          teacher,
        );
        assert.equal(list.rows.length, 1);
        const loginResponse = await req("/auth/login", "POST", {
          email: "owner@attendance.test",
          password: "Synthetic attendance password",
        });
        assert.equal(loginResponse.status, 200);
        const owner = loginResponse.headers.get("set-cookie").split(";")[0];
        await ok(
          `${p}/${next.id}/review`,
          "POST",
          {
            version: nd.version,
            decision: "rejected",
            reason: "Independent review: location cannot be verified",
            acknowledge_warnings: true,
          },
          owner,
          201,
        );
        const replacement = await ok(
          p + "/captures",
          "POST",
          { group_id: outside.id },
          admin,
          201,
        );
        await ok(
          `${p}/captures/${replacement.id}/submit`,
          "POST",
          { ...body, captured_at: new Date().toISOString() },
          admin,
          201,
        );
        await removeDemo(db, demo.datasetId);
        assert.equal(
          (await pg.query("SELECT * FROM attendance_sessions")).rows.length,
          0,
        );
      },
    );
  } finally {
    await app.close();
    await pg.close();
  }
});
