// Opt-in integration check: real Nest HTTP handlers and native PHP controllers,
// isolated PGlite/SQLite databases, no production configuration or network provider.
import "reflect-metadata";
import assert from "node:assert/strict";
import { spawn } from "node:child_process";
import { createInterface } from "node:readline";
import { createHash, randomUUID } from "node:crypto";
import { fileURLToPath } from "node:url";
import { PGlite } from "@electric-sql/pglite";
import { migration } from "../../apps/api/dist/schema.js";
import { accessMigration } from "../../apps/api/dist/migration-access.js";
import { learnerMigration } from "../../apps/api/dist/migration-learners.js";
import { configurationMigration } from "../../apps/api/dist/migration-configuration.js";
import { academicMigration } from "../../apps/api/dist/migration-academic.js";
import { examWorkspaceMigration } from "../../apps/api/dist/migration-exam-workspace.js";
import { examStudentAccessMigration } from "../../apps/api/dist/migration-exam-student-access.js";
import { createApp } from "../../apps/api/dist/bootstrap.js";
import { ExamEliteService } from "../../apps/api/dist/examelite.service.js";
import { FaceJobsService } from "../../apps/api/dist/face-jobs.service.js";
import { digest, token } from "../../apps/api/dist/security.js";

const browserMode = process.argv[6] === "--browser";
if (
  (process.argv.length !== 6 && !(process.argv.length === 7 && browserMode)) ||
  process.env.NODE_ENV === "production"
)
  throw Error(
    "Use node tests/browser/pilot-api-native.mjs VENDOR MODELS QUESTION_CONTROLLER EXAM_CONTROLLER in a development environment",
  );
const php = spawn(
  "php",
  [
    fileURLToPath(
      new URL(
        "../../deploy/examelite/test-pilot-api-bridge.php",
        import.meta.url,
      ),
    ),
    ...process.argv.slice(2, 6),
  ],
  { stdio: ["pipe", "pipe", "inherit"] },
);
const replies = [],
  waiting = [];
let bridgeError;
const failBridge = (error) => {
  bridgeError = error;
  for (const pending of waiting.splice(0)) pending.reject(error);
};
const lines = createInterface({ input: php.stdout });
lines.on("line", (line) => {
  if (!line.startsWith("PILOT_JSON ")) return;
  const value = JSON.parse(line.slice(11));
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
    failBridge(Error(`Native fixture exited (${code})`));
    resolve();
  }),
);
const deadline = setTimeout(() => {
  failBridge(Error("Native pilot timed out"));
  php.kill();
}, 120000);
php.on("error", failBridge);
php.stdin.on("error", failBridge);
let app, pg, vite;
try {
  const fixture = await receive();
  assert.equal(fixture.ready, true);
  const { org, actor: admin } = fixture,
    learner = randomUUID(),
    centre = randomUUID(),
    group = randomUUID(),
    session = token();
  pg = new PGlite();
  for (const sql of [
    migration,
    accessMigration,
    learnerMigration,
    configurationMigration,
    academicMigration,
    examWorkspaceMigration,
    examStudentAccessMigration,
  ])
    await pg.exec(sql);
  await pg.query(
    "INSERT INTO organisations(id,name,slug) VALUES($1,'Synthetic pilot','pilot')",
    [org],
  );
  await pg.query(
    "INSERT INTO users(id,email,name,password_hash,is_superadmin) VALUES($1,'pilot@example.test','Synthetic marker','unused',true)",
    [admin],
  );
  await pg.query(
    "INSERT INTO sessions(token_hash,user_id,expires_at) VALUES($1,$2,now()+interval '1 hour')",
    [digest(session), admin],
  );
  await pg.query(
    `INSERT INTO organisation_settings(organisation_id,enabled_modules) VALUES($1,'{"exams":true,"learners":true}')`,
    [org],
  );
  await pg.query(
    "INSERT INTO centres(id,organisation_id,name) VALUES($1,$2,'Synthetic centre')",
    [centre, org],
  );
  await pg.query(
    "INSERT INTO learning_groups(id,organisation_id,centre_id,name) VALUES($1,$2,$3,'Synthetic class')",
    [group, org, centre],
  );
  await pg.query(
    "INSERT INTO learners(id,organisation_id,group_id,code,name) VALUES($1,$2,$3,'PILOT','Synthetic pilot candidate')",
    [learner, org, group],
  );
  const db = {
    query: (q, p) => pg.query(q, p),
    transaction: (fn) =>
      pg.transaction((tx) => fn({ query: (q, p) => tx.query(q, p) })),
    onModuleDestroy: async () => {},
  };
  const origin = browserMode
    ? "http://127.0.0.1:5195"
    : "http://localhost:5173";
  process.env.ADMIN_ORIGIN = origin;
  app = await createApp(undefined, db);
  app.get(FaceJobsService).onModuleInit = () => {};
  const engine = app.get(ExamEliteService);
  engine.configuration = async () => ({
    central: true,
    organization_id: 10,
    token: "synthetic",
  });
  let queue = Promise.resolve(),
    nativeCalls = 0;
  engine.request = (config, owner, path, body) => {
    const request = queue.then(async () => {
      assert.equal(owner, org);
      if (path === `workspace/${org}/launch`) {
        assert.equal(body.actor_id, admin);
        assert.equal(body.provision_only, true);
        return { ready: true }; // The native fixture already provisions this workspace/staff.
      }
      nativeCalls++;
      php.stdin.write(JSON.stringify({ path, body }) + "\n");
      const reply = await receive();
      if (reply.status !== 200)
        console.error("Native fixture failure:", path, reply.failure);
      assert.equal(reply.status, 200, reply.failure);
      assert.equal(reply.data.organization_id, 10);

      return reply.data;
    });
    queue = request.catch(() => {});
    return request;
  };
  await app.listen(0, "127.0.0.1");
  const base = (await app.getUrl()) + "/api/v1",
    root = `/organisations/${org}`,
    staff = "t4l_session=" + session;
  const response = (path, body, cookie = staff) =>
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
  const call = async (path, body, cookie = staff) => {
    const r = await response(path, body, cookie),
      data = await r.json();
    assert.ok(r.ok, `${path}: ${r.status} ${JSON.stringify(data)}`);
    return data;
  };
  const save = (path, fields, revision = "new") =>
    call(root + path, { fields, revision, request_id: randomUUID() });
  const imageBytes = Buffer.from(
    "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZQmcAAAAASUVORK5CYII=",
    "base64",
  );
  let passage = await save("/exam-content/taxonomy/passages/new", {
    name: "Synthetic pilot diagram",
    passages: { [fixture.language]: "<p>Two pairs make four.</p>" },
  });
  const imagePath = root + `/exam-content/passages/${passage.id}/image`;
  const upload = async (fields) => {
    const request = {
      fields: { language_id: fixture.language, ...fields },
      revision: passage.revision,
      request_id: randomUUID(),
    };
    const saved = await call(imagePath, request);
    assert.deepEqual(
      await call(imagePath, request),
      saved,
      "Native image retry must return the original snapshot",
    );
    passage = saved;
  };
  const assetFrom = () => {
    const source = /<img[^>]+src="([^"]+)"/.exec(
      passage.fields.passages[fixture.language],
    )?.[1];
    assert.match(
      source ?? "",
      /^\/storage\/images\/upload\/t4l\/20\/[a-f0-9]{40}\.png$/,
    );
    return createHash("sha256").update(source).digest("hex");
  };
  const preview = async (asset) => {
    const r = await response(
      root +
        `/exam-content/passages/${passage.id}/languages/${fixture.language}/media/${asset}?revision=${passage.revision}`,
    );
    assert.equal(r.status, 200, r.ok ? undefined : await r.text());
    assert.match(r.headers.get("content-type"), /image\/png/);
    assert.match(r.headers.get("cache-control"), /no-store/);
    assert.deepEqual(Buffer.from(await r.arrayBuffer()), imageBytes);
  };
  await upload({ image: imageBytes.toString("base64") });
  const firstAsset = assetFrom();
  await preview(firstAsset);
  await upload({ image: imageBytes.toString("base64"), asset: firstAsset });
  const replacedAsset = assetFrom();
  assert.notEqual(replacedAsset, firstAsset);
  await preview(replacedAsset);
  await upload({ asset: replacedAsset, remove: true });
  assert.doesNotMatch(passage.fields.passages[fixture.language], /<img/);
  await upload({ image: imageBytes.toString("base64") });
  const passageAsset = assetFrom();
  await preview(passageAsset);
  let question = await save("/exam-content/questions/new", {
    passage_id: passage.id,
    qtype_id: 5,
    question: "<p>Explain why two plus two is four.</p>",
    si_answer1: "Two pairs contain four items.",
    marks: 10,
    negative_marks: 0,
    language_id: fixture.language,
    group_ids: [fixture.group],
    status: "Yes",
  });
  const questionImageRequest = {
    fields: { field: "hint", image: imageBytes.toString("base64") },
    revision: question.revision,
    request_id: randomUUID(),
  };
  question = await call(
    root + `/exam-content/questions/${question.id}/image`,
    questionImageRequest,
  );
  const questionSource = /<img[^>]+src="([^"]+)"/.exec(
    question.fields.hint,
  )?.[1];
  assert.ok(questionSource);
  const questionAsset = createHash("sha256")
    .update(questionSource)
    .digest("hex");
  const questionImage = await response(
    root + `/exam-content/questions/${question.id}/media/${questionAsset}`,
  );
  assert.equal(questionImage.status, 200);
  assert.deepEqual(Buffer.from(await questionImage.arrayBuffer()), imageBytes);
  const defaults = await call(root + "/exam-content/taxonomy/exams/new");
  let exam = await save("/exam-content/taxonomy/exams/new", {
    ...defaults.fields,
    name: "Synthetic API-native paper",
    groups: [fixture.group],
    language_ids: [fixture.language],
    duration: 30,
    passing_percentage: 50,
    start_date: "2026-09-13 10:00:00",
    end_date: "2026-09-13 14:00:00",
    timer_mode: "none",
    proctor: false,
    browser_tolerance: false,
    attempt_count: 1,
    result_after_finish: false,
    frontend_visible: true,
    online_attempt_enabled: true,
  });
  const action = async (name, fields) =>
    (exam = await save(
      `/exam-content/exams/${exam.id}/actions/${name}`,
      fields,
      exam.revision,
    ));
  await action("add-questions", { question_ids: [question.id] });
  await action("set-status", { status: "Active" });
  const grant = await call(root + "/exam-student-access", {
    learner_id: learner,
    exam_id: String(exam.id),
    hours: 24,
  });
  if (browserMode) {
    const { createServer } = await import("vite");
    // Test-only bootstrap on a loopback dev server; never part of the application build.
    const bootstrap = {
      name: "synthetic-pilot",
      configureServer(server) {
        server.middlewares.use("/pilot-fixture", (req, res) => {
          res.setHeader("Content-Type", "application/json");
          res.setHeader("Cache-Control", "no-store");
          res.setHeader(
            "Set-Cookie",
            `${staff}; HttpOnly; SameSite=Lax; Path=/`,
          );
          res.end(
            JSON.stringify({
              org,
              learner,
              exam,
              question,
              fragment: grant.fragment,
            }),
          );
        });
      },
    };
    vite = await createServer({
      configFile: false,
      plugins: [bootstrap],
      root: fileURLToPath(new URL("../../", import.meta.url)),
      define: { "import.meta.env.VITE_API_URL": JSON.stringify("/api/v1") },
      esbuild: { jsx: "automatic" },
      server: {
        host: "127.0.0.1",
        port: 5195,
        strictPort: true,
        proxy: { "/api": await app.getUrl() },
      },
    });
    await vite.listen();
    console.log(
      "READY: http://127.0.0.1:5195/tests/browser/pilot-api-native.html",
    );
    clearTimeout(deadline);
    await new Promise((resolve) => {
      process.once("SIGINT", resolve);
      process.once("SIGTERM", resolve);
    });
  } else {
    const exchange = await response(
      root + "/student-exam/exchange",
      { token: grant.fragment.split(".")[1] },
      "",
    );
    assert.equal(exchange.status, 200);
    assert.match(exchange.headers.get("set-cookie"), /HttpOnly/);
    const studentCookie = exchange.headers.get("set-cookie").split(";")[0];
    const studentPath = root + "/student-exam/attempt/";
    const run = (action, fields = {}) =>
      call(
        studentPath + action,
        { request_id: randomUUID(), ...fields },
        studentCookie,
      );
    const beforeDenied = nativeCalls;
    assert.equal(
      (
        await response(
          studentPath + "start",
          { request_id: randomUUID() },
          staff,
        )
      ).status,
      401,
    );
    assert.equal(
      (
        await response(
          studentPath + "start",
          { request_id: randomUUID(), learner_id: randomUUID() },
          studentCookie,
        )
      ).status,
      400,
    );
    assert.equal(
      nativeCalls,
      beforeDenied,
      "Denied requests must not reach native engine",
    );
    await run("prepare");
    const started = await run("start");
    assert.equal(started.questions[0].id, question.id);
    assert.match(
      started.questions[0].passage.content,
      new RegExp("t4l-media:" + passageAsset),
    );
    const mediaUrl =
      root +
      `/student-exam/media/${started.attempt_id}/${question.id}/${passageAsset}`;
    const studentImage = await response(mediaUrl, undefined, studentCookie);
    assert.equal(studentImage.status, 200);
    assert.deepEqual(Buffer.from(await studentImage.arrayBuffer()), imageBytes);

    const answer = {
      request_id: randomUUID(),
      attempt_id: started.attempt_id,
      question_id: question.id,
      revision: started.questions[0].revision,
      fields: {
        option_selected: "Two pairs each have two items, giving four.",
      },
    };
    const saved = await run("answer", answer);
    assert.equal(saved.saved, true);
    assert.deepEqual(await run("answer", answer), saved);
    assert.equal((await run("start")).attempt_id, started.attempt_id);
    const submit = { attempt_id: started.attempt_id, request_id: randomUUID() };
    const finished = await run("submit", submit);
    assert.equal(finished.completed, true);
    assert.equal(finished.result, null);
    const reviewPath =
      root + `/exam-results/${learner}/attempts/${started.attempt_id}`;
    const review = await call(reviewPath);
    assert.match(review.questions[0].answer_html, /Two pairs/);
    const mark = {
      marks: { [review.questions[0].stat_id]: 7 },
      revision: review.revision,
      request_id: randomUUID(),
    };
    const graded = await call(reviewPath, mark);
    assert.equal(graded.score_percent, 70);
    assert.deepEqual(await call(reviewPath, mark), graded);
    assert.equal(
      (await run("result", { attempt_id: started.attempt_id })).result,
      null,
    );
    await action("set-result-status", { result_after_finish: true });
    const published = await run("result", { attempt_id: started.attempt_id });
    assert.equal(published.result.score_percent, 70);
    assert.equal((await run("history")).items.length, 1);
    assert.deepEqual(await run("submit", submit), published);
    assert.equal((await call(reviewPath)).questions.length, 0);
    await call(root + `/exam-student-access/${grant.id}/revoke`, {});
    const beforeRevoked = nativeCalls;
    assert.equal(
      (
        await response(
          studentPath + "result",
          { request_id: randomUUID(), attempt_id: started.attempt_id },
          studentCookie,
        )
      ).status,
      401,
    );
    assert.equal(nativeCalls, beforeRevoked);
    console.log(
      `PASS: real Tech4Learn HTTP → native ExamElite controllers (${nativeCalls} calls): create, passage image upload/replace/remove/private preview, assign, take with protected diagram, resume, retry, submit, mark, publish, history and revoke.`,
    );
  }
} finally {
  clearTimeout(deadline);
  php.stdin.end();
  if (vite) await vite.close();
  if (app) await app.close();
  if (pg) await pg.close();
  const cleanupDeadline = setTimeout(() => php.kill(), 2000);
  await exited;
  clearTimeout(cleanupDeadline);
  lines.close();
}
