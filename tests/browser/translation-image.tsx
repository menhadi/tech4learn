import React from "react";
import { createRoot } from "react-dom/client";
import { DraftScope } from "../../apps/admin/src/DraftForm";
import { ExamTranslationEditor } from "../../apps/admin/src/ExamTranslationEditor";

const root = createRoot(document.getElementById("root")!);
const png =
  "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=";
const asset = "c".repeat(64),
  requests: { url: string; body: any }[] = [];
let fail = true,
  saved = 0;
window.fetch = async (input, init) => {
  const url = String(input);
  if (
    !url.endsWith("/actions/set-translation-image") &&
    !url.endsWith("/actions/save-question-translation")
  )
    throw Error("Unexpected request");
  requests.push({ url, body: JSON.parse(String(init?.body)) });
  if (fail) {
    fail = false;
    throw Error("Synthetic lost response");
  }
  return new Response(JSON.stringify({ id: 9, revision: "d".repeat(64) }), {
    status: 200,
    headers: { "Content-Type": "application/json" },
  });
};
const button = (name: string) =>
  [...document.querySelectorAll("button")].find((b) => b.textContent === name)!;
const until = async (check: () => unknown) => {
  for (let i = 0; i < 200; i++) {
    if (check()) return;
    await new Promise((r) => setTimeout(r, 25));
  }
  throw Error("Timed out: " + check);
};
function render(central: boolean, missing = false) {
  root.render(
    <DraftScope user={"synthetic-image-" + String(central)} org="synthetic-org">
      <ExamTranslationEditor
        key={String(central) + missing}
        org="synthetic-org"
        central={central}
        examId={9}
        examRevision={"a".repeat(64)}
        languageId={5}
        translationRevision={"b".repeat(64)}
        question={{
          question_id: 7,
          source: {
            question: "Source",
            option1: "One",
            si_answer1: "Source answer",
          },
          translation: missing
            ? null
            : {
                question: `<p>Translated<img src="t4l-media:${asset}"></p>`,
                option1: "Translated one",
                si_answer1: `<p>Model answer<img src="t4l-media:${asset}"></p>`,
              },
        }}
        mediaBase="/synthetic-media"
        onClose={() => {}}
        onSaved={() => saved++}
      />
    </DraftScope>,
  );
}
async function run() {
  for (const central of [false, true]) {
    fail = true;
    const before = requests.length;
    const savedBefore = saved;
    render(central);
    await until(() => button("Save image"));
    const imageForm = [...document.querySelectorAll("form")].find((f) =>
      f.textContent?.includes("Image destination"),
    )!;
    const destination = imageForm.querySelector("select")!;
    destination.value = "si_answer1";
    destination.dispatchEvent(new Event("change", { bubbles: true }));
    await until(() => destination.value === "si_answer1");
    const fileInput = document.querySelector(
      'input[type="file"]',
    ) as HTMLInputElement;
    const data = new DataTransfer();
    data.items.add(
      new File(
        [Uint8Array.from(atob(png), (c) => c.charCodeAt(0))],
        "synthetic.png",
        { type: "image/png" },
      ),
    );
    fileInput.files = data.files;
    fileInput.dispatchEvent(new Event("change", { bubbles: true }));
    await until(() => !button("Save image").disabled);
    button("Save image").click();
    await until(() => button("Retry image upload"));
    if (
      !button("Close translation editor").disabled ||
      !button("Save translated wording").disabled
    )
      throw Error("Uncertain image save did not lock wording");
    const first = requests[before];
    if (
      first.body.fields.language_id !== 5 ||
      first.body.fields.question_id !== 7 ||
      first.body.fields.translation_revision !== "b".repeat(64) ||
      first.body.revision !== "a".repeat(64) ||
      first.body.fields.image !== png ||
      first.body.fields.field !== "si_answer1"
    )
      throw Error("Wrong image identity/payload");
    if (
      !first.url.includes(
        central
          ? "/platform/exam-content/synthetic-org/central/exams/9/"
          : "/organisations/synthetic-org/exam-content/exams/9/",
      )
    )
      throw Error("Wrong owner endpoint");
    button("Retry image upload").click();
    await until(
      () =>
        requests.length === before + 2 &&
        saved === savedBefore + 1 &&
        !button("Close translation editor").disabled,
    );
    if (
      JSON.stringify(requests[before]) !== JSON.stringify(requests[before + 1])
    )
      throw Error("Retry changed request");
    const form = [...document.querySelectorAll("form")].find((f) =>
      f.textContent?.includes("Image destination"),
    )!;
    const action = form.querySelectorAll("select")[1];
    action.value = asset;
    action.dispatchEvent(new Event("change", { bubbles: true }));
    fileInput.files = data.files;
    fileInput.dispatchEvent(new Event("change", { bubbles: true }));
    await until(() => !button("Save image").disabled);
    button("Save image").click();
    await until(
      () =>
        saved === savedBefore + 2 &&
        !button("Close translation editor").disabled,
    );
    if (
      requests.at(-1)!.body.fields.asset !== asset ||
      requests.at(-1)!.body.fields.image !== png
    )
      throw Error("Replacement lost asset or bytes");
    for (const key of Object.keys(localStorage).filter((key) =>
      key.includes("synthetic-image-"),
    )) {
      if (localStorage.getItem(key)?.includes(png))
        throw Error("Image bytes leaked into recovered draft");
    }
    action.value = "remove:" + asset;
    action.dispatchEvent(new Event("change", { bubbles: true }));
    await until(() => button("Remove image"));
    button("Remove image").click();
    await until(
      () =>
        requests.length === before + 4 &&
        saved === savedBefore + 3 &&
        !button("Close translation editor").disabled,
    );
    const removed = requests.at(-1)!.body.fields;
    if (
      removed.remove !== true ||
      removed.asset !== asset ||
      "image" in removed
    )
      throw Error("Removal included file or wrong asset");
  }
  if (saved !== 6)
    throw Error("Successful writes did not request fresh review");
  render(false, true);
  await until(
    () =>
      !button("Save image") &&
      document.querySelector(
        '[role="textbox"][aria-label="Translated model answer"][contenteditable="true"]',
      ),
  );
  const answerInput = document.querySelector(
    '[role="textbox"][aria-label="Translated model answer"]',
  ) as HTMLElement;
  const questionInput = document.querySelector(
    '[role="textbox"][aria-label="Translated question"]',
  ) as HTMLElement;
  if (!answerInput || !questionInput)
    throw Error("Missing translated wording control");
  const setText = (input: HTMLElement, value: string) => {
    input.textContent = value;
    input.dispatchEvent(new Event("input", { bubbles: true }));
  };
  setText(questionInput, "New translated question");
  setText(answerInput, "New translated model answer");
  await until(() => !button("Save translated wording").disabled);
  button("Save translated wording").click();
  await until(() => saved === 7);
  if (
    requests.at(-1)!.body.fields.wording.si_answer1 !==
    "New translated model answer"
  )
    throw Error("Model answer missing from wording save");
  document.body.prepend(
    Object.assign(document.createElement("h1"), {
      textContent:
        "PASS: organisation/central translated upload, identical retry, editor locks, reference removal and missing-target gating",
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
