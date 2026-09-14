import "reflect-metadata";
import { test } from "node:test";
import assert from "node:assert/strict";
import { randomUUID, randomBytes } from "node:crypto";
import { PGlite } from "@electric-sql/pglite";
import { migration } from "../dist/schema.js";
import { accessMigration } from "../dist/migration-access.js";
import { learnerMigration } from "../dist/migration-learners.js";
import { configurationMigration } from "../dist/migration-configuration.js";
import { examWorkspaceMigration } from "../dist/migration-exam-workspace.js";
import { createApp } from "../dist/bootstrap.js";
import { ExamEliteService } from "../dist/examelite.service.js";
import { FaceJobsService } from "../dist/face-jobs.service.js";
import { digest } from "../dist/security.js";
import { allPermissions } from "../dist/access-model.js";

test("central question sharing requires superadmin; organisation reads respect modules, scope and restrictions", async () => {
  const pg = new PGlite();
  for (const m of [
    migration,
    accessMigration,
    learnerMigration,
    configurationMigration,
    examWorkspaceMigration,
  ])
    await pg.exec(m);
  const org = randomUUID(),
    other = randomUUID(),
    admin = randomUUID(),
    member = randomUUID(),
    role = randomUUID();
  await pg.query(
    "INSERT INTO organisations(id,name,slug) VALUES($1,'Owned','owned'),($2,'Other','other')",
    [org, other],
  );
  await pg.query(
    "INSERT INTO users(id,email,name,password_hash,is_superadmin) VALUES($1,'admin@example.test','Admin','unused',true),($2,'member@example.test','Member','unused',false)",
    [admin, member],
  );
  await pg.query(
    "INSERT INTO access_roles(id,organisation_id,name,permissions,protected) VALUES($1,$2,'Exam staff',$3,true)",
    [role, org, allPermissions],
  );
  await pg.query(
    "INSERT INTO memberships(user_id,organisation_id,role,role_id) VALUES($1,$2,'admin',$3)",
    [member, org, role],
  );
  const tokens = {};
  for (const u of [admin, member]) {
    tokens[u] = randomBytes(32).toString("hex");
    await pg.query(
      "INSERT INTO sessions(token_hash,user_id,expires_at) VALUES($1,$2,now()+interval '1 hour')",
      [digest(tokens[u]), u],
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
  const remote = app.get(ExamEliteService),
    requests = [];
  remote.configuration = async () => ({
    central: true,
    organization_id: 1,
    token: "private",
  });
  let saveOutcome = "success";
  remote.request = async (c, o, path, body) => {
    requests.push({ o, path, body });
    if (path.includes("/choices/"))
      return { items: [{ id: 3, label: "Own classification" }], next: null };
    if (path.startsWith("authoring/"))
      return body
        ? saveOutcome === "conflict"
          ? { saved: false, conflict: true }
          : saveOutcome === "invalid"
            ? {
                saved: false,
                errors: { nat_value: ["Numerical answer is required."] },
              }
            : {
                saved: true,
                question: {
                  id: 9,
                  revision: "b".repeat(64),
                  fields: { question: "Saved" },
                },
              }
        : {
            id: 9,
            revision: "a".repeat(64),
            fields: { question: "Original" },
            type: "NAT",
          };
    return path.endsWith("/launch")
      ? { ready: true }
      : path.endsWith("/transfer")
        ? { count: body.question_ids.length, items: [] }
        : { items: [], next: null };
  };
  await app.listen(0, "127.0.0.1");
  const base = (await app.getUrl()) + "/api/v1";
  const call = (path, body, actor = admin, origin = "http://localhost:5173") =>
    fetch(base + path, {
      method: body === undefined ? "GET" : "POST",
      headers: {
        Cookie: "t4l_session=" + tokens[actor],
        Origin: origin,
        "Content-Type": "application/json",
        "X-Tech4Learn-Request": "1",
      },
      body: body === undefined ? undefined : JSON.stringify(body),
    });
  const platform = `/platform/exam-content/${org}`,
    own = `/organisations/${org}/exam-content/questions`;
  try {
    assert.equal(
      (await call(platform + "/questions", undefined, member)).status,
      403,
    );
    assert.equal((await call(own, undefined, member)).status, 403);
    assert.equal(
      (
        await call(
          `/organisations/${other}/exam-content/questions`,
          undefined,
          member,
        )
      ).status,
      404,
    );
    assert.equal(requests.length, 0);
    assert.equal(
      (await call(platform + "/modules", { exams: true, version: 0 }, member))
        .status,
      403,
    );
    assert.equal(
      (
        await call(
          platform + "/modules",
          { exams: true, version: 0 },
          admin,
          "https://evil.example",
        )
      ).status,
      403,
    );
    const enabled = await call(platform + "/modules", {
      exams: true,
      version: 0,
    });
    assert.equal(enabled.status, 201);
    assert.equal((await enabled.json()).version, 1);
    assert.equal(
      (await call(platform + "/modules", { exams: false, version: 0 })).status,
      409,
    );
    assert.equal((await call(own, undefined, member)).status, 200);
    assert.match(requests.at(-1).path, /source=organisation/);
    assert.equal((await call(own + "/9", undefined, member)).status, 200);
    const edit = {
      revision: "a".repeat(64),
      request_id: randomUUID(),
      fields: { question: "Saved" },
    };
    assert.equal((await call(own + "/9", edit, member)).status, 201);
    assert.equal(requests.at(-1).body.actor_id, member);
    for (const [action, fields] of Object.entries({
      "create-section": { name: "Part A", duration: 20 },
      "update-section": { section_id: 3, name: "Part A", duration: 25 },
      "remove-section": { section_id: 3 },
      "assign-section": { question_ids: [9], question_section_id: 3 },
      "subject-timers": { subject_ids: [2], durations: [30] },
      "set-status": { status: "Active" },
    })) {
      assert.equal(
        (
          await call(
            `/organisations/${org}/exam-content/exams/9/actions/${action}`,
            { ...edit, fields, actor_id: admin },
            member,
          )
        ).status,
        201,
      );
      assert.equal(requests.at(-1).body.actor_id, member);
      assert.equal(
        requests.at(-1).path,
        `authoring/${org}/exams/9/actions/${action}`,
      );
    }
    assert.equal(
      (
        await call(
          `/organisations/${other}/exam-content/questions/9`,
          edit,
          member,
        )
      ).status,
      404,
    );
    saveOutcome = "conflict";
    assert.equal((await call(own + "/9", edit, member)).status, 409);
    saveOutcome = "invalid";
    const invalid = await call(own + "/9", edit, member);
    assert.equal(invalid.status, 400);
    assert.equal(
      (await invalid.json()).message,
      "Numerical answer is required.",
    );
    saveOutcome = "success";
    const create = {
      fields: { question: "Created", qtype_id: 2 },
      revision: "new",
      request_id: randomUUID(),
      actor_id: admin,
    };
    assert.equal((await call(own + "/new", undefined, member)).status, 200);
    assert.equal((await call(own, create, member)).status, 201);
    assert.equal(requests.at(-1).body.actor_id, member);
    assert.equal(requests.at(-1).path, `authoring/${org}/questions`);
    assert.equal(
      (await call(own, { ...create, revision: "a".repeat(64) }, member)).status,
      400,
    );
    const taxonomy = `/organisations/${org}/exam-content/taxonomy/subjects`;
    assert.equal(
      (await call(taxonomy + "/new", undefined, member)).status,
      200,
    );
    assert.equal(
      (
        await call(
          taxonomy + "/new",
          {
            ...create,
            fields: { subject_name: "Own subject", group_ids: [3] },
          },
          member,
        )
      ).status,
      201,
    );
    assert.equal(
      requests.at(-1).path,
      `authoring/${org}/taxonomy/subjects/new`,
    );
    assert.equal(
      (
        await call(
          `/organisations/${other}/exam-content/taxonomy/subjects/new`,
          create,
          member,
        )
      ).status,
      404,
    );
    assert.equal(
      (
        await call(
          `/organisations/${org}/exam-content/choices/subjects?search=own&after=0`,
          undefined,
          member,
        )
      ).status,
      200,
    );
    assert.equal(
      (
        await call(
          `/organisations/${org}/exam-content/choices/users`,
          undefined,
          member,
        )
      ).status,
      400,
    );
    const exams = `/organisations/${org}/exam-content/taxonomy/exams`;
    assert.equal((await call(exams + "/new", undefined, member)).status, 200);
    assert.equal(
      (
        await call(
          exams + "/new",
          { ...create, fields: { name: "Synthetic exam" } },
          member,
        )
      ).status,
      201,
    );
    assert.equal(requests.at(-1).path, `authoring/${org}/taxonomy/exams/new`);
    assert.equal(
      (
        await call(
          `/organisations/${org}/exam-content/exams/9/actions/add-questions`,
          { ...edit, fields: { question_ids: [9] } },
          member,
        )
      ).status,
      201,
    );
    assert.equal(requests.at(-1).body.actor_id, member);
    assert.equal(
      (
        await call(
          `/organisations/${org}/exam-content/exams/9/actions/destroy`,
          edit,
          member,
        )
      ).status,
      400,
    );
    assert.equal(
      (
        await call(
          `/organisations/${other}/exam-content/exams/9/questions`,
          undefined,
          member,
        )
      ).status,
      404,
    );
    const publication = {
      fields: { result_after_finish: false },
      revision: "a".repeat(64),
      request_id: randomUUID(),
    };
    assert.equal(
      (
        await call(
          `/organisations/${org}/exam-content/exams/9/actions/set-result-status`,
          publication,
          member,
        )
      ).status,
      201,
    );
    assert.equal(
      requests.at(-1).path,
      `authoring/${org}/exams/9/actions/set-result-status`,
    );
    assert.deepEqual(requests.at(-1).body.fields, {
      result_after_finish: false,
    });
    assert.equal(requests.at(-1).body.actor_id, member);
    assert.equal(
      (
        await call(
          `/organisations/${other}/exam-content/exams/9/actions/set-result-status`,
          publication,
          member,
        )
      ).status,
      404,
    );
    const nativeRequest = remote.request;
    const learner = randomUUID(),
      capture = randomUUID();
    const reviewPath = `/organisations/${org}/exam-proctor/${learner}/attempts`;
    const imagePath = reviewPath + `/19/captures/${capture}`;
    let invalidImage = false,
      expiredImage = false,
      revokeReview = false;
    remote.request = async (c, o, path) => {
      assert.equal(o, org);
      assert.ok(path.startsWith(`review/${org}/learners/${learner}/attempts`));
      if (revokeReview)
        await pg.query(
          "UPDATE memberships SET status='suspended' WHERE user_id=$1 AND organisation_id=$2",
          [member, org],
        );
      const expires_at = new Date(
        Date.now() + (expiredImage ? -60000 : 60000),
      ).toISOString();
      if (path.endsWith(capture))
        return {
          attempt_id: invalidImage ? 20 : 19,
          capture_id: capture,
          mime: "image/jpeg",
          base64: "/9j/",
          expires_at,
        };
      if (path.endsWith("/captures"))
        return {
          attempt_id: 19,
          items: [
            {
              attempt_id: 19,
              capture_id: capture,
              received_at: new Date().toISOString(),
              expires_at,
              base64: "PRIVATE",
            },
          ],
        };
      return {
        items: [
          {
            attempt_id: 19,
            exam_id: 2,
            exam_name: "Synthetic paper",
            started_at: null,
            finished_at: null,
            base64: "PRIVATE",
          },
        ],
        next: null,
      };
    };
    assert.equal(
      (
        await call(
          `/organisations/${other}/exam-proctor/${learner}/attempts`,
          undefined,
          member,
        )
      ).status,
      404,
    );
    assert.equal((await fetch(base + reviewPath)).status, 401);
    assert.equal(
      (
        await fetch(base + reviewPath, {
          headers: { Cookie: "t4l_exam=" + tokens[member] },
        })
      ).status,
      401,
    );
    const history = await call(reviewPath, undefined, member);
    assert.equal(history.status, 200);
    assert.equal(
      JSON.stringify(await history.json()).includes("PRIVATE"),
      false,
    );
    const metadata = await call(reviewPath + "/19/captures", undefined, member);
    assert.equal(metadata.status, 200);
    assert.equal(
      JSON.stringify(await metadata.json()).includes("PRIVATE"),
      false,
    );
    const image = await call(imagePath, undefined, member);
    assert.equal(image.status, 200);
    assert.equal(image.headers.get("content-type"), "image/jpeg");
    assert.equal(image.headers.get("cache-control"), "no-store");
    assert.equal(image.headers.get("x-content-type-options"), "nosniff");
    assert.equal(
      Buffer.from(await image.arrayBuffer()).toString("base64"),
      "/9j/",
    );
    invalidImage = true;
    assert.equal((await call(imagePath, undefined, member)).status, 503);
    invalidImage = false;
    expiredImage = true;
    assert.equal((await call(imagePath, undefined, member)).status, 503);
    expiredImage = false;
    revokeReview = true;
    assert.equal((await call(imagePath, undefined, member)).status, 404);
    revokeReview = false;
    await pg.query(
      "UPDATE memberships SET status='active' WHERE user_id=$1 AND organisation_id=$2",
      [member, org],
    );
    await pg.query(
      "UPDATE memberships SET scope_type='centres',scope_ids=$3 WHERE user_id=$1 AND organisation_id=$2",
      [member, org, [randomUUID()]],
    );
    assert.equal((await call(reviewPath, undefined, member)).status, 403);
    await pg.query(
      "UPDATE memberships SET scope_type='organisation',scope_ids='{}' WHERE user_id=$1 AND organisation_id=$2",
      [member, org],
    );
    const audits = await pg.query(
      "SELECT actor_id,details FROM audit_events WHERE organisation_id=$1 AND action='exams.camera.viewed'",
      [org],
    );
    assert.equal(audits.rows.length, 1);
    assert.equal(audits.rows[0].actor_id, member);
    assert.deepEqual(audits.rows[0].details, {
      learnerId: learner,
      attemptId: 19,
      captureId: capture,
    });
    await pg.query(
      "INSERT INTO examelite_workspaces(organisation_id,restrictions) VALUES($1,$2)",
      [org, ["results"]],
    );
    assert.equal((await call(imagePath, undefined, member)).status, 403);
    await pg.query(
      "DELETE FROM examelite_workspaces WHERE organisation_id=$1",
      [org],
    );
    remote.request = nativeRequest;
    const body = {
      direction: "share",
      question_ids: [9, 2, 9],
      request_id: randomUUID(),
    };
    assert.equal(
      (await call(platform + "/transfer", body, member)).status,
      403,
    );
    assert.equal(
      (await call(platform + "/transfer", { ...body, question_ids: [-1] }))
        .status,
      400,
    );
    assert.equal((await call(platform + "/transfer", body)).status, 201);
    assert.equal(requests.at(-2).body.provision_only, true);
    assert.deepEqual(requests.at(-1).body.question_ids, [2, 9]);
    assert.equal(requests.at(-1).body.actor_id, admin);
    assert.equal(JSON.stringify(requests).includes("example.test"), false);
    await pg.query(
      "INSERT INTO examelite_workspaces(organisation_id,restrictions) VALUES($1,$2)",
      [org, ["questions"]],
    );
    assert.equal((await call(own, undefined, member)).status, 403);
    assert.equal((await call(own + "/9", edit, member)).status, 403);
    assert.equal((await call(own, create, member)).status, 403);
    assert.equal(
      (await call(taxonomy + "/new", undefined, member)).status,
      200,
    );
    await pg.query(
      "UPDATE examelite_workspaces SET restrictions=$2 WHERE organisation_id=$1",
      [org, ["questions", "subjects", "exams"]],
    );
    assert.equal(
      (await call(taxonomy + "/new", undefined, member)).status,
      403,
    );
    assert.equal((await call(taxonomy + "/new", create, member)).status, 403);
    assert.equal((await call(exams + "/new", create, member)).status, 403);
    assert.equal(
      (
        await call(
          `/organisations/${org}/exam-content/choices/exams`,
          undefined,
          member,
        )
      ).status,
      403,
    );
    assert.equal(
      (
        await call(
          `/organisations/${org}/exam-content/choices/subjects`,
          undefined,
          member,
        )
      ).status,
      403,
    );

    assert.equal((await call(platform + "/transfer", body)).status, 403);
    assert.equal(
      (
        await call(platform + "/transfer", {
          ...body,
          direction: "pull",
          request_id: randomUUID(),
        })
      ).status,
      201,
    );
    assert.equal(
      (await call(platform + "/modules", { exams: false, version: 1 })).status,
      201,
    );
    assert.equal((await call(own, undefined, member)).status, 403);
    assert.equal(
      (
        await call(
          `/organisations/${org}/exam-workspace/launch`,
          { feature: "exams" },
          member,
        )
      ).status,
      403,
    );
    assert.equal(
      (await call(platform + "/questions?source=central")).status,
      200,
    );
  } finally {
    await app.close();
    await pg.close();
  }
});
