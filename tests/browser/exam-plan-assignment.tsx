import React from "react";
import { createRoot } from "react-dom/client";
import { ExamPlanAssignment } from "../../apps/admin/src/ExamPlanAssignment";
import { DraftScope } from "../../apps/admin/src/DraftForm";
const root = createRoot(document.getElementById("root")!);
let writes: any[] = [];
let fail = true;
let conflict = false;
let reads = 0;
let saved = 0;
let pages = false,
  changed = false;
const revision = "a".repeat(64);
window.fetch = async (input, init) => {
  if (init?.method === "POST") {
    writes.push(JSON.parse(String(init.body)));
    if (fail) throw Error("Synthetic lost response");
    if (conflict)
      return new Response(JSON.stringify({ message: "Reload changed plan" }), {
        status: 409,
      });
    return new Response(
      JSON.stringify({
        saved: true,
        plan_id: 2,
        assignment_revision: revision,
      }),
    );
  }
  reads++;
  if (pages) {
    const more = String(input).includes("after=50");
    return new Response(
      JSON.stringify({
        assignment_revision: more && changed ? "b".repeat(64) : revision,
        items: Array.from({ length: more ? 2 : 50 }, (_, index) => ({
          id: index + (more ? 51 : 1),
          name: `Plan ${index + (more ? 51 : 1)}`,
          selected: false,
          revision,
        })),
        next: more ? null : "50",
      }),
    );
  }
  return new Response(
    JSON.stringify({
      assignment_revision: revision,
      items: [
        { id: 1, name: "Current synthetic", selected: true, revision },
        { id: 2, name: "New synthetic", selected: false, revision },
      ],
      next: null,
    }),
  );
};
const until = async (check: () => unknown) => {
  for (let i = 0; i < 150; i++) {
    if (check()) return;
    await new Promise((r) => setTimeout(r, 25));
  }
  throw Error("Timed out: " + check);
};
const button = (text: string) =>
  [...document.querySelectorAll("button")].find((b) => b.textContent === text)!;
const render = async (key: string, org = "synthetic-plan-one") => {
  root.render(
    <div data-scenario={key}>
      <DraftScope user="synthetic-plan-admin" org={org}>
        <ExamPlanAssignment key={key} org={org} onSaved={() => saved++} />
      </DraftScope>
    </div>,
  );
  await until(() => document.querySelector(`[data-scenario="${key}"]`));
};
function select() {
  const field = document.querySelector("select")!;
  Object.getOwnPropertyDescriptor(
    HTMLSelectElement.prototype,
    "value",
  )!.set!.call(field, "2");
  field.dispatchEvent(new Event("change", { bubbles: true }));
}
async function run() {
  for (const key of Object.keys(localStorage))
    if (key.includes("synthetic-plan")) localStorage.removeItem(key);
  await render("first");
  await until(() => button("Load plan choices"));
  if (reads) throw Error("Loaded without explicit request");
  button("Load plan choices").click();
  await until(() => document.querySelectorAll("option").length === 3);
  select();
  await until(() => !button("Assign plan").disabled);
  button("Assign plan").click();
  await until(() => document.querySelector('[role="alert"]'));
  if (
    !document.querySelector("fieldset")!.disabled ||
    !button("Load plan choices").disabled
  )
    throw Error("Uncertain write can change selection");
  await render("restored");
  await until(() => button("Restore draft"));
  button("Restore draft").click();
  await until(() => button("Retry plan assignment"));
  await render("restored-again");
  await until(() => button("Restore draft"));
  button("Restore draft").click();
  await until(() => button("Retry plan assignment"));
  fail = false;
  button("Retry plan assignment").click();
  await until(() => saved === 1);
  if (
    writes.length !== 2 ||
    JSON.stringify(writes[0]) !== JSON.stringify(writes[1])
  )
    throw Error("Retry changed request after draft restore");
  await render("after-success");
  await until(
    () => button("Load plan choices") && !button("Load plan choices").disabled,
  );
  if (button("Restore draft")) {
    button("Restore draft").click();
    await new Promise((resolve) => setTimeout(resolve, 50));
    if (button("Retry plan assignment"))
      throw Error("Confirmed assignment left an uncertain draft");
  }
  await until(
    () => button("Load plan choices") && !button("Load plan choices").disabled,
  );
  button("Load plan choices").click();
  await until(() => document.querySelectorAll("option").length === 3);
  select();
  await until(() => !button("Assign plan").disabled);
  conflict = true;
  button("Assign plan").click();
  await until(() => document.body.textContent?.includes("Reload changed plan"));
  if (button("Load plan choices").disabled || button("Retry plan assignment"))
    throw Error("Conflict does not allow a fresh read");
  await render("other", "synthetic-plan-two");
  await until(() => !document.querySelector('[role="alert"]'));
  if (
    document.querySelectorAll("option").length !== 1 ||
    button("Restore draft")
  )
    throw Error("Another organisation inherits choices or draft");
  pages = true;
  button("Load plan choices").click();
  await until(() => button("Load more plans"));
  button("Load more plans").click();
  await until(() => document.querySelectorAll("option").length === 53);
  if (button("Load more plans"))
    throw Error("Completed page cursor remains active");
  button("Load plan choices").click();
  await until(() => button("Load more plans"));
  changed = true;
  button("Load more plans").click();
  await until(() =>
    document.body.textContent?.includes("The organisation plan changed"),
  );
  if (document.querySelectorAll("option").length !== 1)
    throw Error("Changed assignment merges stale choices");
  document.body.prepend(
    Object.assign(document.createElement("h1"), {
      textContent:
        "PASS: plan selection, scoped repeated draft retry, conflict reload, pagination and changed assignment clearing",
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
