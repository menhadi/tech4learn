import React from "react";
import { createRoot } from "react-dom/client";
import { ExamCapabilities } from "../../apps/admin/src/ExamCapabilities";
const root = createRoot(document.getElementById("root")!);
let fail = false;
let last = "";
window.fetch = async (input) => {
  last = String(input);
  if (fail) throw Error("Synthetic catalogue unavailable");
  return new Response(
    JSON.stringify({
      revision: 1,
      native_features: [
        { key: "ai_translation", enabled: true },
        { key: "paid_packages", enabled: false },
      ],
    }),
    { headers: { "Content-Type": "application/json" } },
  );
};
const until = async (check: () => unknown) => {
  for (let i = 0; i < 150; i++) {
    if (check()) return;
    await new Promise((r) => setTimeout(r, 25));
  }
  throw Error("Timed out: " + check);
};
const load = () =>
  [...document.querySelectorAll("button")].find(
    (b) => b.textContent === "Load plan permissions",
  )!;
async function run() {
  root.render(<ExamCapabilities key="one" org="synthetic-one" />);
  await until(load);
  if (last) throw Error("Catalogue read without request");
  load().click();
  await until(() => document.body.textContent?.includes("AI translation"));
  if (
    !last.endsWith("/organisations/synthetic-one/exam-workspace/capabilities")
  )
    throw Error("Wrong owner");
  if (
    !document.body.textContent?.includes("Not allowed") ||
    !document.body.textContent?.includes("do not mean every feature")
  )
    throw Error("Entitlements misrepresented as implemented tools");
  fail = true;
  load().click();
  await until(() => document.querySelector('[role="alert"]'));
  if (document.querySelector("table"))
    throw Error("Failed refresh retains stale plan permissions");
  fail = false;
  root.render(<ExamCapabilities key="two" org="synthetic-two" />);
  await until(() => !document.querySelector('[role="alert"]'));
  if (document.querySelector("table"))
    throw Error("Other organisation inherits catalogue");
  load().click();
  await until(() => document.querySelector("table"));
  if (!last.includes("synthetic-two"))
    throw Error("Refresh uses previous organisation");
  document.body.prepend(
    Object.assign(document.createElement("h1"), {
      textContent:
        "PASS: plan permissions, scope, explicit loading, failure clearing and availability wording",
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
