import React from "react";
import { createRoot } from "react-dom/client";
import { ExamQuestionEditor } from "../../apps/admin/src/ExamQuestionEditor";
import { DraftScope } from "../../apps/admin/src/DraftForm";
const root = createRoot(document.getElementById("root")!);
const org = "11111111-1111-1111-1111-111111111111";
let central = false,
  writes: any[] = [],
  record: any;
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
    const body = JSON.parse(options.body);
    writes.push(body);
    if (writes.length === 1) throw Error("Synthetic acknowledgement lost");
    record = {
      ...record,
      revision: "b".repeat(64),
      fields: { ...record.fields, ...body.fields },
    };
    data = record;
  } else if (url.includes("/choices/passages"))
    data = { items: [{ id: 9, label: "Synthetic owned passage" }], next: null };
  else if (url.includes("/choices/")) throw Error("Unexpected choice request");
  else data = record;
  return new Response(JSON.stringify(data), {
    headers: { "Content-Type": "application/json" },
  });
};
async function run() {
  for (const owner of [false, true]) {
    central = owner;
    writes = [];
    record = {
      id: 7,
      revision: "a".repeat(64),
      type: "N",
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
    root.render(
      <DraftScope user={crypto.randomUUID()} org={org}>
        <ExamQuestionEditor
          key={String(owner)}
          org={org}
          central={owner}
          id={7}
          onClose={() => {}}
        />
      </DraftScope>,
    );
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
      () => writes.length === 1 && document.querySelector('[role="alert"]'),
    );
    button("Save question").click();
    await until(() => writes.length === 2 && record.fields.passage_id === 9);
    if (JSON.stringify(writes[0]) !== JSON.stringify(writes[1]))
      throw Error("Changed retry");
    if (Object.keys(writes[0].fields).join() !== "passage_id")
      throw Error("Unrelated fields submitted");
    root.render(<div />);
    await new Promise((r) => setTimeout(r, 50));
  }
  root.render(
    <h1>
      PASS: question passage picker — both owners, scoped choices, minimal
      updates and identical retry.
    </h1>,
  );
}
run().catch((error) => root.render(<h1>FAIL: {String(error)}</h1>));
