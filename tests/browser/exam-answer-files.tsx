import React from "react";
import { createRoot } from "react-dom/client";
import { StudentExamAttempt } from "../../apps/admin/src/StudentExamAttempt";
const root = document.getElementById("root")!;
const org = "11111111-1111-1111-1111-111111111111", asset = "a".repeat(64);
const uploads: string[] = [], extractions: string[] = [], answers: any[] = [];
let uploaded = false;
window.fetch = async (input, options = {}) => {
  const url = String(input), body = options.body ? JSON.parse(String(options.body)) : null;
  let result: any;
  if (url.endsWith("/prepare")) result = { exam_id: 7, proctor: false };
  else if (url.endsWith("/start")) result = { attempt_id: 11, exam_id: 7, name: "Synthetic file paper", remaining_seconds: 0, time_limited: false,
    settings: { allow_answer_change: false, calculator_allowed: false }, questions: [{ id: 4, number: 1, type: "subjective", content: { question: "Explain the answer." },
      passage: null, answer: "", review: false, answer_locked: false, revision: uploaded ? "c".repeat(64) : "b".repeat(64), attachments_enabled: true, attachment_asset: uploaded ? asset : null }] };
  else if (url.endsWith("/attachments")) {
    uploads.push(JSON.stringify(body));
    if (body.attempt_id !== 11 || body.question_id !== 4 || body.revision !== "b".repeat(64) || atob(body.base64) !== "Synthetic answer file.") throw Error("Wrong upload body");
    uploaded = true;
    if (uploads.length === 1) throw Error("Synthetic lost upload acknowledgement");
    result = { saved: true, attempt_id: 11, question_id: 4, revision: "c".repeat(64), asset };
  } else if (url.endsWith("/extract")) {
    extractions.push(JSON.stringify(body));
    if (JSON.stringify(Object.keys(body).sort()) !== JSON.stringify(["asset", "attempt_id", "question_id"]) || body.asset !== asset) throw Error("Wrong extraction identity");
    if (extractions.length === 1) throw Error("Synthetic extraction failure");
    result = { attempt_id: 11, question_id: 4, asset, text: "Extracted synthetic answer." };
  } else if (url.endsWith("/answer")) {
    answers.push(body);
    if (body.revision !== "c".repeat(64) || body.fields.option_selected !== "Extracted synthetic answer." || body.fields.lock_answer !== true) throw Error("Wrong saved answer revision or text");
    result = { saved: true, question_id: 4, revision: "d".repeat(64), answer_locked: true };
  } else throw Error("Unexpected request: " + url);
  return new Response(JSON.stringify(result), { status: 200, headers: { "Content-Type": "application/json" } });
};
const button = (label: string) => [...root.querySelectorAll<HTMLButtonElement>("button")].find(b => b.textContent === label)!;
async function until(check: () => unknown) { for (let n = 0; n < 150; n++) { if (check()) return; await new Promise(r => setTimeout(r, 40)); } throw Error("Timed out"); }
async function run() {
  createRoot(root).render(<StudentExamAttempt base={`/organisations/${org}/student-exam`} />);
  await until(() => button("Check exam setup")); button("Check exam setup").click();
  await until(() => button("Start or resume exam")); button("Start or resume exam").click();
  await until(() => root.querySelector<HTMLInputElement>('input[type="file"]') && !root.querySelector("fieldset")!.disabled);
  const input = root.querySelector<HTMLInputElement>('input[type="file"]')!;
  const transfer = new DataTransfer(); transfer.items.add(new File(["Synthetic answer file."], "answer.txt", { type: "text/plain" }));
  input.files = transfer.files; input.dispatchEvent(new Event("change", { bubbles: true }));
  await until(() => button("Retry last request")); button("Retry last request").click();
  await until(() => root.querySelector('a[download]'));
  if (uploads.length !== 2 || uploads[0] !== uploads[1]) throw Error("Upload retry changed");
  if (!root.querySelector('a[download]')!.getAttribute("href")!.endsWith(`/attachments/11/4/${asset}`)) throw Error("Wrong private download URL");
  button("Extract text from saved file").click();
  await until(() => button("Retry last request")); button("Retry last request").click();
  await until(() => root.querySelector("textarea")!.value === "Extracted synthetic answer.");
  if (answers.length || extractions.length !== 2 || extractions[0] !== extractions[1]) throw Error("Extraction saved implicitly or retry changed");
  if (!root.querySelector<HTMLInputElement>('input[type="file"]')!.disabled) throw Error("Unsaved draft permits conflicting upload");
  button("Save answer").click();
  await until(() => root.textContent!.includes("This answer is locked"));
  if (answers.length !== 1 || !root.querySelector("fieldset")!.disabled) throw Error("Native answer lock was not preserved");
  document.getElementById("status")!.textContent = "PASS: private upload retry, download, extraction draft/retry, reviewed save and answer lock";
}
run().catch(error => { document.getElementById("status")!.textContent = "FAIL: " + error.message; });
