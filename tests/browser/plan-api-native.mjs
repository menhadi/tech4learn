// Real Nest HTTP and registered native plan controllers; synthetic databases only.
import "reflect-metadata";
import assert from "node:assert/strict";
import { spawn } from "node:child_process";
import { createInterface } from "node:readline";
import { randomUUID } from "node:crypto";
import { fileURLToPath } from "node:url";
import { PGlite } from "@electric-sql/pglite";
import { migration } from "../../apps/api/dist/schema.js";
import { createApp } from "../../apps/api/dist/bootstrap.js";
import { ExamEliteService } from "../../apps/api/dist/examelite.service.js";
import { FaceJobsService } from "../../apps/api/dist/face-jobs.service.js";
import { digest, token } from "../../apps/api/dist/security.js";
if (process.argv.length !== 6 || process.env.NODE_ENV === "production")
  throw Error(
    "Use node tests/browser/plan-api-native.mjs VENDOR MODELS QUESTION_CONTROLLER SAAS_CONTROLLER in development only",
  );
const php = spawn(
  "php",
  [
    fileURLToPath(
      new URL(
        "../../deploy/examelite/test-plan-api-bridge.php",
        import.meta.url,
      ),
    ),
    ...process.argv.slice(2),
  ],
  { stdio: ["pipe", "pipe", "inherit"] },
);
const replies = [],
  waiting = [];
let bridgeError;
const fail = (error) => {
  bridgeError = error;
  for (const item of waiting.splice(0)) item.reject(error);
};
const lines = createInterface({ input: php.stdout });
lines.on("line", (line) => {
  if (!line.startsWith("PLAN_JSON ")) return;
  const value = JSON.parse(line.slice(10));
  if (waiting.length) waiting.shift().resolve(value);
  else replies.push(value);
});
const receive = () =>
  bridgeError
    ? Promise.reject(bridgeError)
    : replies.length
      ? Promise.resolve(replies.shift())
      : new Promise((resolve, reject) => waiting.push({ resolve, reject }));
const exited = new Promise((resolve) =>
  php.once("exit", (code) => {
    fail(Error(`Native fixture exited (${code})`));
    resolve();
  }),
);
php.on("error", fail);
php.stdin.on("error", fail);
const deadline = setTimeout(() => {
  fail(Error("Plan fixture timed out"));
  php.kill();
}, 120000);
let app, pg;
try {
  const fixture = await receive();
  assert.equal(fixture.ready, true);
  const { org, actor } = fixture;
  pg = new PGlite();
  await pg.exec(migration);
  await pg.query(
    "INSERT INTO organisations(id,name,slug) VALUES($1,'Synthetic plan owner','synthetic-plan-owner')",
    [org],
  );
  await pg.query(
    "INSERT INTO users(id,email,name,password_hash,is_superadmin) VALUES($1,'plan@example.test','Synthetic plan admin','unused',true)",
    [actor],
  );
  const session = token();
  await pg.query(
    "INSERT INTO sessions(token_hash,user_id,expires_at) VALUES($1,$2,now()+interval '1 hour')",
    [digest(session), actor],
  );
  const db = {
    query: (q, p) => pg.query(q, p),
    transaction: (fn) =>
      pg.transaction((tx) => fn({ query: (q, p) => tx.query(q, p) })),
    onModuleDestroy: async () => {},
  };
  process.env.ADMIN_ORIGIN = "http://localhost:5173";
  app = await createApp(undefined, db);
  app.get(FaceJobsService).onModuleInit = () => {};
  let queue = Promise.resolve(),
    nativeCalls = 0;
  const native = (path, body) => {
    const result = queue.then(async () => {
      php.stdin.write(JSON.stringify({ path, body }) + "\n");
      const reply = await receive();
      assert.equal(reply.status, 200, reply.failure);
      return reply.data;
    });
    queue = result.catch(() => {});
    return result;
  };
  const engine = app.get(ExamEliteService);
  engine.configuration = async () => ({
    central: true,
    organization_id: 10,
    token: "synthetic",
  });
  engine.request = async (c, owner, path, body) => {
    assert.equal(owner, org);
    nativeCalls++;
    const result = await native(path, body);
    assert.equal(result.version, 1);
    assert.equal(result.organization_id, 10);
    return result;
  };
  await app.listen(0, "127.0.0.1");
  const base =
    (await app.getUrl()) + `/api/v1/organisations/${org}/exam-workspace`;
  const call = async (path, body) => {
    const response = await fetch(base + path, {
      method: body === undefined ? "GET" : "POST",
      headers: {
        Cookie: "t4l_session=" + session,
        Origin: "http://localhost:5173",
        "Content-Type": "application/json",
        "X-Tech4Learn-Request": "1",
      },
      body: body === undefined ? undefined : JSON.stringify(body),
    });
    return { status: response.status, data: await response.json() };
  };
  const catalogue = await call("/plans");
  assert.equal(catalogue.status, 200);
  const choice = catalogue.data.items.find((plan) => !plan.selected);
  assert.ok(choice);
  const before = await native("fixture/facts");
  const body = {
    request_id: randomUUID(),
    plan_id: choice.id,
    plan_revision: choice.revision,
    assignment_revision: catalogue.data.assignment_revision,
  };
  const assigned = await call("/plan", body);
  assert.equal(assigned.status, 201, JSON.stringify(assigned.data));
  assert.equal(assigned.data.plan_id, choice.id);
  assert.deepEqual(await call("/plan", body), assigned);
  const after = await native("fixture/facts");
  assert.equal(after.audits, before.audits + 1);
  assert.equal(after.plan_id, choice.id);
  assert.ok(after.unchanged_details && after.unchanged_plans);
  const refreshed = await call("/plans");
  assert.equal(
    refreshed.data.items.find((plan) => plan.selected).id,
    choice.id,
  );
  assert.equal(
    (await call("/plan", { ...body, request_id: randomUUID() })).status,
    409,
  );
  const callsBefore = nativeCalls;
  assert.equal(
    (await call("/plan", { ...body, actor_id: randomUUID() })).status,
    400,
  );
  assert.equal(nativeCalls, callsBefore);
  await pg.query("UPDATE users SET is_superadmin=false WHERE id=$1", [actor]);
  assert.equal((await call("/plan", body)).status, 403);
  assert.equal(nativeCalls, callsBefore);
  console.log(
    "PASS: real Tech4Learn HTTP → native plan controller: catalogue, assignment, exact replay, stale conflict, actor injection, revocation and unchanged organisation/shared plans.",
  );
} finally {
  clearTimeout(deadline);
  php.stdin.end();
  if (app) await app.close();
  if (pg) await pg.close();
  const kill = setTimeout(() => php.kill(), 2000);
  await exited;
  clearTimeout(kill);
  lines.close();
}
