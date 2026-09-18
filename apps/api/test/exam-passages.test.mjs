import "reflect-metadata";
import { test } from "node:test";
import assert from "node:assert/strict";
import { randomUUID } from "node:crypto";
import { ExamContentService } from "../dist/exam-content.service.js";
import { ExamContentController } from "../dist/exam-content.controller.js";

test("passage previews bind owner, language and revision and withhold bytes after revocation", async () => {
  const user = { id: randomUUID() },
    org = randomUUID(),
    revision = "a".repeat(64),
    asset = "b".repeat(64);
  const base = {
    passage_id: 7,
    language_id: 3,
    revision,
    asset,
    mime: "image/png",
    base64: Buffer.from("synthetic bytes").toString("base64"),
  };
  let payload = base,
    revoked = false,
    revoke = false,
    checks = 0,
    calls = 0,
    audits = 0;
  const remote = {
    request: async (_config, target, path, body, maxBytes, timeout) => {
      calls++;
      assert.equal(target, org);
      assert.equal(body, undefined);
      assert.equal(maxBytes, 14000000);
      assert.equal(timeout, 30000);
      assert.match(
        path,
        new RegExp(
          `/passages/7/languages/3/media/${asset}\\?revision=${revision}$`,
        ),
      );
      if (revoke) revoked = true;
      return { data: payload };
    },
  };
  const service = new ExamContentService(
    {},
    {
      audit: async () => {
        audits++;
      },
    },
    remote,
    {},
  );
  service.config = async () => ({});
  service.centralAccess = async () => {
    checks++;
    if (revoked) throw Error("revoked");
  };
  service.questionAccess = async (_user, _org, _id, feature) => {
    assert.equal(feature, "questions");
    checks++;
    if (revoked) throw Error("revoked");
  };
  for (const central of [false, true]) {
    const read = (query = { revision }, id = "7", lang = "3", key = asset) =>
      service.passageMedia(user, org, id, lang, key, query, central);
    const before = checks;
    assert.deepEqual(await read(), {
      buffer: Buffer.from("synthetic bytes"),
      mime: "image/png",
    });
    assert.equal(checks, before + 2);
    const requests = calls;
    for (const query of [
      {},
      { revision, owner: 99 },
      { revision: [revision] },
      { revision: "wrong" },
    ])
      await assert.rejects(read(query));
    for (const bad of ["new", "0", "1e2", "7/../8"])
      await assert.rejects(read({ revision }, bad));
    await assert.rejects(read({ revision }, "7", "03"));
    await assert.rejects(read({ revision }, "7", "3", "bad"));
    assert.equal(calls, requests);
    for (const patch of [
      { passage_id: 8 },
      { language_id: 4 },
      { revision: "c".repeat(64) },
      { asset: "d".repeat(64) },
      { mime: "image/svg+xml" },
      { base64: "%%%" },
      { base64: "" },
    ]) {
      payload = { ...base, ...patch };
      await assert.rejects(read());
    }
    payload = base;
    const auditBefore = audits;
    revoke = true;
    await assert.rejects(read(), /revoked/);
    assert.equal(audits, auditBefore);
    revoked = false;
    revoke = false;
  }
  // Route handlers must authenticate, rate-limit and return private raster bytes.
  const controller = Object.create(ExamContentController.prototype);
  controller.identity = {
    account: async () => user,
    limit: async (_key, max, seconds) => {
      assert.equal(max, 180);
      assert.equal(seconds, 60);
    },
  };
  controller.content = service;
  for (const name of ["passageImage", "centralPassageImage"]) {
    const headers = {};
    let sent;
    await controller[name](org, "7", "3", asset, { revision }, undefined, {
      setHeader: (key, value) => {
        headers[key] = value;
      },
      send: (value) => {
        sent = value;
      },
    });
    assert.equal(headers["Cache-Control"], "no-store");
    assert.equal(headers["X-Content-Type-Options"], "nosniff");
    assert.deepEqual(sent, Buffer.from("synthetic bytes"));
    assert.match(
      Reflect.getMetadata("path", controller[name]),
      /passages\/:id\/languages\/:language\/media\/:asset$/,
    );
  }
});

test("passage gateway bounds wording, derives actors and rechecks access after remote work", async () => {
  const user = { id: randomUUID(), is_superadmin: true },
    org = randomUUID();
  const revision = "a".repeat(64),
    requests = [],
    launches = [];
  let revoked = false,
    revokeOnResponse = false,
    checks = 0;
  const authorize = async (_user, _org, _id, feature) => {
    checks++;
    assert.equal(feature, "questions");
    if (revoked) throw Error("revoked");
  };
  const fields = {
    name: "Synthetic passage",
    passages: { 3: "<p>Wording</p>" },
  };
  const record = { id: 7, revision, fields };
  const remote = {
    request: async (_config, _org, path, body) => {
      requests.push({ path, body });
      if (revokeOnResponse) revoked = true;
      return path.startsWith("central/")
        ? { kind: "passages", record, saved: true }
        : { ...record, saved: true, question: record };
    },
  };
  const service = new ExamContentService(
    {},
    { audit: async () => {} },
    remote,
    { launch: async (_user, _org, settings) => launches.push(settings) },
  );
  service.config = async () => ({});
  service.questionAccess = authorize;
  service.centralAccess = async () => {
    if (revoked) throw Error("revoked");
  };
  const body = { fields, revision: "new", request_id: randomUUID() };
  assert.deepEqual(
    await service.saveQuestion(user, org, "new", body, "passages"),
    record,
  );
  assert.equal(checks, 2);
  assert.deepEqual(launches[0], { feature: "questions" });
  assert.equal(requests[0].body.actor_id, user.id);
  assert.match(requests[0].path, /taxonomy\/passages\/new$/);
  const before = requests.length;
  for (const invalid of [
    { ...body, actor_id: randomUUID() },
    { ...body, fields: { ...fields, organization_id: 44 } },
    { ...body, fields: { ...fields, passages: { "03": "Text" } } },
    { ...body, fields: { ...fields, passages: [] } },
    { ...body, fields: { ...fields, passages: { 3: "" } } },
    { ...body, fields: { ...fields, passages: { 3: "é".repeat(100001) } } },
  ])
    await assert.rejects(
      service.saveQuestion(user, org, "new", invalid, "passages"),
    );
  assert.equal(
    requests.length,
    before,
    "Invalid writes never reach the engine",
  );
  await service.centralTaxonomy(user, org, "passages", "new", {}, body);
  assert.equal(requests.at(-1).body.actor_id, user.id);
  assert.equal(requests.at(-1).path, "central/taxonomy/passages/new");
  revokeOnResponse = true;
  await assert.rejects(service.taxonomy(user, org, "passages", "7"), /revoked/);
  revoked = false;
  await assert.rejects(
    service.saveQuestion(user, org, "7", { ...body, revision }, "passages"),
    /revoked/,
  );
  revoked = false;
  await assert.rejects(
    service.centralTaxonomy(
      user,
      org,
      "passages",
      "7",
      {},
      { ...body, revision },
    ),
    /revoked/,
  );
});
