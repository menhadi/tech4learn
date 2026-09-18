import React from "react";
import { createRoot } from "react-dom/client";
import { DraftScope } from "../../apps/admin/src/DraftForm";
import { ExamTranslationEditor } from "../../apps/admin/src/ExamTranslationEditor";
const root = createRoot(document.getElementById("root")!);
const asset = "c".repeat(64);
const writes: any[] = [];
let fail = true,
  saved = 0;
window.fetch = async (input, init) => {
  writes.push({ url: String(input), body: JSON.parse(String(init?.body)) });
  if (fail) {
    fail = false;
    throw Error("Synthetic uncertain save");
  }
  return new Response(JSON.stringify({ id: 9, revision: "d".repeat(64) }));
};
const until = async (check: () => unknown) => {
  for (let i = 0; i < 200; i++) {
    if (check()) return;
    await new Promise((r) => setTimeout(r, 25));
  }
  throw Error("Timed out: " + check);
};
const button = (label: string) =>
  [...document.querySelectorAll("button")].find(
    (b) => b.textContent === label,
  )!;
async function run() {
  for (const central of [false, true]) {
    fail = true;
    const before = writes.length;
    const count = saved;
    root.render(
      <div data-owner={String(central)}>
        <DraftScope user="synthetic-exam-images" org="synthetic-owner">
          <ExamTranslationEditor
            key={String(central)}
            org="synthetic-owner"
            central={central}
            mode="exam"
            examId={9}
            examRevision={"a".repeat(64)}
            languageId={5}
            translationRevision={"b".repeat(64)}
            question={{
              question_id: 0,
              source: {
                name: "Source",
                instruction: "Source instructions",
                syllabus: null,
              },
              translation: {
                name: "Translated",
                instruction: `<p>Before <img src="t4l-media:${asset}"> <math><mi>x</mi></math></p>`,
                syllabus: null,
              },
            }}
            mediaBase="/synthetic-media"
            onClose={() => {}}
            onSaved={() => saved++}
          />
        </DraftScope>
      </div>,
    );
    await until(() => document.querySelector(`[data-owner="${central}"]`));
    const details = [...document.querySelectorAll("details")].find(
      (d) =>
        d.querySelector("summary")?.textContent === "Edit surrounding text",
    )!;
    if (!details)
      throw Error("Exam wording with images cannot edit surrounding text");
    details.open = true;
    const textarea = details.querySelector("textarea")!;
    Object.getOwnPropertyDescriptor(
      HTMLTextAreaElement.prototype,
      "value",
    )!.set!.call(textarea, "After ");
    textarea.dispatchEvent(new Event("input", { bubbles: true }));
    await until(() => !button("Apply text change").disabled);
    button("Apply text change").click();
    await until(() => !button("Save translated wording").disabled);
    button("Save translated wording").click();
    await until(() => document.querySelector('[role="alert"]'));
    const first = writes[before];
    if (
      !first.url.endsWith("/actions/save-exam-translation") ||
      !first.body.fields.wording.instruction.includes(`t4l-media:${asset}`) ||
      !first.body.fields.wording.instruction.includes("After") ||
      "question_id" in first.body.fields
    )
      throw Error("Invalid retained exam wording request");
    if (
      !first.url.includes(central ? "/central/exams/" : "/exam-content/exams/")
    )
      throw Error("Wrong owner endpoint");
    button("Retry translation save").click();
    await until(() => saved === count + 1);
    if (JSON.stringify(writes[before]) !== JSON.stringify(writes[before + 1]))
      throw Error("Retry changed exam image wording");
    if (!document.querySelector('input[type="file"]'))
      throw Error("Exam image upload control missing");
  }
  document.body.prepend(
    Object.assign(document.createElement("h1"), {
      textContent:
        "PASS: organisation and central translated exam text editing retains images and exact retry",
    }),
  );
}
run().catch((error) =>
  document.body.prepend(
    Object.assign(document.createElement("h1"), {
      textContent: "FAIL: " + error.message,
    }),
  ),
);
