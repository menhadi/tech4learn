import { test } from "node:test";
import assert from "node:assert/strict";
import { centralExamMetadata } from "../dist/central-exam-metadata.js";

test("central exam metadata projects native paper data and rejects malformed snapshots", () => {
  const draft = {
    id: 0,
    test_types: { full_length: "Full-Length Tests" },
    timezone: "Asia/Kolkata",
  };
  assert.deepEqual(centralExamMetadata(draft, true), {
    test_types: draft.test_types,
    timezone: draft.timezone,
  });
  const record = {
    ...draft,
    id: 7,
    status: "Active",
    sections: [
      {
        id: 1,
        exam_id: 7,
        name: "Section",
        display_order: 0,
        duration: null,
        created_at: "private",
      },
    ],
    paper_subjects: [{ id: 2, name: "Subject", secret: "private" }],
    subject_durations: [{ exam_id: 7, subject_id: 2, duration: "10" }],
  };
  const result = centralExamMetadata(record, false);
  assert.deepEqual(result.sections, [
    { id: 1, name: "Section", display_order: 0, duration: null },
  ]);
  assert.deepEqual(result.subject_durations, [{ subject_id: 2, duration: 10 }]);
  assert.deepEqual(result.paper_subjects, [{ id: 2, name: "Subject" }]);
  for (const patch of [
    { test_types: [] },
    { test_types: {} },
    { test_types: { full_length: {} } },
    { timezone: "not/a/timezone" },
    { status: "unknown" },
    { sections: null },
    { paper_subjects: [{ id: 2, name: {} }] },
    { sections: [{ ...record.sections[0], exam_id: 8 }] },
    { sections: [record.sections[0], record.sections[0]] },
    { sections: [{ ...record.sections[0], duration: -1 }] },
    { subject_durations: [{ ...record.subject_durations[0], exam_id: 8 }] },
    {
      subject_durations: [{ ...record.subject_durations[0], duration: "NaN" }],
    },
    {
      subject_durations: [{ ...record.subject_durations[0], subject_id: "2" }],
    },
    {
      paper_subjects: Array.from({ length: 1001 }, (_, i) => ({
        id: i + 1,
        name: "Subject",
      })),
    },
  ])
    assert.throws(
      () => centralExamMetadata({ ...record, ...patch }, false),
      (e) => e.getStatus() === 503,
    );
  assert.throws(() =>
    centralExamMetadata({ ...draft, status: "Active" }, true),
  );
});
