import "reflect-metadata";
import { test } from "node:test";
import assert from "node:assert/strict";
import { randomUUID } from "node:crypto";
import { PGlite } from "@electric-sql/pglite";
import { migration } from "../dist/schema.js";
import { accessMigration } from "../dist/migration-access.js";
import { learnerMigration } from "../dist/migration-learners.js";
import { configurationMigration } from "../dist/migration-configuration.js";
import { examWorkspaceMigration } from "../dist/migration-exam-workspace.js";
import { examStudentAccessMigration } from "../dist/migration-exam-student-access.js";
import { createApp } from "../dist/bootstrap.js";
import { ExamContentService } from "../dist/exam-content.service.js";
import { ExamEliteService } from "../dist/examelite.service.js";
import { FaceJobsService } from "../dist/face-jobs.service.js";
import { digest, token } from "../dist/security.js";
test("student exam links are single-use, paper-scoped and immediately revocable without staff membership", async () => {
  const pg = new PGlite();
  for (const m of [
    migration,
    accessMigration,
    learnerMigration,
    configurationMigration,
    examWorkspaceMigration,
    examStudentAccessMigration,
  ])
    await pg.exec(m);
  const org = randomUUID(),
    other = randomUUID(),
    admin = randomUUID(),
    learner = randomUUID(),
    foreign = randomUUID(),
    centre = randomUUID(),
    group = randomUUID(),
    session = token();
  await pg.query(
    "INSERT INTO organisations(id,name,slug) VALUES($1,'Academy','academy'),($2,'Other','other')",
    [org, other],
  );
  await pg.query(
    "INSERT INTO users(id,email,name,password_hash,is_superadmin) VALUES($1,'admin@example.test','Admin','unused',true)",
    [admin],
  );
  await pg.query(
    "INSERT INTO sessions(token_hash,user_id,expires_at) VALUES($1,$2,now()+interval '1 hour')",
    [digest(session), admin],
  );
  await pg.query(
    'INSERT INTO organisation_settings(organisation_id,enabled_modules) VALUES($1,\'{"exams":true,"learners":true}\')',
    [org],
  );
  await pg.query(
    "INSERT INTO centres(id,organisation_id,name) VALUES($1,$2,'Centre')",
    [centre, org],
  );
  await pg.query(
    "INSERT INTO learning_groups(id,organisation_id,centre_id,name) VALUES($1,$2,$3,'Group')",
    [group, org, centre],
  );
  await pg.query(
    "INSERT INTO learners(id,organisation_id,group_id,code,name) VALUES($1,$2,$3,'A1','Synthetic student')",
    [learner, org, group],
  );
  const db = {
    query: (q, p) => pg.query(q, p),
    transaction: (fn) =>
      pg.transaction((tx) => fn({ query: (q, p) => tx.query(q, p) })),
    onModuleDestroy: async () => {},
  };
  const app = await createApp(undefined, db);
  app.get(FaceJobsService).onModuleInit = () => {};
  app.get(ExamContentService).taxonomy = async (user, owner, kind, id) => {
    assert.equal(owner, org);
    assert.equal(kind, "exams");
    return { id: Number(id), fields: { name: "Synthetic native exam" } };
  };
  await app.listen(0, "127.0.0.1");
  const base = (await app.getUrl()) + "/api/v1";
  const staff = "t4l_session=" + session,
    root = `/organisations/${org}`;
  const call = (path, body, cookie = staff, origin = "http://localhost:5173") =>
    fetch(base + path, {
      method: body === undefined ? "GET" : "POST",
      headers: {
        Cookie: cookie,
        Origin: origin,
        "Content-Type": "application/json",
        "X-Tech4Learn-Request": "1",
      },
      body: body === undefined ? undefined : JSON.stringify(body),
    });
  const issue = async () => {
    const r = await call(root + "/exam-student-access", {
      learner_id: learner,
      exam_id: "7",
      hours: 24,
    });
    assert.equal(r.status, 201);
    return r.json();
  };
  try {
    assert.equal(
      (
        await call(root + "/exam-student-access", {
          learner_id: foreign,
          exam_id: "7",
          hours: 24,
        })
      ).status,
      404,
    );
    assert.equal(
      (
        await call(
          root + "/exam-student-access",
          { learner_id: learner, exam_id: "7", hours: 24 },
          "",
        )
      ).status,
      401,
    );
    const grant = await issue();
    const secret = grant.fragment.split(".")[1];
    assert.match(secret, /^[a-f0-9]{64}$/);
    const stored = (
      await pg.query("SELECT * FROM exam_student_grants WHERE id=$1", [
        grant.id,
      ])
    ).rows[0];
    assert.equal(stored.token_hash, digest(secret));
    assert.ok(!JSON.stringify(stored).includes(secret));
    const list = await (
      await call(root + "/exam-student-access?learner=" + learner)
    ).json();
    assert.ok(!JSON.stringify(list).includes("token"));
    assert.equal(
      (
        await call(
          `/organisations/${other}/student-exam/exchange`,
          { token: secret },
          "",
        )
      ).status,
      401,
    );
    assert.equal(
      (
        await call(
          root + "/student-exam/exchange",
          { token: secret },
          "",
          "https://evil.test",
        )
      ).status,
      403,
    );
    const response = await call(
      root + "/student-exam/exchange",
      { token: secret },
      "",
    );
    assert.equal(response.status, 200);
    assert.match(response.headers.get("set-cookie"), /HttpOnly/);
    assert.match(response.headers.get("set-cookie"), /SameSite=Lax/);
    const studentCookie = response.headers.get("set-cookie").split(";")[0];
    const engine = app.get(ExamEliteService);
    engine.configuration = async () => ({
      central: true,
      organization_id: 10,
      token: "synthetic",
    });
    let calls = 0;
    engine.request = async (config, owner, path, payload) => {
      calls++;
      assert.equal(owner, org);
      assert.equal(path, `student/${org}/start`);
      assert.equal(payload.learner_id, learner);
      assert.equal(payload.exam_id, 7);
      assert.equal(payload.name, "Synthetic student");
      return {
        data: { attempt_id: 11, exam_id: 7, completed: true, result: null },
      };
    };
    const startPath = root + "/student-exam/attempt/start";
    assert.equal(
      (await call(startPath, { request_id: randomUUID() }, staff)).status,
      401,
    );
    assert.equal(calls, 0);
    assert.equal(
      (
        await call(
          startPath,
          { request_id: randomUUID(), learner_id: foreign },
          studentCookie,
        )
      ).status,
      400,
    );
    assert.equal(
      (
        await call(
          startPath,
          { request_id: randomUUID(), exam_id: 99 },
          studentCookie,
        )
      ).status,
      400,
    );
    assert.equal(
      (
        await call(
          startPath,
          { request_id: randomUUID() },
          studentCookie,
          "https://evil.test",
        )
      ).status,
      403,
    );
    assert.equal(calls, 0);
    const startReply = await call(
      startPath,
      { request_id: randomUUID() },
      studentCookie,
    );
    assert.equal(startReply.status, 200);
    assert.deepEqual(await startReply.json(), {
      attempt_id: 11,
      exam_id: 7,
      completed: true,
      result: null,
    });
    assert.equal(calls, 1);
    const answerBody = {
      request_id: randomUUID(),
      attempt_id: 11,
      question_id: 4,
      revision: "a".repeat(64),
      fields: { option_selected: "7" },
    };
    engine.request = async (config, owner, path, payload) => {
      assert.equal(path, `student/${org}/answer`);
      assert.equal(payload.exam_id, 7);
      assert.equal(payload.learner_id, learner);
      assert.deepEqual(payload.fields, answerBody);
      return {
        data: {
          saved: true,
          question_id: 4,
          revision: "b".repeat(64),
          answer_locked: false,
        },
      };
    };
    assert.equal(
      (
        await call(
          root + "/student-exam/attempt/answer",
          answerBody,
          studentCookie,
        )
      ).status,
      200,
    );
    engine.request = async () => ({
      data: {
        saved: true,
        question_id: 99,
        revision: "b".repeat(64),
        answer_locked: false,
      },
    });
    assert.equal(
      (
        await call(
          root + "/student-exam/attempt/answer",
          answerBody,
          studentCookie,
        )
      ).status,
      503,
    );
    engine.request = async () => ({
      data: { attempt_id: 12, exam_id: 7, completed: true, result: null },
    });
    assert.equal(
      (
        await call(
          root + "/student-exam/attempt/submit",
          { request_id: randomUUID(), attempt_id: 11 },
          studentCookie,
        )
      ).status,
      503,
    );
    engine.request = async () => ({
      error: { status: 409, message: "PRIVATE SQL STACK" },
    });
    const conflict = await call(
      startPath,
      { request_id: randomUUID() },
      studentCookie,
    );
    assert.equal(conflict.status, 409);
    assert.ok(!(await conflict.text()).includes("PRIVATE"));
    engine.request = async () => ({
      data: { attempt_id: 11, exam_id: 99, completed: true, result: null },
    });
    assert.equal(
      (await call(startPath, { request_id: randomUUID() }, studentCookie))
        .status,
      503,
    );
    engine.request = async () => {
      await pg.query("UPDATE learners SET archived=true WHERE id=$1", [
        learner,
      ]);
      return {
        data: { attempt_id: 11, exam_id: 7, completed: true, result: null },
      };
    };
    assert.equal(
      (await call(startPath, { request_id: randomUUID() }, studentCookie))
        .status,
      401,
    );
    await pg.query("UPDATE learners SET archived=false WHERE id=$1", [learner]);
    assert.deepEqual(await response.json(), { ok: true });
    assert.equal(
      (await call(root + "/student-exam/exchange", { token: secret }, ""))
        .status,
      401,
    );
    assert.equal(
      (
        await call(
          root + "/student-exam/exchange",
          { token: secret },
          studentCookie,
        )
      ).status,
      200,
    );
    const me = await (
      await call(root + "/student-exam/me", undefined, studentCookie)
    ).json();
    assert.equal(me.student_name, "Synthetic student");
    assert.equal(me.exam_name, "Synthetic native exam");
    assert.ok(!("learner_id" in me));
    assert.equal(
      (
        await call(
          `/organisations/${other}/student-exam/me`,
          undefined,
          studentCookie,
        )
      ).status,
      401,
    );
    assert.equal(
      (
        await call(
          root + "/exam-student-access",
          { learner_id: learner, exam_id: "8", hours: 24 },
          studentCookie,
        )
      ).status,
      401,
    );
    assert.equal(
      (await call("/auth/me", undefined, studentCookie)).status,
      401,
    );
    assert.equal(
      Number((await pg.query("SELECT count(*) AS n FROM users")).rows[0].n),
      1,
    );
    assert.equal(
      Number(
        (await pg.query("SELECT count(*) AS n FROM memberships")).rows[0].n,
      ),
      0,
    );
    const replacement = await issue();
    assert.equal(
      (await call(root + "/student-exam/me", undefined, studentCookie)).status,
      401,
    );
    const second = await call(
      root + "/student-exam/exchange",
      { token: replacement.fragment.split(".")[1] },
      "",
    );
    const secondCookie = second.headers.get("set-cookie").split(";")[0];
    assert.equal(second.status, 200);
    await pg.query(
      "UPDATE organisation_settings SET enabled_modules='{}' WHERE organisation_id=$1",
      [org],
    );
    assert.equal(
      (await call(root + "/student-exam/me", undefined, secondCookie)).status,
      403,
    );
    await pg.query(
      "UPDATE organisation_settings SET enabled_modules='{\"exams\":true}' WHERE organisation_id=$1",
      [org],
    );
    await pg.query(
      "INSERT INTO examelite_workspaces(organisation_id,restrictions) VALUES($1,ARRAY['taking'])",
      [org],
    );
    assert.equal(
      (await call(root + "/student-exam/me", undefined, secondCookie)).status,
      403,
    );
    await pg.query(
      "UPDATE examelite_workspaces SET restrictions='{}' WHERE organisation_id=$1",
      [org],
    );
    await pg.query("UPDATE learners SET archived=true WHERE id=$1", [learner]);
    assert.equal(
      (await call(root + "/student-exam/me", undefined, secondCookie)).status,
      401,
    );
    await pg.query("UPDATE learners SET archived=false WHERE id=$1", [learner]);
    assert.equal(
      (await call(root + `/exam-student-access/${replacement.id}/revoke`, {}))
        .status,
      201,
    );
    assert.equal(
      (await call(root + "/student-exam/me", undefined, secondCookie)).status,
      401,
    );
    const expiring = await issue();
    await pg.query(
      "UPDATE exam_student_grants SET expires_at=now()-interval '1 second' WHERE id=$1",
      [expiring.id],
    );
    assert.equal(
      (
        await call(
          root + "/student-exam/exchange",
          { token: expiring.fragment.split(".")[1] },
          "",
        )
      ).status,
      401,
    );
    const concurrent = await issue();
    const exchanges = await Promise.all(
      [1, 2].map(() =>
        call(
          root + "/student-exam/exchange",
          { token: concurrent.fragment.split(".")[1] },
          "",
        ),
      ),
    );
    assert.deepEqual(exchanges.map((r) => r.status).sort(), [200, 401]);
    const winningCookie = exchanges
      .find((r) => r.status === 200)
      .headers.get("set-cookie")
      .split(";")[0];
    await pg.query(
      "UPDATE exam_student_sessions SET expires_at=now()-interval '1 second' WHERE grant_id=$1",
      [concurrent.id],
    );
    assert.equal(
      (await call(root + "/student-exam/me", undefined, winningCookie)).status,
      401,
    );
    const audit = JSON.stringify(
      (await pg.query("SELECT details FROM audit_events")).rows,
    );
    assert.ok(!audit.includes(secret));
  } finally {
    await app.close();
    await pg.close();
  }
});
