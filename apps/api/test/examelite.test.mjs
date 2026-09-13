import "reflect-metadata";
import { test } from "node:test";
import assert from "node:assert/strict";
import { connectionFor, ExamEliteService } from "../dist/examelite.service.js";

const org = "11111111-1111-4111-8111-111111111111",
  learner = "22222222-2222-4222-8222-222222222222";
const config = { enabled: true, token: "a".repeat(64), organization_id: 1 };
test("ExamElite private mapping fails closed and never inherits a tenant grant", () => {
  assert.equal(connectionFor({ [org]: config }, "other"), null);
  assert.equal(connectionFor(Object.create({ [org]: config }), org), null);
  assert.equal(connectionFor({ [org]: { enabled: false } }, org), null);
  assert.throws(() =>
    connectionFor({ [org]: { ...config, token: "bad" } }, org),
  );
  assert.deepEqual(connectionFor({ [org]: config }, org), {
    token: config.token,
    organization_id: 1,
  });
});
test("ExamElite authorises before network and verifies local learner scope", async () => {
  let calls = 0;
  const denied = new ExamEliteService(
    {
      require: async () => {
        throw Error("denied");
      },
    },
    {},
    {},
  );
  denied.configuration = async () => {
    calls++;
    return config;
  };
  await assert.rejects(() => denied.status({}, org), /denied/);
  assert.equal(calls, 0);
  const service = new ExamEliteService(
    { require: async () => {} },
    {
      detail: async () => {
        throw Error("learner denied");
      },
    },
    {},
  );
  service.configuration = async () => {
    calls++;
    return config;
  };
  await assert.rejects(
    () => service.list({}, org, "0", learner),
    /learner denied/,
  );
  assert.equal(calls, 0);
  await assert.rejects(() => service.list({}, org, "../status"));
});
test("ExamElite transport forbids redirects, masks secrets and bounds responses", async () => {
  const original = globalThis.fetch;
  const service = new ExamEliteService({}, {}, {});
  try {
    globalThis.fetch = async (url, options) => {
      assert.equal(url, "https://examelite.com/api/tech4learn/v1/status");
      assert.equal(options.redirect, "error");
      assert.equal(options.headers["X-Tech4Learn-Organisation"], org);
      return new Response(JSON.stringify({ version: 1, organization_id: 2 }));
    };
    await assert.rejects(() => service.request(config, org, "status"));
    globalThis.fetch = async () => {
      throw Error(config.token);
    };
    await assert.rejects(
      () => service.request(config, org, "status"),
      (e) => !e.message.includes(config.token),
    );
    globalThis.fetch = async () => new Response("x".repeat(512001));
    await assert.rejects(() => service.request(config, org, "status"));
  } finally {
    globalThis.fetch = original;
  }
});
test("ExamElite strips provider extras and rejects wrong learner/cursor responses", async () => {
  const service = new ExamEliteService(
    { require: async () => {} },
    { detail: async () => {} },
    {},
  );
  service.configuration = async () => config;
  service.request = async () => ({
    items: [
      {
        id: 1,
        name: "Exam",
        duration: 60,
        start_date: null,
        end_date: null,
        secret: "hidden",
      },
    ],
    next: null,
  });
  assert.deepEqual(await service.list({}, org), {
    items: [
      { id: 1, name: "Exam", duration: 60, start_date: null, end_date: null },
    ],
    next: null,
  });
  service.request = async () => ({
    items: [],
    learner_id: "wrong",
    next: null,
  });
  await assert.rejects(() => service.list({}, org, "0", learner));
  service.request = async () => ({ items: [], next: 0 });
  await assert.rejects(() => service.list({}, org));
});
