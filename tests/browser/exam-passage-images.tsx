import React from "react";
import { createRoot } from "react-dom/client";
import { ExamTaxonomy } from "../../apps/admin/src/ExamTaxonomy";
import { DraftScope } from "../../apps/admin/src/DraftForm";
const root = createRoot(document.getElementById("root")!);
const org = "11111111-1111-1111-1111-111111111111";
const png =
  "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=";
let central = false,
  attempts: any[] = [],
  record: any;
const button = (name: string) =>
  [...document.querySelectorAll("button")].find(
    (b) => b.textContent?.trim() === name,
  )!;
const until = async (fn: () => unknown) => {
  for (let i = 0; i < 200; i++) {
    if (fn()) return;
    await new Promise((r) => setTimeout(r, 25));
  }
  throw Error("Timed out: " + fn);
};
const choose = (field: HTMLSelectElement, value: string) => {
  Object.getOwnPropertyDescriptor(
    HTMLSelectElement.prototype,
    "value",
  )!.set!.call(field, value);
  field.dispatchEvent(new Event("change", { bubbles: true }));
};
window.fetch = async (input, options: any = {}) => {
  const url = String(input),
    base = central
      ? `/platform/exam-content/${org}/central`
      : `/organisations/${org}/exam-content`;
  if (!url.includes(base)) throw Error("Wrong owner route");
  let data: any;
  if (options.method === "POST") {
    if (!url.endsWith("/passages/9/image")) throw Error("Wrong image endpoint");
    const body = JSON.parse(options.body);
    attempts.push(body);
    if (attempts.length === 1) throw Error("Synthetic lost acknowledgement");
    data = { ...record, revision: "b".repeat(64) };
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
  else data = record;
  return new Response(JSON.stringify(data), {
    headers: { "Content-Type": "application/json" },
  });
};
async function run() {
  for (const owner of [false, true])
    for (const action of ["append", "replace", "remove"]) {
      central = owner;
      attempts = [];
      record = {
        id: 9,
        revision: "a".repeat(64),
        fields: {
          name: "Synthetic passage",
          passages: {
            3: '<p>Words</p><img src="/storage/synthetic.png">',
            4: "<p>Unchanged</p>",
          },
        },
      };
      root.render(
        <DraftScope user={crypto.randomUUID()} org={org}>
          <ExamTaxonomy org={org} central={central} />
        </DraftScope>,
      );
      await until(() => document.querySelector("select"));
      choose(document.querySelector("select")!, "passages");
      await until(() => button("Load classification"));
      button("Load classification").click();
      await until(() => button("Edit"));
      button("Edit").click();
      await until(() =>
        [...document.querySelectorAll("option")].some(
          (o) => o.textContent === "Replace image 1",
        ),
      );
      const option = [...document.querySelectorAll("option")].find(
        (o) =>
          o.textContent ===
          (action === "remove" ? "Remove image 1" : "Replace image 1"),
      )!;
      const destination = option.closest("select")!;
      choose(destination, action === "append" ? "" : option.value);
      await new Promise((r) => setTimeout(r, 0));
      if (action !== "remove") {
        const dt = new DataTransfer();
        dt.items.add(
          new File(
            [Uint8Array.from(atob(png), (c) => c.charCodeAt(0))],
            "test.png",
            { type: "image/png" },
          ),
        );
        const input =
          document.querySelector<HTMLInputElement>('input[type="file"]')!;
        input.files = dt.files;
        input.dispatchEvent(new Event("change", { bubbles: true }));
      }
      const save = action === "remove" ? "Remove image" : "Save image";
      await until(() => button(save) && !button(save).disabled);
      button(save).click();
      const retry =
        action === "remove" ? "Retry image removal" : "Retry image upload";
      await until(() => button(retry) && !button(retry).disabled);
      if (
        !button("Back to classification").disabled ||
        !document
          .querySelector<HTMLInputElement>('input[maxlength="255"]')!
          .matches(":disabled") ||
        !document
          .querySelector<HTMLSelectElement>("select")!
          .matches(":disabled")
      )
        throw Error("Uncertain image save left edits unlocked");
      if (JSON.stringify(localStorage).includes(png))
        throw Error("Image bytes leaked into draft");
      button(retry).click();
      await until(() =>
        document.body.textContent?.includes("Passage image saved."),
      );
      if (
        attempts.length !== 2 ||
        JSON.stringify(attempts[0]) !== JSON.stringify(attempts[1])
      )
        throw Error("Retry changed request");
      const fields = attempts[0].fields;
      if (
        fields.language_id !== 3 ||
        fields.field !== undefined ||
        fields.question_id !== undefined ||
        fields.passages !== undefined
      )
        throw Error("Wrong destination fields");
      if (
        action === "remove"
          ? fields.remove !== true || fields.image !== undefined
          : fields.image !== png
      )
        throw Error("Wrong image payload");
      if (action !== "append" && !/^[a-f0-9]{64}$/.test(fields.asset))
        throw Error("Missing scoped asset");
      if (record.fields.passages[4] !== "<p>Unchanged</p>")
        throw Error("Other language changed");
      root.render(<div />);
      await new Promise((r) => setTimeout(r, 40));
    }
  root.render(
    <h1>
      PASS: passage images, both owners, upload, replace, remove, scoped
      language, identical retries, edit locks and no draft bytes.
    </h1>,
  );
}
run().catch((e) => root.render(<h1>FAIL: {String(e)}</h1>));
