import "reflect-metadata";
import { test } from "node:test";
import assert from "node:assert/strict";
import { randomUUID } from "node:crypto";
import { ExamContentService } from "../dist/exam-content.service.js";

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
    service.centralTaxonomy(user, org, "passages", "7", {}, { ...body, revision }),
    /revoked/,
  );
});
