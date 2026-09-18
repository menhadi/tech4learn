import React from "react";
import { createRoot } from "react-dom/client";
import { ExamTaxonomy } from "../../apps/admin/src/ExamTaxonomy";
import { DraftScope } from "../../apps/admin/src/DraftForm";
const root = createRoot(document.getElementById("root")!);
const org = "11111111-1111-1111-1111-111111111111";
let central = false,
  attempts: any[] = [];
let record: any;
const button = (name: string) =>
  [...document.querySelectorAll("button")].find(
    (b) => b.textContent?.trim() === name,
  )!;
const until = async (predicate: () => unknown) => {
  for (let i = 0; i < 150; i++) {
    if (predicate()) return;
    await new Promise((r) => setTimeout(r, 25));
  }
  throw Error("Timed out: " + predicate);
};
function select(value: string) {
  const field = document.querySelector("select")!;
  Object.getOwnPropertyDescriptor(
    HTMLSelectElement.prototype,
    "value",
  )!.set!.call(field, value);
  field.dispatchEvent(new Event("change", { bubbles: true }));
}
window.fetch = async (input, options: any = {}) => {
  const url = String(input),
    base = central
      ? `/platform/exam-content/${org}/central`
      : `/organisations/${org}/exam-content`;
  if (!url.includes(base)) throw Error("Wrong owner route");
  let data: any;
  if (options.method === "POST") {
    const body = JSON.parse(options.body);
    attempts.push(body);
    if (attempts.length === 1) throw Error("Synthetic lost acknowledgement");
    record = {
      ...record,
      revision: "b".repeat(64),
      fields: {
        ...record.fields,
        ...body.fields,
        passages: { ...record.fields.passages, ...body.fields.passages },
      },
    };
    data = record;
  } else if (url.includes("/choices/languages"))
    data = {
      items: [
        { id: 3, label: "English" },
        { id: 4, label: "Hindi" },
      ],
      next: null,
    };
  else if (url.includes("/choices/passages"))
    data = { items: [{ id: 9, label: "Synthetic passage" }], next: null };
  else if (url.endsWith("/new")) data = { id: 0, revision: "new", fields: {} };
  else data = record;
  return new Response(JSON.stringify(data), {
    headers: { "Content-Type": "application/json" },
  });
};
async function run() {
  for (const owner of [false, true]) {
    central = owner;
    attempts = [];
    record = {
      id: 9,
      revision: "a".repeat(64),
      fields: {
        name: "Synthetic passage",
        passages: {
          3: "<p>Original wording</p>",
          4: '<p>Preserved media</p><img src="/storage/synthetic.png">',
        },
      },
    };
    root.render(
      <DraftScope user={crypto.randomUUID()} org={org}>
        <ExamTaxonomy key={String(owner)} org={org} central={owner} />
      </DraftScope>,
    );
    await until(() => document.querySelector("select"));
    select("passages");
    await until(() => button("Create passages"));
    button("Load classification").click();
    await until(() => button("Edit"));
    button("Edit").click();
    await until(() => document.querySelector('[aria-label="Passage wording"]'));
    if (
      document.body.textContent?.includes("Display order") ||
      document.body.textContent?.includes("Exam group")
    )
      throw Error("Unrelated fields exposed");
    const editor = document.querySelector('[aria-label="Passage wording"]')!;
    editor.innerHTML = "<p>Changed wording</p>";
    editor.dispatchEvent(new Event("input", { bubbles: true }));
    button("Search passage language").click();
    await until(() =>
      [...document.querySelectorAll("option")].some(
        (o) => o.textContent === "Hindi",
      ),
    );
    select("4");
    await until(() =>
      document.body.textContent?.includes("does not support yet"),
    );
    select("3");
    await until(() =>
      document
        .querySelector('[aria-label="Passage wording"]')
        ?.textContent?.includes("Changed wording"),
    );
    button("Save classification").click();
    await until(
      () => attempts.length === 1 && document.querySelector('[role="alert"]'),
    );
    button("Save classification").click();
    await until(
      () =>
        attempts.length === 2 &&
        document
          .querySelector('[role="status"]')
          ?.textContent?.includes("saved"),
    );
    if (JSON.stringify(attempts[0]) !== JSON.stringify(attempts[1]))
      throw Error("Retry changed request");
    if (Object.keys(attempts[0].fields.passages).join() !== "3")
      throw Error("Untouched language sent as an edit");
    if (!record.fields.passages[4].includes("synthetic.png"))
      throw Error("Other language changed");
    root.render(<div />);
    await new Promise((r) => setTimeout(r, 40));
  }
  root.render(
    <h1>
      PASS: passage editor — both owners, language switching, preserved media
      and identical retry.
    </h1>,
  );
}
run().catch((error) => root.render(<h1>FAIL: {String(error)}</h1>));
