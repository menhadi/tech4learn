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
    if (attempts.length === 1)
      record = {
        ...record,
        id: 9,
        revision: "b".repeat(64),
        fields: {
          ...record.fields,
          ...body.fields,
          passages: { ...record.fields.passages, ...body.fields.passages },
        },
      };
    if (attempts.length === 1) throw Error("Synthetic lost acknowledgement");
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
  for (const scenario of [
    { owner: false, creating: false },
    { owner: true, creating: false },
    { owner: false, creating: true },
    { owner: true, creating: true },
  ]) {
    const { owner, creating } = scenario;
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
    if (creating) record = { id: 0, revision: "new", fields: {} };
    const user = crypto.randomUUID();
    const mount = () =>
      root.render(
        <DraftScope user={user} org={org}>
          <ExamTaxonomy key={String(owner)} org={org} central={owner} />
        </DraftScope>,
      );
    mount();
    await until(() => document.querySelector("select"));
    select("passages");
    await until(() => button("Create passages"));
    if (creating) {
      button("Create passages").click();
      await until(() => document.querySelector('input[maxlength="255"]'));
      const name = document.querySelector<HTMLInputElement>(
        'input[maxlength="255"]',
      )!;
      Object.getOwnPropertyDescriptor(
        HTMLInputElement.prototype,
        "value",
      )!.set!.call(name, "Created passage");
      name.dispatchEvent(new Event("input", { bubbles: true }));
      button("Search passage language").click();
      await until(() =>
        [...document.querySelectorAll("option")].some(
          (o) => o.textContent === "English",
        ),
      );
      select("3");
    } else {
      button("Load classification").click();
      await until(() => button("Edit"));
      button("Edit").click();
    }
    await until(() => document.querySelector('[aria-label="Passage wording"]'));
    if (
      document.body.textContent?.includes("Display order") ||
      document.body.textContent?.includes("Exam group")
    )
      throw Error("Unrelated fields exposed");
    const editor = document.querySelector('[aria-label="Passage wording"]')!;
    editor.innerHTML = "<p>Changed wording</p>";
    editor.dispatchEvent(new Event("input", { bubbles: true }));
    // Separate typing from the next click, as real browser input events are.
    await new Promise((resolve) => setTimeout(resolve, 0));
    if (!creating) {
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
    }
    button("Save classification").click();
    await until(
      () =>
        attempts.length === 1 &&
        !button("Save classification").disabled &&
        document.body.textContent?.includes("Synthetic lost acknowledgement"),
    );
    if (
      !button("Back to classification").disabled ||
      !button("Reload saved classification").disabled ||
      !document.querySelector<HTMLInputElement>('input[maxlength="255"]')
        ?.disabled ||
      !document.querySelector<HTMLSelectElement>("select")?.disabled ||
      document
        .querySelector('[aria-label="Passage wording"]')
        ?.getAttribute("contenteditable") !== "false" ||
      button("Save classification").disabled
    )
      throw Error("Uncertain passage save did not lock edits and retain retry");
    if (creating) {
      root.render(<div />);
      await new Promise((resolve) => setTimeout(resolve, 30));
      mount();
      await until(() => document.querySelector("select"));
      select("passages");
      await until(() => button("Create passages"));
      button("Create passages").click();
      await until(() => button("Restore draft"));
      button("Restore draft").click();
      await until(
        () =>
          button("Back to classification").disabled &&
          document.querySelector<HTMLInputElement>('input[maxlength="255"]')
            ?.value === "Created passage" &&
          document.querySelector('[aria-label="Passage wording"]')
            ?.textContent === "Changed wording",
      );
      if (!button("Reload saved classification").disabled)
        throw Error("Restored pending passage lost its write lock");
    }
    button("Save classification").click();
    await until(
      () =>
        attempts.length === 2 &&
        document
          .querySelector('[role="status"]')
          ?.textContent?.includes("saved"),
    );
    if (JSON.stringify(attempts[0]) !== JSON.stringify(attempts[1]))
      throw Error(
        "Retry changed request " +
          JSON.stringify({ owner, creating, attempts }),
      );
    if (Object.keys(attempts[0].fields.passages).join() !== "3")
      throw Error("Untouched language sent as an edit");
    if (
      creating &&
      (attempts[0].revision !== "new" ||
        attempts[0].fields.name !== "Created passage")
    )
      throw Error("New passage payload is incorrect");
    if (!creating && !record.fields.passages[4].includes("synthetic.png"))
      throw Error("Other language changed");
    root.render(<div />);
    await new Promise((r) => setTimeout(r, 40));
  }
  root.render(
    <h1>
      PASS: passage editor — both owners, creation, language switching,
      preserved media, edit locks, restored pending drafts and identical retry
      after lost acknowledgement.
    </h1>,
  );
}
run().catch((error) => root.render(<h1>FAIL: {String(error)}</h1>));
