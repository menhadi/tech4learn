import { test } from "node:test";
import assert from "node:assert/strict";
import { randomUUID } from "node:crypto";
import { ExamOmrService } from "../dist/exam-omr.service.js";

test("OMR list uses learner scope and rejects a foreign exam", async () => {
  const org = randomUUID();
  const group = randomUUID();
  const calls = [];
  const db = {
    query: async (sql, params) => {
      calls.push({ sql, params });
      return { rows: [] };
    },
  };
  const access = {
    require: async (_user, _org, permission) =>
      permission === "learners.view"
        ? { scope_type: "groups", scope_ids: [group] }
        : { scope_type: "organisation", scope_ids: [] },
  };
  const exams = { taxonomy: async () => ({ id: 42 }) };
  const service = new ExamOmrService(db, access, exams);
  const actor = { id: randomUUID() };
  assert.deepEqual(await service.list(actor, org, "42"), []);
  assert.match(calls[0].sql, /JOIN learners l/);
  assert.match(calls[0].sql, /g\.id=ANY\(\$4::uuid\[\]\)/);
  assert.deepEqual(calls[0].params, [org, 42, "groups", [group]]);
  await assert.rejects(service.list(actor, org, "43"), /Exam not found/);
  assert.equal(calls.length, 1);
});

test("OMR upload rejects a student outside the learner scope before inserting", async () => {
  const org = randomUUID(), learner = randomUUID(), actor = { id: randomUUID() };
  let inserted = false;
  const sql = { query: async (query) => {
    if (query.startsWith("INSERT INTO exam_omr_scans")) inserted = true;
    return { rows: [] };
  } };
  const db = { transaction: async (run) => run(sql) };
  const access = {
    lock: async () => {},
    require: async (_user, _org, permission) =>
      permission === "learners.view"
        ? { scope_type: "groups", scope_ids: [randomUUID()] }
        : { scope_type: "organisation", scope_ids: [] },
  };
  const exams = { taxonomy: async () => ({ id: 42 }) };
  const service = new ExamOmrService(db, access, exams);
  const file = `data:application/pdf;base64,${Buffer.from("%PDF-1.4\n").toString("base64")}`;
  await assert.rejects(
    service.upload(actor, org, "42", { learner_id: learner, content_type: "application/pdf", file }),
    /Student not found/,
  );
  assert.equal(inserted, false);
});

test("OMR review rechecks the scan student and never updates an inaccessible scan", async () => {
  const org = randomUUID(), learner = randomUUID(), scan = randomUUID();
  let updated = false;
  const sql = { query: async (query) => {
    if (query.startsWith("SELECT learner_id FROM exam_omr_scans"))
      return { rows: [{ learner_id: learner }] };
    if (query.startsWith("UPDATE exam_omr_scans")) updated = true;
    return { rows: [] };
  } };
  const db = { transaction: async (run) => run(sql) };
  const access = {
    lock: async () => {},
    require: async (_user, _org, permission) =>
      permission === "learners.view"
        ? { scope_type: "groups", scope_ids: [randomUUID()] }
        : { scope_type: "organisation", scope_ids: [] },
  };
  const service = new ExamOmrService(db, access, {});
  await assert.rejects(
    service.review({ id: randomUUID() }, org, scan, { revision: 1, answers: { "1": "A" } }),
    /Student not found/,
  );
  assert.equal(updated, false);
});
