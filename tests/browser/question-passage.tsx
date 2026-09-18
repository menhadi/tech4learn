import React from "react";
import { createRoot } from "react-dom/client";
import { ExamQuestionEditor } from "../../apps/admin/src/ExamQuestionEditor";
import { DraftScope } from "../../apps/admin/src/DraftForm";
const root = createRoot(document.getElementById("root")!);
const org = "11111111-1111-1111-1111-111111111111";
let central = false,
  creating = false,
  writes: any[] = [],
  record: any,
  newRecord: any;
const button = (name: string) =>
  [...document.querySelectorAll("button")].find(
    (b) => b.textContent?.trim() === name,
  )!;
async function until(check: () => unknown) {
  for (let i = 0; i < 200; i++) {
    if (check()) return;
    await new Promise((r) => setTimeout(r, 25));
  }
  throw Error("Timed out: " + check);
}
window.fetch = async (input, options: any = {}) => {
  const url = String(input),
    prefix = central
      ? `/platform/exam-content/${org}/central`
      : `/organisations/${org}/exam-content`;
  if (!url.includes(prefix)) throw Error("Wrong owner route");
  let data: any;
  if (options.method === "POST") {
    if (!url.endsWith(prefix + "/questions" + (creating ? "" : "/7")))
      throw Error("Wrong question write route");
    const body = JSON.parse(options.body);
    writes.push(body);
    if (writes.length === 3)
      return new Response(
        JSON.stringify({ message: "Synthetic validation rejection" }),
        { status: 422, headers: { "Content-Type": "application/json" } },
      );
    record = {
      ...record,
      id: 7,
      revision: "b".repeat(64),
      fields: { ...record.fields, ...body.fields },
    };
    if (writes.length === 1) throw Error("Synthetic acknowledgement lost");
    data = record;
  } else if (url.includes("/choices/passages"))
    data = { items: [{ id: 9, label: "Synthetic owned passage" }], next: null };
  else if (url.includes("/choices/")) {
    const kind = url.split("/choices/")[1].split("?")[0];
    const catalogues: Record<string, any[]> = {
      types: [{ id: 2, label: "Numerical", type: "NAT" }],
      groups: [{ id: 2, label: "Synthetic group" }],
      languages: [{ id: 3, label: "English" }],
      subjects: [],
      topics: [],
      subtopics: [],
      sections: [],
      difficulties: [],
    };
    if (!(kind in catalogues))
      throw Error("Unexpected choice request: " + kind);
    data = { items: catalogues[kind], next: null };
  } else data = url.endsWith("/new") ? newRecord : record;
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
    const { owner } = scenario;
    creating = scenario.creating;
    central = owner;
    writes = [];
    record = {
      id: creating ? 0 : 7,
      revision: creating ? "new" : "a".repeat(64),
      type: "NAT",
      type_name: "Numerical",
      fields: {
        qtype_id: 2,
        question: "<p>How many?</p>",
        language_id: 3,
        group_ids: [2],
        marks: 1,
        negative_marks: 0,
        status: "Yes",
        nat_mode: "exact",
        nat_value: 7,
        passage_id: null,
      },
    };
    newRecord = structuredClone(record);
    const user = crypto.randomUUID();
    const mount = () =>
      root.render(
        <DraftScope user={user} org={org}>
          <ExamQuestionEditor
            key={String(owner)}
            org={org}
            central={owner}
            id={creating ? "new" : 7}
            onClose={() => {}}
          />
        </DraftScope>,
      );
    mount();
    await until(() => document.querySelector("summary"));
    document.querySelector("summary")!.click();
    await until(() => button("Search passage"));
    button("Search passage").click();
    await until(() =>
      [...document.querySelectorAll("option")].some(
        (o) => o.textContent === "Synthetic owned passage",
      ),
    );
    const select = [...document.querySelectorAll("select")].find((s) =>
      [...s.options].some((o) => o.textContent === "Synthetic owned passage"),
    )!;
    Object.getOwnPropertyDescriptor(
      HTMLSelectElement.prototype,
      "value",
    )!.set!.call(select, "9");
    select.dispatchEvent(new Event("change", { bubbles: true }));
    await until(() => !button("Save question").disabled);
    button("Save question").click();
    await until(
      () =>
        writes.length === 1 &&
        !button("Save question").disabled &&
        document.body.textContent?.includes("Synthetic acknowledgement lost"),
    );
    if (
      !button("Back to question bank").disabled ||
      !button("Reload saved question").disabled ||
      document
        .querySelector('[aria-label="Question"]')
        ?.getAttribute("contenteditable") !== "false" ||
      ![...document.querySelectorAll("select")].every((s) =>
        s.matches(":disabled"),
      )
    )
      throw Error("Question edits were not locked after an unconfirmed save");
    if (creating) {
      root.render(<div />);
      await new Promise((resolve) => setTimeout(resolve, 30));
      mount();
      await until(() => button("Restore draft"));
      button("Restore draft").click();
      await until(
        () =>
          button("Back to question bank").disabled &&
          document
            .querySelector('[aria-label="Question"]')
            ?.getAttribute("contenteditable") === "false",
      );
    }
    button("Save question").click();
    await until(() => writes.length === 2 && record.fields.passage_id === 9);
    if (JSON.stringify(writes[0]) !== JSON.stringify(writes[1]))
      throw Error("Changed retry");
    if (!creating && Object.keys(writes[0].fields).join() !== "passage_id")
      throw Error("Unrelated fields submitted");
    if (creating) {
      if (writes[0].revision !== "new" || writes[0].fields.passage_id !== 9)
        throw Error("Restored new question lost its original content");
      await until(() => !button("Back to question bank").disabled);
      root.render(<div />);
      await new Promise((resolve) => setTimeout(resolve, 30));
      continue;
    }
    const choosePassage = async (value: string) => {
      Object.getOwnPropertyDescriptor(
        HTMLSelectElement.prototype,
        "value",
      )!.set!.call(select, value);
      select.dispatchEvent(new Event("change", { bubbles: true }));
      await new Promise((resolve) => setTimeout(resolve, 0));
    };
    await until(() => !button("Back to question bank").disabled);
    await choosePassage("");
    button("Save question").click();
    await until(
      () =>
        writes.length === 3 &&
        !button("Save question").disabled &&
        document.body.textContent?.includes("Synthetic validation rejection"),
    );
    if (
      button("Back to question bank").disabled ||
      select.disabled ||
      document
        .querySelector('[aria-label="Question"]')
        ?.getAttribute("contenteditable") !== "true"
    )
      throw Error("Validation rejection did not reopen question editing");
    await choosePassage("9");
    button("Save question").click();
    await until(
      () => writes.length === 4 && !button("Back to question bank").disabled,
    );
    if (
      writes[3].request_id === writes[2].request_id ||
      writes[3].fields.passage_id !== 9
    )
      throw Error("Corrected question save reused a rejected request");
    root.render(<div />);
    await new Promise((r) => setTimeout(r, 50));
  }
  root.render(
    <h1>
      PASS: question passage picker — both owners, scoped choices, minimal
      updates, locked identical retry, new-question draft recovery and editable
      validation recovery.
    </h1>,
  );
}
run().catch((error) => root.render(<h1>FAIL: {String(error)}</h1>));
