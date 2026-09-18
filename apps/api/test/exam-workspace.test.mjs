import "reflect-metadata";
import { test } from "node:test";
import assert from "node:assert/strict";
import { randomUUID, randomBytes } from "node:crypto";
import { PGlite } from "@electric-sql/pglite";
import { migration } from "../dist/schema.js";
import { accessMigration } from "../dist/migration-access.js";
import { learnerMigration } from "../dist/migration-learners.js";
import { examWorkspaceMigration } from "../dist/migration-exam-workspace.js";
import { createApp } from "../dist/bootstrap.js";
import { ExamEliteService } from "../dist/examelite.service.js";
import { ExamWorkspaceService } from "../dist/exam-workspace.service.js";
import { IdentityService } from "../dist/identity.service.js";
import { FaceJobsService } from "../dist/face-jobs.service.js";
import { digest } from "../dist/security.js";
import { allPermissions } from "../dist/access-model.js";

test("native exam entry enforces dedicated permission, tenant scope, restrictions and minimal identity", async () => {
  const pg = new PGlite();
  await pg.exec(migration);
  await pg.exec(accessMigration);
  await pg.exec(learnerMigration);
  await pg.exec(examWorkspaceMigration);
  await pg.exec(
    "CREATE TABLE organisation_settings(organisation_id uuid,enabled_modules jsonb)",
  );
  const org = randomUUID(),
    other = randomUUID(),
    admin = randomUUID(),
    member = randomUUID(),
    branding = randomUUID(),
    role = randomUUID(),
    brandRole = randomUUID(),
    learner = randomUUID(),
    foreign = randomUUID();
  const centre = randomUUID(),
    group = randomUUID();
  await pg.query(
    "INSERT INTO organisations(id,name,slug) VALUES($1,'Owned','owned'),($2,'Other','other')",
    [org, other],
  );
  await pg.query(
    "INSERT INTO organisation_settings(organisation_id,enabled_modules) VALUES($1,$2)",
    [org, JSON.stringify({ exams: true })],
  );
  await pg.query(
    "INSERT INTO users(id,email,name,password_hash,is_superadmin) VALUES($1,'admin@example.test','Admin','unused',true),($2,'member@example.test','Member','unused',false),($3,'branding@example.test','Branding','unused',false)",
    [admin, member, branding],
  );
  await pg.query(
    "INSERT INTO access_roles(id,organisation_id,name,permissions,protected) VALUES($1,$2,'Exam admin',$3,true),($4,$2,'Branding',ARRAY['organisation.view','configuration.view','configuration.manage'],false)",
    [role, org, allPermissions, brandRole],
  );
  await pg.query(
    "INSERT INTO memberships(user_id,organisation_id,role,role_id) VALUES($1,$2,'admin',$3),($4,$2,'admin',$5)",
    [member, org, role, branding, brandRole],
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
    "INSERT INTO learners(id,organisation_id,group_id,name,code) VALUES($1,$2,$3,'Learner','CODE')",
    [learner, org, group],
  );
  const tokens = {};
  for (const u of [admin, member, branding]) {
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
  const remote = app.get(ExamEliteService);
  remote.configuration = async () => ({
    central: true,
    organization_id: 1,
    token: "private",
  });
  const requests = [];
  let badProvision = false,
    fail = false;
  let capabilityReply,
    capabilityRevoke = false;
  let planReply,
    planRevoke = false;
  remote.request = async (c, o, path, body) => {
    requests.push({ o, path, body });
    if (fail) throw new Error("Provider unavailable");
    if (
      path.includes("/plans?") ||
      path.endsWith("/plan") ||
      path === "central/plans" ||
      path.startsWith("central/plans/")
    ) {
      if (planRevoke)
        await pg.query("UPDATE users SET is_superadmin=false WHERE id=$1", [
          admin,
        ]);
      return planReply;
    }
    if (path.endsWith("/capabilities")) {
      if (capabilityRevoke)
        await pg.query("UPDATE users SET is_superadmin=false WHERE id=$1", [
          admin,
        ]);
      return capabilityReply;
    }
    return path.endsWith("/launch")
      ? { ready: !badProvision }
      : { saved: true };
  };
  await app.listen(0, "127.0.0.1");
  const base = (await app.getUrl()) + "/api/v1/organisations/";
  const call = (
    path = "",
    body,
    actor = member,
    organisation = org,
    origin = "http://localhost:5173",
  ) =>
    fetch(base + organisation + "/exam-workspace" + path, {
      method: body === undefined ? "GET" : "POST",
      headers: {
        Cookie: "t4l_session=" + tokens[actor],
        Origin: origin,
        "Content-Type": "application/json",
        "X-Tech4Learn-Request": "1",
      },
      body: body === undefined ? undefined : JSON.stringify(body),
    });
  try {
    assert.equal((await call("", undefined, branding)).status, 403);
    assert.equal((await call("", undefined, member, other)).status, 404);
    assert.deepEqual((await (await call()).json()).restrictions, []);
    assert.equal(
      (
        await call(
          "/launch",
          { feature: "questions" },
          member,
          org,
          "https://evil.example",
        )
      ).status,
      403,
    );
    assert.equal(
      (await call("/launch", { feature: "taking", learner: foreign })).status,
      404,
    );
    assert.equal(requests.length, 0);
    assert.equal(
      (await call("/launch", { feature: "taking", learner })).status,
      410,
    );
    assert.equal(
      (await call("/launch", { feature: "exams", provision_only: true }))
        .status,
      410,
    );
    assert.equal(requests.length, 0);
    const account = await app.get(IdentityService).account(tokens[member]);
    assert.deepEqual(
      await app
        .get(ExamWorkspaceService)
        .launch(account, org, { feature: "taking", learner }, true),
      { ready: true },
    );
    assert.deepEqual(requests.at(-1).body.learner, {
      id: learner,
      name: "Learner",
    });
    assert.equal(JSON.stringify(requests).includes("example.test"), false);
    assert.equal(
      (
        await call("/restrictions", {
          restrictions: ["questions"],
          revision: 0,
        })
      ).status,
      403,
    );
    assert.equal(
      (
        await call(
          "/restrictions",
          { restrictions: ["questions"], revision: 0 },
          admin,
        )
      ).status,
      201,
    );
    assert.equal(
      (await call("/restrictions", { restrictions: [], revision: 0 }, admin))
        .status,
      409,
    );
    assert.equal((await call("/launch", { feature: "questions" })).status, 403);
    assert.equal((await call("/launch", { feature: "exams" })).status, 410);
    badProvision = true;
    await assert.rejects(
      () =>
        app
          .get(ExamWorkspaceService)
          .launch(account, org, { feature: "exams" }, true),
      /provisioning failed/,
    );
    badProvision = false;
    fail = true;
    assert.equal(
      (await call("/restrictions", { restrictions: [], revision: 1 }, admin))
        .status,
      500,
    );
    fail = false;
    assert.equal((await (await call()).json()).revision, 1);
    await pg.query(
      "UPDATE memberships SET scope_type='centres',scope_ids=$1 WHERE user_id=$2",
      [[centre], member],
    );
    assert.equal((await call("/launch", { feature: "exams" })).status, 403);
    await pg.query(
      "UPDATE memberships SET scope_type='organisation',scope_ids='{}',status='suspended' WHERE user_id=$1",
      [member],
    );
    assert.equal((await call("/launch", { feature: "exams" })).status, 404);
    const validCatalogue = {
      revision: 1,
      native_features: [
        { key: "ai_translation", enabled: true, secret: "omit" },
        { key: "paid_packages", enabled: false },
      ],
      workspace_features: [
        "subjects",
        "questions",
        "exams",
        "taking",
        "results",
      ].map((key) => ({ key, enabled: key !== "questions" })),
      private_configuration: "omit",
    };
    capabilityReply = validCatalogue;
    let before = requests.length;
    assert.equal((await call("/capabilities", undefined, member)).status, 403);
    assert.equal(
      (await call("/capabilities?owner=1", undefined, admin)).status,
      400,
    );
    assert.equal(requests.length, before);
    const catalogue = await call("/capabilities", undefined, admin);
    assert.equal(catalogue.status, 200);
    const projected = await catalogue.json();
    assert.deepEqual(Object.keys(projected), [
      "revision",
      "native_features",
      "workspace_features",
    ]);
    assert.deepEqual(projected.native_features, [
      { key: "ai_translation", enabled: true },
      { key: "paid_packages", enabled: false },
    ]);
    assert.equal(requests.at(-1).path, `workspace/${org}/capabilities`);
    assert.equal(requests.at(-1).body, undefined);
    for (const native_features of [
      null,
      [],
      [{ key: "bad/key", enabled: true }],
      [{ key: "valid", enabled: 1 }],
      [
        { key: "duplicate", enabled: true },
        { key: "duplicate", enabled: false },
      ],
    ]) {
      capabilityReply = { ...validCatalogue, native_features };
      assert.equal((await call("/capabilities", undefined, admin)).status, 503);
    }
    capabilityReply = { ...validCatalogue, revision: 0 };
    assert.equal((await call("/capabilities", undefined, admin)).status, 409);
    capabilityReply = {
      ...validCatalogue,
      workspace_features: validCatalogue.workspace_features.map((row) => ({
        ...row,
        enabled: true,
      })),
    };
    assert.equal((await call("/capabilities", undefined, admin)).status, 409);
    capabilityReply = validCatalogue;
    capabilityRevoke = true;
    assert.equal((await call("/capabilities", undefined, admin)).status, 403);
    capabilityRevoke = false;
    before = requests.length;
    assert.equal((await call("/capabilities", undefined, admin)).status, 403);
    assert.equal(requests.length, before);
    await pg.query("UPDATE users SET is_superadmin=true WHERE id=$1", [admin]);
    const revision = "a".repeat(64);
    assert.equal((await call("/plan-fields", undefined, member)).status, 403);
    assert.equal(
      (await call("/plan-fields?owner=1", undefined, admin)).status,
      400,
    );
    const planFields = await call("/plan-fields", undefined, admin);
    assert.equal(planFields.status, 200);
    const fieldSchema = await planFields.json();
    assert.ok(
      fieldSchema.features.includes("ai_translation") &&
        fieldSchema.limits.includes("students"),
    );
    assert.deepEqual(Object.keys(fieldSchema).sort(), ["features", "limits"]);
    const planCatalogue = {
      assignment_revision: revision,
      items: [
        {
          id: 3,
          name: "Synthetic plan",
          selected: true,
          revision,
          private_data: "omit",
        },
      ],
      next: null,
      secret: "omit",
    };
    planReply = planCatalogue;
    before = requests.length;
    assert.equal((await call("/plans", undefined, member)).status, 403);
    assert.equal((await call("/plans?after=01", undefined, admin)).status, 400);
    assert.equal((await call("/plans?owner=10", undefined, admin)).status, 400);
    assert.equal(requests.length, before);
    const plans = await call("/plans", undefined, admin);
    assert.equal(plans.status, 200);
    assert.deepEqual(await plans.json(), {
      assignment_revision: revision,
      items: [{ id: 3, name: "Synthetic plan", selected: true, revision }],
      next: null,
    });
    assert.equal(requests.at(-1).path, `workspace/${org}/plans?after=0`);
    for (const bad of [
      { items: null },
      { assignment_revision: "bad" },
      { next: "3" },
      { items: [...planCatalogue.items, ...planCatalogue.items] },
      { items: [{ ...planCatalogue.items[0], selected: 1 }] },
    ]) {
      planReply = { ...planCatalogue, ...bad };
      assert.equal((await call("/plans", undefined, admin)).status, 503);
    }
    const assignment = {
      request_id: randomUUID(),
      plan_id: 3,
      assignment_revision: revision,
      plan_revision: revision,
    };
    before = requests.length;
    assert.equal((await call("/plan", assignment, member)).status, 403);
    assert.equal(
      (await call("/plan", { ...assignment, actor_id: member }, admin)).status,
      400,
    );
    assert.equal((await call("/plan?owner=10", assignment, admin)).status, 400);
    assert.equal(
      (await call("/plan", { ...assignment, plan_id: "3" }, admin)).status,
      400,
    );
    assert.equal(requests.length, before);
    planReply = {
      saved: true,
      plan_id: 3,
      assignment_revision: revision,
      secret: "omit",
    };
    const assigned = await call("/plan", assignment, admin);
    assert.equal(assigned.status, 201);
    assert.deepEqual(await assigned.json(), {
      saved: true,
      plan_id: 3,
      assignment_revision: revision,
    });
    assert.deepEqual(requests.at(-1).body, { ...assignment, actor_id: admin });
    assert.equal((await call("/plan", assignment, admin)).status, 201);
    assert.deepEqual(requests.at(-1).body, requests.at(-2).body);
    planReply = { saved: true, plan_id: 4, assignment_revision: revision };
    assert.equal((await call("/plan", assignment, admin)).status, 503);
    planReply = { saved: false, conflict: true };
    assert.equal((await call("/plan", assignment, admin)).status, 409);
    planRevoke = true;
    planReply = planCatalogue;
    assert.equal((await call("/plans", undefined, admin)).status, 403);
    await pg.query("UPDATE users SET is_superadmin=true WHERE id=$1", [admin]);
    planReply = { saved: true, plan_id: 3, assignment_revision: revision };
    assert.equal((await call("/plan", assignment, admin)).status, 403);
    planRevoke = false;
    before = requests.length;
    assert.equal((await call("/plan", assignment, admin)).status, 403);
    assert.equal(requests.length, before);
    await pg.query("UPDATE users SET is_superadmin=true WHERE id=$1", [admin]);
    const creation = {
      request_id: randomUUID(),
      fields: {
        name: "New synthetic plan",
        price: "15.50",
        feature_reports: true,
        limit_students: 20,
      },
    };
    before = requests.length;
    assert.equal((await call("/plans", creation, member)).status, 403);
    assert.equal(
      (await call("/plans", { ...creation, actor_id: member }, admin)).status,
      400,
    );
    assert.equal((await call("/plans?owner=1", creation, admin)).status, 400);
    for (const fields of [
      { ...creation.fields, is_default: true },
      { ...creation.fields, feature_reports: 1 },
      { ...creation.fields, limit_students: -1 },
      { ...creation.fields, price: "1e6" },
      { ...creation.fields, price: "100000000.00" },
      { ...creation.fields, billing_cycle: ["monthly"] },
      { ...creation.fields, name: " " },
      { ...creation.fields, feature_unknown: true },
    ]) {
      assert.equal(
        (await call("/plans", { ...creation, fields }, admin)).status,
        400,
      );
    }
    assert.equal(requests.length, before);
    planReply = {
      saved: true,
      plan_id: 10,
      revision,
      name: creation.fields.name,
      private_data: "omit",
    };
    const createdPlan = await call("/plans", creation, admin);
    assert.equal(createdPlan.status, 201);
    assert.deepEqual(await createdPlan.json(), {
      saved: true,
      plan_id: 10,
      revision,
      name: creation.fields.name,
    });
    assert.equal(requests.at(-1).path, "central/plans");
    assert.deepEqual(requests.at(-1).body, { ...creation, actor_id: admin });
    assert.equal((await call("/plans", creation, admin)).status, 201);
    assert.deepEqual(requests.at(-1).body, requests.at(-2).body);
    planReply = { ...planReply, name: "Different plan" };
    assert.equal((await call("/plans", creation, admin)).status, 503);
    planReply = { saved: false, conflict: true };
    assert.equal((await call("/plans", creation, admin)).status, 409);
    planRevoke = true;
    planReply = {
      saved: true,
      plan_id: 10,
      revision,
      name: creation.fields.name,
    };
    assert.equal((await call("/plans", creation, admin)).status, 403);
    before = requests.length;
    assert.equal((await call("/plans", creation, admin)).status, 403);
    assert.equal(requests.length, before);
    planRevoke = false;
    await pg.query("UPDATE users SET is_superadmin=true WHERE id=$1", [admin]);
    const centralCatalogue = {
      items: [
        {
          id: 10,
          name: "Inactive plan",
          active: false,
          is_default: false,
          revision,
        },
      ],
      next: null,
    };
    planReply = { ...centralCatalogue, secret: "omit" };
    const centralCatalogueResponse = await call(
      "/central-plans",
      undefined,
      admin,
    );
    assert.equal(centralCatalogueResponse.status, 200);
    assert.deepEqual(await centralCatalogueResponse.json(), centralCatalogue);
    assert.equal(requests.at(-1).path, "central/plans?after=0");
    for (const bad of [
      { next: "10" },
      { items: [...centralCatalogue.items, ...centralCatalogue.items] },
      { items: [{ ...centralCatalogue.items[0], active: 1 }] },
    ]) {
      planReply = { ...centralCatalogue, ...bad };
      assert.equal(
        (await call("/central-plans", undefined, admin)).status,
        503,
      );
    }
    const fields = {
      name: "Inactive plan",
      price: "0.00",
      billing_cycle: "monthly",
      status: false,
      ...Object.fromEntries(
        fieldSchema.features.map((key) => [`feature_${key}`, true]),
      ),
      ...Object.fromEntries(
        fieldSchema.limits.map((key) => [`limit_${key}`, null]),
      ),
    };
    const detail = {
      plan_id: 10,
      revision,
      fields,
      is_default: false,
      assigned_organisations: 2,
    };
    planReply = { ...detail, secret: "omit" };
    const details = await call("/central-plans/10", undefined, admin);
    assert.equal(details.status, 200);
    assert.deepEqual(await details.json(), detail);
    for (const bad of [
      { plan_id: 11 },
      { fields: { name: "Only name" } },
      { fields: { ...fields, status: 1 } },
      { assigned_organisations: -1 },
    ]) {
      planReply = { ...detail, ...bad };
      assert.equal(
        (await call("/central-plans/10", undefined, admin)).status,
        503,
      );
    }
    const update = {
      request_id: randomUUID(),
      revision,
      fields: { limit_students: 50 },
    };
    before = requests.length;
    assert.equal((await call("/central-plans", undefined, member)).status, 403);
    assert.equal(
      (await call("/central-plans/10", undefined, member)).status,
      403,
    );
    assert.equal((await call("/central-plans/10", update, member)).status, 403);
    assert.equal(
      (await call("/central-plans?after=01", undefined, admin)).status,
      400,
    );
    assert.equal(
      (await call("/central-plans/010", undefined, admin)).status,
      400,
    );
    assert.equal(
      (await call("/central-plans/10?owner=1", undefined, admin)).status,
      400,
    );
    for (const bad of [
      { actor_id: member },
      { revision: "bad" },
      { fields: {} },
      { fields: { is_default: true } },
      { fields: { limit_students: -1 } },
    ]) {
      assert.equal(
        (await call("/central-plans/10", { ...update, ...bad }, admin)).status,
        400,
      );
    }
    assert.equal(requests.length, before);
    planReply = {
      saved: true,
      plan_id: 10,
      name: fields.name,
      revision,
      secret: "omit",
    };
    const updated = await call("/central-plans/10", update, admin);
    assert.equal(updated.status, 201);
    assert.deepEqual(await updated.json(), {
      saved: true,
      plan_id: 10,
      name: fields.name,
      revision,
    });
    assert.deepEqual(requests.at(-1).body, { ...update, actor_id: admin });
    assert.equal((await call("/central-plans/10", update, admin)).status, 201);
    assert.deepEqual(requests.at(-1).body, requests.at(-2).body);
    planReply = { saved: false, conflict: true };
    assert.equal((await call("/central-plans/10", update, admin)).status, 409);
    planReply = { saved: true, plan_id: 11, name: fields.name, revision };
    assert.equal((await call("/central-plans/10", update, admin)).status, 503);
    planRevoke = true;
    for (const [path, body, reply] of [
      ["/central-plans", undefined, centralCatalogue],
      ["/central-plans/10", undefined, detail],
      [
        "/central-plans/10",
        update,
        { saved: true, plan_id: 10, name: fields.name, revision },
      ],
    ]) {
      await pg.query("UPDATE users SET is_superadmin=true WHERE id=$1", [
        admin,
      ]);
      planReply = reply;
      assert.equal((await call(path, body, admin)).status, 403);
    }
  } finally {
    await app.close();
    await pg.close();
  }
});
