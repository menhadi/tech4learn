import "reflect-metadata";
import { test } from "node:test";
import assert from "node:assert/strict";
import { randomUUID, randomBytes } from "node:crypto";
import { PGlite } from "@electric-sql/pglite";
import { createApp } from "../dist/bootstrap.js";
import {
  FaceControlClient,
  FaceControlService,
} from "../dist/face-control.service.js";
import { FaceJobsService } from "../dist/face-jobs.service.js";
import { migration } from "../dist/schema.js";
import { faceControlMigration } from "../dist/migration-face-control.js";
import { digest } from "../dist/security.js";

test("engine controls enforce stored superadmin identity, CSRF, worker exclusion and durable failure pause", async () => {
  const pg = new PGlite();
  await pg.exec(migration);
  await pg.exec(`ALTER TABLE audit_events ADD COLUMN details jsonb DEFAULT '{}';
    CREATE TABLE attendance_face_worker(id integer PRIMARY KEY,job_id uuid,lease_until timestamptz);
    INSERT INTO attendance_face_worker(id) VALUES(1);`);
  await pg.exec(faceControlMigration);
  const db = {
    query: (q, p) => pg.query(q, p),
    transaction: (fn) =>
      pg.transaction((tx) => fn({ query: (q, p) => tx.query(q, p) })),
    onModuleDestroy: async () => {},
  };
  const id = randomUUID(),
    token = randomBytes(32).toString("hex");
  await pg.query(
    "INSERT INTO users(id,email,name,password_hash,is_superadmin) VALUES($1,'owner@example.test','Test','unused',true)",
    [id],
  );
  await pg.query(
    "INSERT INTO sessions(token_hash,user_id,expires_at) VALUES($1,$2,now()+interval '1 hour')",
    [digest(token), id],
  );
  const app = await createApp(undefined, db);
  // Prevent the normal polling fixture from asking for unrelated module tables.
  app.get(FaceJobsService).onModuleInit = () => {};
  let calls = [];
  app.get(FaceControlClient).call = async (input) => {
    calls.push(input);
    return { running: true, ready: true };
  };
  await app.listen(0, "127.0.0.1");
  const base = await app.getUrl();
  const req = (method = "GET", body, extra = {}) =>
    fetch(base + "/api/v1/platform/face-engine", {
      method,
      headers: {
        Cookie: `t4l_session=${token}`,
        Origin: "http://localhost:5173",
        "Content-Type": "application/json",
        "X-Tech4Learn-Request": "1",
        ...extra,
      },
      body: body ? JSON.stringify(body) : undefined,
    });
  try {
    assert.equal((await req()).status, 200);
    calls = [];
    assert.equal((await req("GET", undefined, { Cookie: "" })).status, 401);
    assert.equal(
      (
        await req(
          "POST",
          { action: "stop", confirm: true },
          { Origin: "https://attacker.example" },
        )
      ).status,
      403,
    );
    const service = app.get(FaceControlService),
      user = { id, is_superadmin: true };
    await assert.rejects(
      () =>
        service.change(
          { ...user, is_superadmin: false },
          { action: "stop", confirm: true },
        ),
      (e) => e.status === 403,
    );
    await assert.rejects(
      () => service.change(user, { action: "exec", confirm: true }),
      (e) => e.status === 400,
    );
    await assert.rejects(
      () => service.change(user, { action: "stop" }),
      (e) => e.status === 400,
    );
    assert.equal(calls.length, 0);
    await pg.query(
      "UPDATE attendance_face_worker SET lease_until=now()+interval '60 seconds'",
    );
    await assert.rejects(
      () => service.change(user, { action: "stop", confirm: true }),
      (e) => e.status === 409,
    );
    assert.equal(calls.length, 0);
    await pg.query("UPDATE attendance_face_worker SET lease_until=NULL");
    await service.change(user, {
      action: "start",
      confirm: true,
      container: "examelite",
    });
    assert.deepEqual(calls, [{ action: "start" }]);
    assert.equal(
      (await pg.query("SELECT paused FROM attendance_face_worker")).rows[0]
        .paused,
      false,
    );
    app.get(FaceControlClient).call = async () => {
      throw new Error("private backend detail");
    };
    await assert.rejects(
      () => service.change(user, { action: "restart", confirm: true }),
      (e) => e.status === 503 && !e.message.includes("private"),
    );
    assert.equal(
      (await pg.query("SELECT paused FROM attendance_face_worker")).rows[0]
        .paused,
      true,
    );
    assert.equal(
      (
        await pg.query(
          "SELECT count(*)::int AS n FROM audit_events WHERE action='face_engine.failed'",
        )
      ).rows[0].n,
      1,
    );
    app.get(FaceControlClient).call = async () => ({
      running: true,
      ready: false,
    });
    assert.equal(
      (await service.change(user, { action: "start", confirm: true })).paused,
      true,
    );
    app.get(FaceControlClient).call = async () => ({
      running: true,
      ready: true,
    });
    assert.equal(
      (await service.change(user, { action: "start", confirm: true })).paused,
      false,
    );
    // Stored role changes take effect without accepting a client-supplied superadmin claim.
    await pg.query("UPDATE users SET is_superadmin=false WHERE id=$1", [id]);
    assert.equal(
      (
        await req("POST", {
          action: "stop",
          confirm: true,
          is_superadmin: true,
        })
      ).status,
      403,
    );
  } finally {
    await app.close();
    await pg.close();
  }
});

test("paused worker retains queued work without claiming it", async () => {
  let called = [];
  const db = {
    query: async (sql) => {
      called.push(sql);
      return { rows: [{ version: 9 }] };
    },
    transaction: async (fn) =>
      fn({
        query: async (sql) => {
          called.push(sql);
          assert.match(sql, /attendance_face_worker/);
          return { rows: [{ paused: true, job_id: null, lease_until: null }] };
        },
      }),
  };
  await new FaceJobsService(db, {}).tick();
  assert.equal(
    called.some((sql) => sql.includes("status='queued' ORDER BY")),
    false,
  );
});

test("busy control lock fails immediately without contacting the host", async () => {
  const service = new FaceControlService(
    {
      transaction: async () => {
        throw Object.assign(new Error("lock unavailable"), { code: "55P03" });
      },
    },
    { call: async () => assert.fail("must not call host") },
  );
  await assert.rejects(
    () =>
      service.change(
        { is_superadmin: true },
        { action: "stop", confirm: true },
      ),
    (e) => e.status === 409,
  );
});
