import React from "react";
import { createRoot } from "react-dom/client";
import { ExamPlanCreate } from "../../apps/admin/src/ExamPlanCreate";
import { DraftScope } from "../../apps/admin/src/DraftForm";
const root = createRoot(document.getElementById("root")!);
const writes: any[] = [];
let fail = true,
  deny = false;
window.fetch = async (url, init) => {
  if (init?.method !== "POST")
    return new Response(
      JSON.stringify({
        features: ["reports", "ai_translation"],
        limits: ["students", "exams"],
      }),
    );
  writes.push({ url: String(url), body: JSON.parse(String(init.body)) });
  if (fail) throw Error("Synthetic lost reply");
  if (deny)
    return new Response(JSON.stringify({ message: "Permission changed" }), {
      status: 403,
    });
  return new Response(
    JSON.stringify({
      saved: true,
      plan_id: 9,
      revision: "a".repeat(64),
      name: writes.at(-1).body.fields.name,
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
  const element = input(name);
  Object.getOwnPropertyDescriptor(
    HTMLInputElement.prototype,
    "value",
  )!.set!.call(element, value);
  element.dispatchEvent(new Event("input", { bubbles: true }));
}
async function render(key: string, org = "synthetic-plan-create-one") {
  root.render(
    <div data-scenario={key}>
      <DraftScope user="synthetic-plan-creator" org={org}>
        <ExamPlanCreate key={key} org={org} />
      </DraftScope>
    </div>,
  );
  await until(() => document.querySelector(`[data-scenario="${key}"]`));
  await until(() => button("New exam plan"));
  button("New exam plan").click();
  await until(() => input("name"));
}
async function run() {
  for (const key of Object.keys(localStorage))
    if (key.includes("synthetic-plan-create")) localStorage.removeItem(key);
  await render("first");
  if (
    !input("feature_reports").checked ||
    !input("feature_ai_translation").checked
  )
    throw Error("Capabilities not enabled by default");
  fill("name", " Synthetic new plan ");
  fill("limit_students", "0");
  input("feature_reports").click();
  await until(() => !input("feature_reports").checked);
  button("Create plan").click();
  await until(() => button("Retry plan creation"));
  if ([...document.querySelectorAll("fieldset")].some((f) => !f.disabled))
    throw Error("Uncertain request not locked");
  const fields = writes[0].body.fields;
  if (
    fields.name !== "Synthetic new plan" ||
    fields.feature_reports !== false ||
    fields.feature_ai_translation !== true ||
    fields.limit_students !== 0 ||
    fields.limit_exams !== null
  )
    throw Error("Incorrect native field conversion");
  await render("restore");
  await until(() => button("Restore draft"));
  button("Restore draft").click();
  await until(() => button("Retry plan creation"));
  await render("restore-again");
  await until(() => button("Restore draft"));
  button("Restore draft").click();
  await until(() => button("Retry plan creation"));
  fail = false;
  button("Retry plan creation").click();
  await until(() =>
    document.body.textContent?.includes("created. Load plan choices"),
  );
  if (
    writes.length !== 2 ||
    JSON.stringify(writes[0]) !== JSON.stringify(writes[1])
  )
    throw Error("Restored retry changed identity or settings");
  if (input("name").value !== "" || !input("feature_reports").checked)
    throw Error("Successful save did not reset defaults");
  fill("name", "Inactive synthetic plan");
  input("status").click();
  await until(() => !input("status").checked);
  button("Create plan").click();
  await until(() =>
    document.body.textContent?.includes(
      "will not appear in active plan choices",
    ),
  );
  if (writes.at(-1).body.fields.status !== false)
    throw Error("Inactive status was lost");
  await render("other", "synthetic-plan-create-two");
  if (button("Restore draft") || button("Retry plan creation"))
    throw Error("Cross-organisation draft leakage");
  fill("name", "Denied creation");
  deny = true;
  await until(() => input("name").value === "Denied creation");
  button("Create plan").click();
  await until(() => document.body.textContent?.includes("Permission changed"));
  if (
    button("Retry plan creation") ||
    document.querySelector("fieldset")!.disabled
  )
    throw Error("Definitive rejection leaves stale pending request");
  document.body.prepend(
    Object.assign(document.createElement("h1"), {
      textContent:
        "PASS: default capabilities, native fields, repeated draft retry, reset, organisation isolation and permission rejection",
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
