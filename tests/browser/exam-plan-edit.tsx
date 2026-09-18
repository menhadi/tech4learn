import React from "react";
import { createRoot } from "react-dom/client";
import { ExamPlanEdit } from "../../apps/admin/src/ExamPlanEdit";
import { DraftScope } from "../../apps/admin/src/DraftForm";
const root = createRoot(document.getElementById("root")!);
const writes: any[] = [];
let fail = true,
  conflict = false;
const revision = "a".repeat(64);
window.fetch = async (url, init) => {
  const path = String(url);
  if (init?.method === "POST") {
    writes.push({ path, body: JSON.parse(String(init.body)) });
    if (fail) throw Error("Synthetic lost response");
    if (conflict)
      return new Response(JSON.stringify({ message: "Plan changed" }), {
        status: 409,
      });
    return new Response(
      JSON.stringify({
        saved: true,
        plan_id: 9,
        name: "Synthetic existing plan",
        revision: "b".repeat(64),
      }),
    );
  }
  if (path.endsWith("/9"))
    return new Response(
      JSON.stringify({
        plan_id: 9,
        revision,
        assigned_organisations: 3,
        is_default: true,
        fields: {
          name: "Synthetic existing plan",
          price: "2.00",
          billing_cycle: "yearly",
          status: false,
          feature_reports: false,
          feature_ai_translation: true,
          limit_students: 20,
          limit_exams: null,
        },
      }),
    );
  return new Response(
    JSON.stringify({
      items: [
        {
          id: 9,
          name: "Synthetic existing plan",
          active: false,
          is_default: true,
          revision,
        },
      ],
      next: null,
    }),
  );
};
const until = async (test: () => unknown) => {
  for (let i = 0; i < 200; i++) {
    if (test()) return;
    await new Promise((r) => setTimeout(r, 25));
  }
  throw Error("Timed out: " + test);
};
const button = (text: string) =>
  [...document.querySelectorAll("button")].find((b) => b.textContent === text)!;
const input = (name: string) =>
  document.querySelector<HTMLInputElement>(`input[name="${name}"]`)!;
function fill(name: string, value: string) {
  const e = input(name);
  Object.getOwnPropertyDescriptor(
    HTMLInputElement.prototype,
    "value",
  )!.set!.call(e, value);
  e.dispatchEvent(new Event("input", { bubbles: true }));
}
async function render(key: string, org = "synthetic-plan-edit-one") {
  root.render(
    <div data-scenario={key}>
      <DraftScope user="synthetic-plan-editor" org={org}>
        <ExamPlanEdit key={key} org={org} />
      </DraftScope>
    </div>,
  );
  await until(() => document.querySelector(`[data-scenario="${key}"]`));
  await until(() => button("Load all plans"));
  button("Load all plans").click();
  await until(() => button("Edit Synthetic existing plan"));
  button("Edit Synthetic existing plan").click();
  await until(() => input("name"));
}
async function run() {
  for (const key of Object.keys(localStorage))
    if (key.includes("synthetic-plan-edit")) localStorage.removeItem(key);
  await render("first");
  if (
    input("status").checked ||
    input("feature_reports").checked ||
    input("limit_students").value !== "20" ||
    input("limit_exams").value !== ""
  )
    throw Error("Stored plan fields not preserved");
  if (!document.body.textContent?.includes("assigned to 3 organisations"))
    throw Error("Shared impact missing");
  fill("limit_students", "50");
  await until(() => input("limit_students").value === "50");
  button("Save plan changes").click();
  await until(() => button("Retry plan update"));
  if (
    !button("Close editor").disabled ||
    [...document.querySelectorAll("fieldset")].some((f) => !f.disabled)
  )
    throw Error("Uncertain edit can be abandoned or changed");
  await render("restore");
  await until(() => button("Restore draft"));
  button("Restore draft").click();
  await until(() => button("Retry plan update"));
  await render("restore-again");
  await until(() => button("Restore draft"));
  button("Restore draft").click();
  await until(() => button("Retry plan update"));
  fail = false;
  button("Retry plan update").click();
  await until(() => document.body.textContent?.includes("Plan saved."));
  if (
    writes.length !== 2 ||
    JSON.stringify(writes[0]) !== JSON.stringify(writes[1]) ||
    writes[0].body.revision !== revision ||
    writes[0].body.fields.limit_students !== 50
  )
    throw Error("Retry identity, revision or settings changed");
  await render("conflict");
  fill("name", "Edited name");
  await until(() => input("name").value === "Edited name");
  conflict = true;
  button("Save plan changes").click();
  await until(() => document.body.textContent?.includes("Close this editor"));
  if (!button("Save plan changes").disabled || button("Close editor").disabled)
    throw Error("Stale plan can be resaved without reload");
  await render("other", "synthetic-plan-edit-two");
  if (button("Restore draft")) throw Error("Cross organisation draft leakage");
  document.body.prepend(
    Object.assign(document.createElement("h1"), {
      textContent:
        "PASS: existing inactive/default plan, shared impact, exact repeated draft retry, stale reload and organisation isolation",
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
