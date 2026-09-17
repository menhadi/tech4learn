import React from "react";
import { createRoot } from "react-dom/client";
import { ExamTaxonomy } from "../../apps/admin/src/ExamTaxonomy";
import { DraftScope } from "../../apps/admin/src/DraftForm";
const root = createRoot(document.getElementById("root")!);
const org = "11111111-1111-1111-1111-111111111111";
let central = false,
  kind = "categories",
  attempts: any[] = [],
  removed = false,
  absent = false;
const button = (text: string) =>
  [...document.querySelectorAll("button")].find(
    (b) => b.textContent?.trim() === text,
  );
const until = async (test: () => any) => {
  for (let i = 0; i < 120; i++) {
    await new Promise((r) => setTimeout(r, 25));
    if (test()) return;
  }
  throw Error("Timed out: " + test);
};
function change(el: HTMLInputElement | HTMLSelectElement, value: string) {
  Object.getOwnPropertyDescriptor(
    el instanceof HTMLSelectElement
      ? HTMLSelectElement.prototype
      : HTMLInputElement.prototype,
    "value",
  )!.set!.call(el, value);
  el.dispatchEvent(
    new Event(el instanceof HTMLSelectElement ? "change" : "input", {
      bubbles: true,
    }),
  );
}
window.fetch = async (input, options: any = {}) => {
  const url = String(input),
    prefix = central
      ? `/platform/exam-content/${org}/central`
      : `/organisations/${org}/exam-content`;
  if (!url.includes(prefix)) throw Error("Wrong scope " + url);
  let result: any;
  if (url.endsWith("/delete")) {
    attempts.push(JSON.parse(options.body));
    if (attempts.length === 1) throw Error("Synthetic lost response");
    removed = true;
    result = { id: 9, deleted: true };
  } else if (url.includes("/choices/"))
    result = {
      items: removed ? [] : [{ id: 9, name: "Synthetic category" }],
      next: null,
    };
  else if (url.endsWith("/new"))
    result = { id: 0, revision: "new", fields: {} };
  else if (absent)
    return new Response(JSON.stringify({ message: "Record unavailable" }), {
      status: 404,
    });
  else
    result = {
      id: 9,
      revision: "a".repeat(64),
      fields: {
        name: "Synthetic category",
        code: "synthetic",
        value1: "Yes",
        value2: "No",
        title: "Synthetic category",
        status: true,
        group_ids: [],
        parent_id: kind === "subcategories" ? 3 : null,
      },
    };
  return new Response(JSON.stringify(result), {
    headers: { "Content-Type": "application/json" },
  });
};
async function open() {
  await until(() => document.querySelector("select"));
  change(document.querySelector("select")!, kind);
  await until(() => button("Load classification"));
  button("Load classification")!.click();
  await until(() => button("Edit"));
  button("Edit")!.click();
  await until(() => button("Delete classification"));
}
async function run() {
  for (const scope of [false, true])
    for (const type of scope
      ? ["categories", "subcategories", "languages"]
      : ["categories", "subcategories"]) {
      central = scope;
      kind = type;
      attempts = [];
      removed = false;
      absent = false;
      root.render(
        <DraftScope user={crypto.randomUUID()} org={org}>
          <ExamTaxonomy key={`${scope}-${type}`} org={org} central={scope} />
        </DraftScope>,
      );
      await new Promise((r) => setTimeout(r, 50));
      await open();
      if (!button("Delete classification")!.disabled)
        throw Error("Missing confirmation");
      const title = document.querySelector(
        "input[required]",
      ) as HTMLInputElement;
      change(title, "Edited");
      await until(() => button("Reload saved classification"));
      (
        document.querySelector(
          '[aria-label="Delete classification"] input',
        ) as HTMLInputElement
      ).click();
      await new Promise((r) => setTimeout(r, 50));
      if (!button("Delete classification")!.disabled)
        throw Error("Unsaved edits can be deleted");
      button("Reload saved classification")!.click();
      await until(
        () =>
          document.querySelector<HTMLInputElement>("input[required]")?.value ===
          "Synthetic category",
      );
      if (
        (
          document.querySelector(
            '[aria-label="Delete classification"] input',
          ) as HTMLInputElement
        ).checked
      )
        throw Error("Confirmation restored");
      (
        document.querySelector(
          '[aria-label="Delete classification"] input',
        ) as HTMLInputElement
      ).click();
      await until(() => !button("Delete classification")!.disabled);
      button("Delete classification")!.click();
      await until(() => button("Retry deletion"));
      if (
        !(
          document.querySelector(
            '[aria-label="Classification fields"]',
          ) as HTMLFieldSetElement
        ).disabled ||
        !button("Back to classification")!.disabled
      )
        throw Error("Uncertain deletion not frozen");
      if (Object.keys(attempts[0]).sort().join() !== "request_id,revision")
        throw Error("Unexpected deletion payload");
      button("Retry deletion")!.click();
      await until(() =>
        document.body.textContent?.includes("Classification deleted."),
      );
      if (JSON.stringify(attempts[0]) !== JSON.stringify(attempts[1]))
        throw Error("Retry changed request");
      if (button("Save classification")) throw Error("Deleted editor remains");
      button("Back to classification")!.click();
      await until(() => button("Load classification"));
      button("Load classification")!.click();
      await new Promise((r) => setTimeout(r, 80));
      if (button("Edit")) throw Error("Stale list retained");
    }
  attempts = [];
  removed = false;
  absent = false;
  root.render(
    <DraftScope user={crypto.randomUUID()} org={org}>
      <ExamTaxonomy key="missing-reload" org={org} central={true} />
    </DraftScope>,
  );
  await new Promise((r) => setTimeout(r, 50));
  await open();
  (
    document.querySelector(
      '[aria-label="Delete classification"] input',
    ) as HTMLInputElement
  ).click();
  await until(() => !button("Delete classification")!.disabled);
  button("Delete classification")!.click();
  await until(() => button("Retry deletion"));
  absent = true;
  button("Reload saved classification")!.click();
  await until(() => button("Retry loading classification"));
  if (
    button("Back to classification")!.disabled ||
    button("Retry deletion") ||
    button("Save classification")
  )
    throw Error("Missing record reload trapped editor");
  document.body.prepend(
    Object.assign(document.createElement("h1"), {
      textContent:
        "PASS: category and central language deletion confirmation, dirty edits, exact retry, list refresh and missing-record reload",
    }),
  );
}
run().catch((e) =>
  document.body.prepend(
    Object.assign(document.createElement("h1"), {
      textContent: "FAIL: " + e.message,
    }),
  ),
);
