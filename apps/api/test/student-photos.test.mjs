import { test } from "node:test";
import assert from "node:assert/strict";
import { randomUUID } from "node:crypto";
import { createServer } from "node:http";
import { PGlite } from "@electric-sql/pglite";
import { migration } from "../dist/schema.js";
import { accessMigration } from "../dist/migration-access.js";
import { learnerMigration } from "../dist/migration-learners.js";
import { configurationMigration } from "../dist/migration-configuration.js";
import { attendanceMigration } from "../dist/migration-attendance.js";
import { visionMigration } from "../dist/migration-vision.js";
import { academicMigration } from "../dist/migration-academic.js";
import { photoMigration } from "../dist/migration-photos.js";
import { createApp } from "../dist/bootstrap.js";
import { createDemo, seedDemoLearners, removeDemo } from "../dist/demo.js";
import { hashPassword } from "../dist/security.js";
import { resolveFaces } from "../dist/face-matching.service.js";
import { photoFile } from "../dist/learner-photos.service.js";
const box = { x_min: 10, y_min: 10, x_max: 150, y_max: 150, probability: 0.99 };
const photo = Buffer.from([
  255, 216, 255, 192, 0, 17, 8, 0, 200, 0, 200, 3, 1, 17, 0, 2, 17, 0, 3, 17, 0,
  255, 218, 0, 12, 3, 1, 0, 2, 0, 3, 0, 0, 63, 0, 0, 255, 217,
]).toString("base64");
test("face suggestions reject ambiguity and duplicate identities; bounded photo container strips metadata", () => {
  assert.equal(
    resolveFaces([{ box, scores: { a: 0.95, b: 0.94 } }], 0.9, 0.1)[0]
      .learnerId,
    null,
  );
  assert.equal(
    resolveFaces([{ box, scores: { a: 0.95, b: 0.6 } }], 0.9, 0.1)[0].learnerId,
    "a",
  );
  assert.ok(
    resolveFaces(
      [
        { box, scores: { a: 0.95 } },
        { box, scores: { a: 0.96 } },
      ],
      0.9,
      0.1,
    ).every((r) => r.learnerId === null),
  );
  assert.equal(
    resolveFaces([{ box, scores: { a: 0.5 } }], 0.9, 0.1)[0].learnerId,
    null,
  );
  const bytes = Buffer.from(photo, "base64"),
    withMeta = Buffer.concat([
      bytes.subarray(0, 2),
      Buffer.from([255, 225, 0, 6, 65, 66, 67, 68]),
      bytes.subarray(2),
    ]);
  assert.deepEqual(photoFile(withMeta.toString("base64")).content, bytes);
  assert.throws(() => photoFile("bad"));
  const small = Buffer.from(bytes);
  small[8] = 1;
  assert.throws(() => photoFile(small.toString("base64")));
});
test("private student photos, consent withdrawal and stateless face drafts through HTTP", async (t) => {
  const pg = new PGlite();
  let app, engine;
  let failEngine = false,
    multi = false,
    seen = [];
  let onVerify;
  const envKeys = [
    "T4L_FACE_VERIFY_URL",
    "T4L_FACE_VERIFY_KEY",
    "T4L_FACE_MODEL",
    "T4L_FACE_ORGANISATIONS",
    "T4L_FACE_THRESHOLD",
    "T4L_FACE_MARGIN",
  ];
  const before = Object.fromEntries(envKeys.map((k) => [k, process.env[k]]));
  try {
    for (const s of [
      migration,
      accessMigration,
      learnerMigration,
      configurationMigration,
      attendanceMigration,
      visionMigration,
      academicMigration,
      photoMigration,
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
      "INSERT INTO users(id,email,name,password_hash,is_superadmin) VALUES($1,'photo-owner@example.test','Synthetic owner',$2,true)",
      [randomUUID(), await hashPassword("Synthetic photo test password")],
    );
    const demo = await createDemo(db);
    await seedDemoLearners(db, demo.datasetId);
    const org = demo.organisations[0].id,
      other = demo.organisations[1].id;
    await pg.query(
      `INSERT INTO organisation_settings(organisation_id,enabled_modules) VALUES($1,'{"learners":true,"attendance":true}'::jsonb)`,
      [org],
    );
    const l = (
      await pg.query(
        "SELECT id,group_id FROM learners WHERE organisation_id=$1 LIMIT 1",
        [org],
      )
    ).rows[0];
    // Isolated parser/transport fixture only, no real person or recognition accuracy assertion.
    await pg.query("UPDATE learners SET demo=false WHERE id=$1", [l.id]);
    app = await createApp(undefined, db);
    await app.listen(0, "127.0.0.1");
    const base = (await app.getUrl()) + "/api/v1";
    const login = await fetch(base + "/auth/login", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Origin: "http://localhost:5173",
        "X-Tech4Learn-Request": "1",
      },
      body: JSON.stringify({
        email: "photo-owner@example.test",
        password: "Synthetic photo test password",
      }),
    });
    assert.equal(login.status, 200);
    const cookie = login.headers.get("set-cookie").split(";")[0];
    const req = async (path, method = "GET", body) => {
      const r = await fetch(base + path, {
        method,
        headers: {
          Cookie: cookie,
          Origin: "http://localhost:5173",
          "Content-Type": "application/json",
          "X-Tech4Learn-Request": "1",
        },
        body: body === undefined ? undefined : JSON.stringify(body),
      });
      return { status: r.status, body: await r.json().catch(() => null) };
    };
    const path = `/organisations/${org}/learners/${l.id}`;
    await t.test(
      "permissions, foreign IDs, consent versions and deletion",
      async () => {
        assert.equal(
          (await req(`/organisations/${other}/learners/${l.id}/photos`)).status,
          404,
        );
        assert.equal(
          (
            await req(path + "/photos", "POST", {
              purpose: "profile",
              photo,
              consentVersion: 0,
            })
          ).status,
          409,
        );
        assert.equal(
          (
            await req(path + "/photo-consent", "POST", {
              purpose: "profile",
              granted: true,
              version: 0,
              attested: true,
            })
          ).status,
          201,
        );
        const first = await req(path + "/photos", "POST", {
          purpose: "profile",
          photo,
          consentVersion: 1,
        });
        assert.equal(first.status, 201);
        assert.equal(
          (
            await req(path + "/photos", "POST", {
              purpose: "profile",
              photo,
              consentVersion: 1,
            })
          ).body.id,
          first.body.id,
        );
        const media = await fetch(base + path + "/photos/" + first.body.id, {
          headers: { Cookie: cookie },
        });
        assert.equal(media.status, 200);
        assert.equal(media.headers.get("cache-control"), "no-store");
        assert.equal(
          (await fetch(base + path + "/photos/" + first.body.id)).status,
          401,
        );
        assert.equal(
          (await req(path + `/photos/${first.body.id}/check`, "POST", {}))
            .status,
          400,
        );
        const staffId = randomUUID(),
          roleId = randomUUID(),
          otherGroup = (
            await pg.query(
              "SELECT id FROM learning_groups WHERE organisation_id=$1 AND id<>$2 LIMIT 1",
              [org, l.group_id],
            )
          ).rows[0].id;
        await pg.query(
          "INSERT INTO users(id,email,name,password_hash) SELECT $1,'scoped-photo@example.test','Synthetic scoped staff',password_hash FROM users WHERE email='photo-owner@example.test'",
          [staffId],
        );
        await pg.query(
          "INSERT INTO access_roles(id,organisation_id,name,permissions,protected) VALUES($1,$2,'Synthetic photo viewer',$3,false)",
          [
            roleId,
            org,
            [
              "organisation.view",
              "groups.view",
              "learners.view",
              "learners.photos",
            ],
          ],
        );
        await pg.query(
          "INSERT INTO memberships(user_id,organisation_id,role,role_id,scope_type,scope_ids) VALUES($1,$2,'organisation_admin',$3,'groups',$4)",
          [staffId, org, roleId, [otherGroup]],
        );
        const staffLogin = await fetch(base + "/auth/login", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Origin: "http://localhost:5173",
            "X-Tech4Learn-Request": "1",
          },
          body: JSON.stringify({
            email: "scoped-photo@example.test",
            password: "Synthetic photo test password",
          }),
        });
        const staffCookie = staffLogin.headers.get("set-cookie").split(";")[0];
        const read = () =>
          fetch(base + path + "/photos/" + first.body.id, {
            headers: { Cookie: staffCookie },
          });
        assert.equal((await read()).status, 404);
        await pg.query("UPDATE memberships SET scope_ids=$1 WHERE user_id=$2", [
          [l.group_id],
          staffId,
        ]);
        assert.equal((await read()).status, 200);
        await pg.query(
          "UPDATE access_roles SET permissions=array_remove(permissions,'learners.photos') WHERE id=$1",
          [roleId],
        );
        assert.equal((await read()).status, 403);

        assert.equal(
          (
            await req(path + "/photo-consent", "POST", {
              purpose: "profile",
              granted: false,
              version: 0,
              attested: true,
            })
          ).status,
          409,
        );
        assert.equal(
          (
            await req(path + "/photo-consent", "POST", {
              purpose: "profile",
              granted: false,
              version: 1,
              attested: true,
            })
          ).status,
          201,
        );
        assert.equal(
          (await req(path + "/photos/" + first.body.id)).status,
          404,
        );
      },
    );
    engine = createServer(async (req, res) => {
      let raw = "";
      for await (const b of req) raw += b;
      seen.push({
        url: req.url,
        key: req.headers["x-api-key"],
        body: JSON.parse(raw),
      });
      if (failEngine) {
        res.writeHead(503).end();
        return;
      }
      if (onVerify) await onVerify();
      const source = {
        source_image_face: { box },
        face_matches: [{ box, similarity: 0.98 }],
      };
      res.setHeader("Content-Type", "application/json");
      res.end(JSON.stringify({ result: multi ? [source, source] : [source] }));
    });
    await new Promise((r) => engine.listen(0, "127.0.0.1", r));
    process.env.T4L_FACE_VERIFY_URL = `http://127.0.0.1:${engine.address().port}`;
    process.env.T4L_FACE_VERIFY_KEY = "synthetic-private-service-key";
    process.env.T4L_FACE_MODEL = "synthetic-contract-model";
    process.env.T4L_FACE_ORGANISATIONS = org;
    await req(path + "/photo-consent", "POST", {
      purpose: "reference",
      granted: true,
      version: 0,
      attested: true,
    });
    const ref = (
      await req(path + "/photos", "POST", {
        purpose: "reference",
        photo,
        consentVersion: 1,
      })
    ).body.id;
    await t.test(
      "single-face validation and engine failures never mark a reference checked",
      async () => {
        multi = true;
        assert.equal(
          (await req(path + `/photos/${ref}/check`, "POST", {})).status,
          400,
        );
        multi = false;
        failEngine = true;
        assert.equal(
          (await req(path + `/photos/${ref}/check`, "POST", {})).status,
          503,
        );
        failEngine = false;
        assert.equal(
          (await req(path + "/photos")).body.photos[0].checked,
          false,
        );
        assert.equal(
          (await req(path + `/photos/${ref}/check`, "POST", {})).status,
          201,
        );
        assert.ok(
          seen.every(
            (s) =>
              s.url.startsWith("/api/v1/verification/verify?") &&
              !s.url.includes("face_plugins") &&
              s.key === "synthetic-private-service-key" &&
              s.body.source_image === photo,
          ),
        );
      },
    );
    const centre = (
      await pg.query("SELECT centre_id FROM learning_groups WHERE id=$1", [
        l.group_id,
      ])
    ).rows[0].centre_id;
    const owner = (
        await pg.query(
          "SELECT id FROM users WHERE email='photo-owner@example.test'",
        )
      ).rows[0].id,
      session = randomUUID();
    await pg.query(
      `INSERT INTO attendance_sessions(id,organisation_id,group_id,centre_id,actor_id,status,snapshot,attendance_date) VALUES($1,$2,$3,$4,$5,'pending',$6,'2026-09-12')`,
      [
        session,
        org,
        l.group_id,
        centre,
        owner,
        JSON.stringify({
          roster: [{ id: l.id, name: "Synthetic student", code: "SYNTHETIC" }],
          fields: [],
        }),
      ],
    );
    await pg.query(
      "INSERT INTO attendance_photos(session_id,organisation_id,content) VALUES($1,$2,$3)",
      [session, org, Buffer.from(photo, "base64")],
    );
    await t.test(
      "matching returns scoped drafts only; withdrawal prevents reuse",
      async () => {
        const match = await req(
          `/organisations/${org}/attendance/${session}/face-match`,
          "POST",
          {},
        );
        assert.equal(match.status, 201);
        assert.equal(match.body.faces[0].learnerId, l.id);
        assert.deepEqual(
          (
            await pg.query(
              "SELECT marks FROM attendance_sessions WHERE id=$1",
              [session],
            )
          ).rows[0].marks,
          {},
        );
        onVerify = async () => {
          onVerify = undefined;
          await req(path + "/photo-consent", "POST", {
            purpose: "reference",
            granted: false,
            version: 1,
            attested: true,
          });
        };
        assert.equal(
          (
            await req(
              `/organisations/${org}/attendance/${session}/face-match`,
              "POST",
              {},
            )
          ).status,
          409,
        );
        assert.equal(
          (
            await req(
              `/organisations/${org}/attendance/${session}/face-match`,
              "POST",
              {},
            )
          ).status,
          400,
        );
        assert.equal((await req(path + "/photos")).body.photos.length, 0);
      },
    );
    await removeDemo(db, demo.datasetId);
  } finally {
    if (app) await app.close();
    if (engine) await new Promise((r) => engine.close(r));
    await pg.close();
    for (const k of envKeys) {
      if (before[k] === undefined) delete process.env[k];
      else process.env[k] = before[k];
    }
  }
});
