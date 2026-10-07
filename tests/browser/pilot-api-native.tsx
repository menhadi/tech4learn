import React from "react";
import { createRoot } from "react-dom/client";
import { StudentExamAttempt } from "../../apps/admin/src/StudentExamAttempt";
import { ExamResults } from "../../apps/admin/src/ExamResults";
import { DraftScope } from "../../apps/admin/src/DraftForm";

const student = document.getElementById("student")!,
  staff = document.getElementById("staff")!;
// Forward every request to the real gateway. Simulate only a lost successful
// acknowledgement; no native payload or persisted outcome is mocked.
const transport = window.fetch.bind(window);
const lost = new Set<string>();
const attempts: Record<string, string[]> = {};
window.fetch = async (input, options = {}) => {
  const url = new URL(String(input), location.origin);
  const kind = options.method === "POST"
    ? url.pathname.endsWith("/student-exam/attachments") ? "upload"
      : url.pathname.endsWith("/attachments/extract") ? "extract"
      : url.pathname.endsWith("/attempt/answer") ? "answer"
      : url.pathname.endsWith("/attempt/submit") ? "submit"
      : /\/exam-results\/[^/]+\/attempts\/[0-9]+$/.test(url.pathname) ? "mark"
      : null
    : null;
  const body = String(options.body ?? "");
  if (kind) (attempts[kind] ??= []).push(body);
  const reply = await transport(input, options);
  if (kind && reply.ok && !lost.has(kind + body)) {
    lost.add(kind + body);
    await reply.arrayBuffer();
    throw Error("Synthetic lost acknowledgement after real native " + kind);
  }
  return reply;
};
const assert = (condition: unknown, message: string) => {
  if (!condition) throw Error(message);
};
function attach(text: string, name: string) {
  const input = student.querySelector<HTMLInputElement>('input[type="file"]')!;
  assert(input && !input.disabled, "Answer attachment control is unavailable");
  const transfer = new DataTransfer();
  transfer.items.add(new File([text], name, { type: "text/plain" }));
  input.files = transfer.files;
  input.dispatchEvent(new Event("change", { bubbles: true }));
}
async function fileContents(link: HTMLAnchorElement, expected: string) {
  const response = await transport(link.href);
  assert(response.ok, "Private download failed: " + response.status);
  assert(response.headers.get("content-disposition")?.startsWith("attachment;"), "Download is not forced as an attachment");
  assert(response.headers.get("cache-control")?.includes("no-store"), "Download may be cached");
  assert(response.headers.get("x-content-type-options") === "nosniff", "Download lacks nosniff");
  assert(await response.text() === expected, "Private file bytes do not match");
}
const button = (root: Element, label: string) =>
  [...root.querySelectorAll("button")].find((b) => b.textContent === label)!;
async function until(predicate: () => unknown) {
  for (let i = 0; i < 400; i++) {
    await new Promise((resolve) => setTimeout(resolve, 50));
    if (predicate()) return;
  }
  throw Error("Timed out waiting for screen");
}
const request = async (path: string, body?: unknown) => {
  const reply = await fetch(path, {
    method: body === undefined ? "GET" : "POST",
    headers: {
      "Content-Type": "application/json",
      "X-Tech4Learn-Request": "1",
    },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const text = await reply.text(),
    data = text ? JSON.parse(text) : null;
  if (!reply.ok) throw Error(`${reply.status}: ${JSON.stringify(data)}`);
  return data;
};
async function run() {
  const fixture = await request("/pilot-fixture"),
    { org, learner, exam } = fixture;
  const root = `/api/v1/organisations/${org}`;
  await request(root + "/student-exam/exchange", {
    token: fixture.fragment.split(".")[1],
  });
  let studentRoot = createRoot(student);
  const mountStudent = () => studentRoot.render(
    <StudentExamAttempt base={`/organisations/${org}/student-exam`} />,
  );
  mountStudent();
  await until(() => button(student, "Check exam setup"));
  button(student, "Check exam setup").click();
  await until(() => button(student, "Start or resume exam"));
  button(student, "Start or resume exam").click();
  await until(
    () =>
      student.querySelector("textarea") &&
      !student.querySelector("fieldset")!.disabled,
  );
  const answer = "Two pairs each have two items, giving four.";
  document.getElementById("status")!.textContent = "Running connected private file upload and retry";
  const original = "First synthetic answer draft.";
  attach(original, "draft.txt");
  await until(() => button(student, "Retry last request"));
  button(student, "Retry last request").click();
  await until(() => student.querySelector('a[download]') && !student.querySelector("fieldset")!.disabled);
  const originalUrl = student.querySelector<HTMLAnchorElement>('a[download]')!.href;
  await fileContents(student.querySelector<HTMLAnchorElement>('a[download]')!, original);
  attach(answer, "reviewed-answer.txt");
  await until(() => button(student, "Retry last request"));
  button(student, "Retry last request").click();
  await until(() => student.querySelector<HTMLAnchorElement>('a[download]')?.href !== originalUrl && !student.querySelector("fieldset")!.disabled);
  const savedUrl = student.querySelector<HTMLAnchorElement>('a[download]')!.href;
  await fileContents(student.querySelector<HTMLAnchorElement>('a[download]')!, answer);
  assert((await transport(originalUrl)).status === 404, "Superseded attachment is still readable");
  assert(attempts.upload.length === 4 && attempts.upload[0] === attempts.upload[1] && attempts.upload[2] === attempts.upload[3], "Upload/replacement retry changed identity or bytes");

  document.getElementById("status")!.textContent = "Running connected resume and native text extraction";
  studentRoot.unmount();
  studentRoot = createRoot(student);
  mountStudent();
  await until(() => button(student, "Check exam setup"));
  button(student, "Check exam setup").click();
  await until(() => button(student, "Start or resume exam"));
  button(student, "Start or resume exam").click();
  await until(() => student.querySelector<HTMLAnchorElement>('a[download]')?.href === savedUrl && !student.querySelector("fieldset")!.disabled);
  assert(student.querySelector<HTMLTextAreaElement>("textarea")!.value === "", "Upload saved a written answer implicitly");
  button(student, "Choose extraction language").click();
  await until(() => student.querySelector('select') && !button(student, "Extract text from saved file").disabled);
  button(student, "Extract text from saved file").click();
  await until(() => button(student, "Retry last request"));
  button(student, "Retry last request").click();
  await until(() => student.querySelector<HTMLTextAreaElement>("textarea")!.value === answer && !button(student, "Save answer").disabled);
  assert(attempts.extract.length === 2 && attempts.extract[0] === attempts.extract[1], "Extraction retry changed its saved file or language");
  assert(student.querySelector<HTMLInputElement>('input[type="file"]')!.disabled, "Unsaved extracted writing permits conflicting uploads");
  const persisted = await request(root + "/student-exam/attempt/start", { request_id: crypto.randomUUID() });
  assert(!persisted.questions[0].answer, "Extraction saved the reviewed draft implicitly");
  await until(() => !button(student, "Save answer").disabled);
  button(student, "Save answer").click();
  await until(() => button(student, "Retry last request"));
  button(student, "Retry last request").click();
  await until(() => student.textContent!.includes("Answer saved."));
  assert(attempts.answer.length === 2 && attempts.answer[0] === attempts.answer[1], "Answer retry changed its saved revision/text");
  button(student, "Finish exam").click();
  await until(() => button(student, "Submit saved answers"));
  button(student, "Submit saved answers").click();
  await until(() => button(student, "Retry last request"));
  button(student, "Retry last request").click();
  await until(() => student.textContent!.includes("Exam submitted"));
  assert(attempts.submit.length === 2 && attempts.submit[0] === attempts.submit[1], "Submission retry changed identity");
  assert((await transport(savedUrl)).status === 403, "Student can download after submission");
  const extractBody = JSON.parse(attempts.extract[0]);
  assert((await transport(root + "/student-exam/attachments/extract", { method: "POST", headers: { "Content-Type": "application/json", "X-Tech4Learn-Request": "1" }, body: JSON.stringify(extractBody) })).status === 403, "Student can extract after submission");
  if (student.textContent!.includes("70"))
    throw Error("Result published before marking");
  createRoot(staff).render(
    <DraftScope user="synthetic-connected-marker" org={org}>
      <ExamResults org={org} />
    </DraftScope>,
  );
  await until(() => button(staff, "View results"));
  button(staff, "View results").click();
  await until(() => button(staff, "Review result"));
  button(staff, "Review result").click();
  await until(
    () =>
      staff.textContent!.includes(answer) &&
      staff.querySelector('input[name^="mark-"]'),
  );
  const file = staff.querySelector<HTMLAnchorElement>('a[download]')!;
  assert(file?.textContent === "Download student answer file", "Staff private file review is missing");
  await fileContents(file, answer);
  const mark = staff.querySelector<HTMLInputElement>('input[name^="mark-"]')!;
  mark.value = "7";
  mark.dispatchEvent(new Event("input", { bubbles: true }));
  await until(() => !button(staff, "Save all marks").disabled);
  button(staff, "Save all marks").click();
  await until(() => button(staff, "Retry saving marks"));
  button(staff, "Retry saving marks").click();
  await until(
    () =>
      button(staff, "Review result") &&
      !button(staff, "Choose another student").disabled,
  );
  assert(attempts.mark.length === 2 && attempts.mark[0] === attempts.mark[1], "Marking retry changed its revision/marks");
  button(student, "Refresh result").click();
  await until(() => !button(student, "Refresh result").disabled);
  if (student.textContent!.includes("70"))
    throw Error("Marking published hidden result");
  const current = await request(
    root + `/exam-content/taxonomy/exams/${exam.id}`,
  );
  await request(
    root + `/exam-content/exams/${exam.id}/actions/set-result-status`,
    {
      fields: { result_after_finish: true },
      revision: current.revision,
      request_id: crypto.randomUUID(),
    },
  );
  button(student, "Refresh result").click();
  await until(() => student.textContent!.includes("70"));
  button(staff, "Review result").click();
  await until(() =>
    staff.textContent!.includes("No answers are awaiting manual marking."),
  );
  const history = await request(root + `/exam-results/${learner}/attempts`);
  if (history.items.length !== 1 || history.items[0].score_percent !== 70)
    throw Error("Native history mismatch");
  await request(root + `/exam-student-access/${fixture.grantId}/revoke`, {});
  assert((await transport(root + "/student-exam/attempt/result", { method: "POST", headers: { "Content-Type": "application/json", "X-Tech4Learn-Request": "1" }, body: JSON.stringify({attempt_id:history.items[0].attempt_id,request_id:crypto.randomUUID()}) })).status === 401, "Revoked grant still permits student results");
  document.getElementById("status")!.textContent =
    "PASS: real browser → Tech4Learn HTTP API → native ExamElite: private file upload/replacement/retries/download, resume, native extraction draft, reviewed save, submission, staff file review, marking/retry, publication/history and revocation";
}
run().catch((error) => {
  document.getElementById("status")!.textContent = "FAIL: " + error.message;
});
