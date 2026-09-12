import { test } from "node:test";
import assert from "node:assert/strict";
import {
  runVision,
  parseVision,
  providerConfig,
  visionProviders,
} from "../dist/vision-providers.js";
const result = {
  visible_people: 2,
  quality: "Readable synthetic register",
  warnings: [],
  entries: [{ code: "DEMO-1", name: "Synthetic learner", mark: "present" }],
};
test("four vision adapters use private inline images, fixed endpoints and validated drafts", async () => {
  for (const p of visionProviders) {
    const key = `T4L_${p.prefix}_API_KEY`,
      model = `T4L_${p.prefix}_VISION_MODEL`,
      oldKey = process.env[key],
      oldModel = process.env[model];
    try {
      delete process.env[key];
      delete process.env[model];
      assert.equal(providerConfig(p.id).configured, false);
      await assert.rejects(
        () => runVision(p.id, "register", Buffer.from("synthetic")),
        (e) => e.getStatus() === 503,
      );
      process.env[key] = "synthetic-test-secret";
      process.env[model] = "synthetic-vision-model";
      let request;
      const response =
        p.id === "openai"
          ? {
              output: [
                {
                  type: "message",
                  content: [
                    { type: "output_text", text: JSON.stringify(result) },
                  ],
                },
              ],
            }
          : p.id === "claude"
            ? { content: [{ type: "text", text: JSON.stringify(result) }] }
            : p.id === "gemini"
              ? {
                  candidates: [
                    { content: { parts: [{ text: JSON.stringify(result) }] } },
                  ],
                }
              : { choices: [{ message: { content: JSON.stringify(result) } }] };
      const run = await runVision(
        p.id,
        "register",
        Buffer.from("synthetic"),
        async (url, options) => {
          request = { url, options, body: JSON.parse(options.body) };
          return Response.json(response);
        },
      );
      assert.deepEqual(run.result, result);
      assert.equal(run.model, "synthetic-vision-model");
      assert.equal(request.options.redirect, "error");
      assert.ok(
        request.options.body.includes(
          Buffer.from("synthetic").toString("base64"),
        ),
      );
      assert.ok(!request.options.body.includes("synthetic-test-secret"));
      if (p.id === "openai") {
        assert.equal(request.url, "https://api.openai.com/v1/responses");
        assert.equal(request.body.store, false);
      }
      if (p.id === "claude") {
        assert.equal(
          request.options.headers["anthropic-version"],
          "2023-06-01",
        );
        assert.equal(request.body.messages[0].content[0].source.type, "base64");
      }
      if (p.id === "gemini") {
        assert.ok(
          request.url.startsWith("https://generativelanguage.googleapis.com/"),
        );
        assert.equal(
          request.options.headers["x-goog-api-key"],
          "synthetic-test-secret",
        );
      }
      if (p.id === "deepseek")
        assert.equal(request.url, "https://api.deepseek.com/chat/completions");
      await assert.rejects(
        () =>
          runVision(
            p.id,
            "register",
            Buffer.from("synthetic"),
            async () => new Response("secret provider error", { status: 401 }),
          ),
        (e) => e.getStatus() === 503 && !e.message.includes("secret"),
      );
    } finally {
      if (oldKey === undefined) delete process.env[key];
      else process.env[key] = oldKey;
      if (oldModel === undefined) delete process.env[model];
      else process.env[model] = oldModel;
    }
  }
});
test("scene analysis never exposes identity guesses as attendance entries", () => {
  assert.deepEqual(parseVision(JSON.stringify(result), "scene").entries, []);
  for (const bad of [
    "not JSON",
    JSON.stringify({
      ...result,
      entries: [{ name: "x", code: "y", mark: "guess" }],
    }),
    JSON.stringify({ ...result, visible_people: -1 }),
  ])
    assert.throws(() => parseVision(bad, "register"));
});
