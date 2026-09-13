import "reflect-metadata";
import { test } from "node:test";
import assert from "node:assert/strict";
import { PGlite } from "@electric-sql/pglite";
import { randomUUID, randomBytes } from "node:crypto";
import { createApp } from "../dist/bootstrap.js";
import { migration } from "../dist/schema.js";
import { accessMigration } from "../dist/migration-access.js";
import { learnerMigration } from "../dist/migration-learners.js";
import { examEliteMigration } from "../dist/migration-examelite.js";
import { ExamEliteService } from "../dist/examelite.service.js";
import { FaceJobsService } from "../dist/face-jobs.service.js";
import { digest } from "../dist/security.js";

test("central sharing enforces superadmin, optimistic updates, stable scoped identities and revocation", async () => {
  const pg = new PGlite();
  await pg.exec(migration);
  await pg.exec(accessMigration);
  await pg.exec(learnerMigration);
  await pg.exec(examEliteMigration);
  const own = randomUUID(),
    other = randomUUID(),
    student = randomUUID(),
    foreign = randomUUID(),
    user = randomUUID(),
    ordinary = randomUUID(),
    centre = randomUUID(),
    group = randomUUID(),
    centre2 = randomUUID(),
    group2 = randomUUID();
  const token = randomBytes(32).toString("hex"),
    normalToken = randomBytes(32).toString("hex");
  await pg.query(
    "INSERT INTO organisations(id,name,slug) VALUES($1,'First','first'),($2,'Second','second')",
    [own, other],
  );
  await pg.query(
    "INSERT INTO users(id,email,name,password_hash,is_superadmin) VALUES($1,'admin@example.test','Admin','unused',true),($2,'member@example.test','Member','unused',false)",
    [user, ordinary],
  );
  await pg.query(
    "INSERT INTO sessions(token_hash,user_id,expires_at) VALUES($1,$2,now()+interval '1 hour'),($3,$4,now()+interval '1 hour')",
    [digest(token), user, digest(normalToken), ordinary],
  );
  for (const [org, c, g, l] of [
    [own, centre, group, student],
    [other, centre2, group2, foreign],
  ]) {
    await pg.query(
      "INSERT INTO centres(id,organisation_id,name) VALUES($1,$2,'Centre')",
      [c, org],
    );
    await pg.query(
      "INSERT INTO learning_groups(id,organisation_id,centre_id,name) VALUES($1,$2,$3,'Group')",
      [g, org, c],
    );
    await pg.query(
      "INSERT INTO learners(id,organisation_id,group_id,name,code) VALUES($1,$2,$3,'Student','ST-1')",
      [l, org, g],
    );
  }
  const db = {
    query: (q, p) => pg.query(q, p),
    transaction: (fn) =>
      pg.transaction((tx) => fn({ query: (q, p) => tx.query(q, p) })),
    onModuleDestroy: async () => {},
  };
  const app = await createApp(undefined, db);
  app.get(FaceJobsService).onModuleInit = () => {};
  const service = app.get(ExamEliteService);
  service.configuration = async () => ({
    central: true,
    organization_id: 1,
    token: "a".repeat(64),
  });
  let requests = [];
  service.request = async (c, org, path, body) => {
    requests.push({ org, path, body });
    if (path === "platform/validate-exams")
      return { valid: body.exam_ids.every((id) => id === 17) };
    if (path.endsWith("/learners/" + student))
      return {
        student_id: 140,
        learner_id: student,
        tech4learn_organisation_id: own,
      };
    return { version: 1, organization_id: 1, items: [], next: null };
  };
  await app.listen(0, "127.0.0.1");
  const base = (await app.getUrl()) + "/api/v1/platform/examelite";
  const call = (
    path = "",
    body,
    credential = token,
    origin = "http://localhost:5173",
  ) =>
    fetch(base + path, {
      method: body === undefined ? "GET" : "POST",
      headers: {
        Cookie: `t4l_session=${credential}`,
        Origin: origin,
        "Content-Type": "application/json",
        "X-Tech4Learn-Request": "1",
      },
      body: body === undefined ? undefined : JSON.stringify(body),
    });
  try {
    assert.equal((await call("", undefined, normalToken)).status, 403);
    assert.equal((await call("", undefined, "invalid")).status, 401);
    assert.equal(requests.length, 0);
    assert.equal((await call()).status, 200);
    const path = "/organisations/" + own,
      grant = { enabled: true, exam_ids: [17], revision: 0 };
    assert.equal(
      (await call(path, grant, token, "https://evil.example")).status,
      403,
    );
    assert.equal((await call(path, grant, normalToken)).status, 403);
    assert.equal((await call(path, { ...grant, exam_ids: [99] })).status, 400);
    assert.equal((await call(path, grant)).status, 201);
    assert.equal((await call(path, grant)).status, 409);
    const count = requests.length;
    assert.equal((await call(path + "/learners/" + foreign, {})).status, 404);
    assert.equal(requests.length, count);
    assert.equal((await call(path + "/learners/" + student, {})).status, 201);
    assert.equal((await call(path + "/learners/" + student, {})).status, 201);
    assert.equal(
      (await pg.query("SELECT * FROM examelite_students")).rows.length,
      1,
    );
    assert.equal(
      (
        await pg.query(
          "SELECT * FROM audit_events WHERE action='examelite.student.connected'",
        )
      ).rows.length,
      1,
    );
    assert.deepEqual(
      requests.find((r) => r.path.endsWith("/learners/" + student)).body,
      { name: "Student" },
    );
    assert.equal((await (await call(path)).json()).students[0].connected, true);
    await assert.rejects(
      pg.query(
        "INSERT INTO examelite_students(organisation_id,learner_id,external_organisation_id,external_student_id) VALUES($1,$2,1,141)",
        [own, foreign],
      ),
    );
    assert.equal(
      (await call(path, { enabled: false, exam_ids: [17], revision: 1 }))
        .status,
      201,
    );
    const revoked = requests.length;
    assert.equal((await call(path + "/learners/" + student, {})).status, 403);
    assert.equal(requests.length, revoked);
    await pg.query("UPDATE users SET is_superadmin=false WHERE id=$1", [user]);
    assert.equal((await call()).status, 403);
    assert.equal(requests.length, revoked);
  } finally {
    await app.close();
    await pg.close();
  }
});
