import React from "react";
import { createRoot } from "react-dom/client";
import { ExamTranslationEditor } from "../../apps/admin/src/ExamTranslationEditor";
const root = createRoot(document.getElementById("root")!);
const png =
  "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=";
const requests: any[] = [];
let fail = true,
  saved = 0;
window.fetch = async (input, init) => {
  requests.push({ url: String(input), body: JSON.parse(String(init?.body)) });
  if (fail) {
    fail = false;
    throw Error("Synthetic lost image response");
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
const button = (name: string) =>
  [...document.querySelectorAll("button")].find((b) => b.textContent === name)!;
async function run() {
  for (const central of [false, true]) {
    fail = true;
    const before = requests.length,
      count = saved;
    root.render(
      <div data-owner={String(central)}>
        <ExamTranslationEditor
          key={String(central)}
          org="synthetic-exam-upload"
          mode="exam"
          central={central}
          examId={9}
          examRevision={"a".repeat(64)}
          languageId={5}
          translationRevision={"b".repeat(64)}
          question={{
            question_id: 0,
            source: { name: "Source", instruction: "Original", syllabus: null },
            translation: {
              name: "Translated",
              instruction: "Translated instructions",
              syllabus: null,
            },
          }}
          mediaBase="/synthetic-media"
          onClose={() => {}}
          onSaved={() => saved++}
        />
      </div>,
    );
    await until(() => document.querySelector(`[data-owner="${central}"]`));
    const input = document.querySelector(
      'input[type="file"]',
    ) as HTMLInputElement;
    if (!input) throw Error("Missing exam image input");
    const file = new DataTransfer();
    file.items.add(
      new File(
        [Uint8Array.from(atob(png), (c) => c.charCodeAt(0))],
        "synthetic.png",
        { type: "image/png" },
      ),
    );
    input.files = file.files;
    input.dispatchEvent(new Event("change", { bubbles: true }));
    await until(() => !button("Save image").disabled);
    button("Save image").click();
    await until(() => button("Retry image upload"));
    if (
      !button("Close translation editor").disabled ||
      !button("Save translated wording").disabled
    )
      throw Error("Uncertain exam upload does not lock wording");
    const first = requests[before];
    if (
      first.body.fields.question_id !== 0 ||
      first.body.fields.field !== "instruction" ||
      first.body.fields.image !== png ||
      !first.url.endsWith("/actions/set-translation-image")
    )
      throw Error("Wrong translated exam image payload");
    if (
      !first.url.includes(central ? "/central/exams/" : "/exam-content/exams/")
    )
      throw Error("Wrong owner path");
    button("Retry image upload").click();
    await until(() => saved === count + 1);
    if (JSON.stringify(first) !== JSON.stringify(requests[before + 1]))
      throw Error("Retry changed exam image request");
  }
  document.body.prepend(
    Object.assign(document.createElement("h1"), {
      textContent:
        "PASS: translated exam image upload, default instruction destination, both owners, identical retry and editor locks",
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
