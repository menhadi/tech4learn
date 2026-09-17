import React, { useState } from "react";
import { createRoot } from "react-dom/client";
import { FormattedField } from "../../apps/admin/src/ExamQuestionEditor";
import { DraftScope, DraftForm } from "../../apps/admin/src/DraftForm";
const source = "/storage/images/upload/original.png",
  asset = "t4l-media:" + "c".repeat(64),
  root = createRoot(document.getElementById("root")!);
const button = (text: string) =>
  [...document.querySelectorAll("button")].find((b) => b.textContent === text);
const until = async (test: () => any) => {
  for (let i = 0; i < 150; i++) {
    await new Promise((r) => setTimeout(r, 30));
    if (test()) return;
  }
  throw Error("Timeout " + test);
};
function App({
  initial,
  locked = false,
}: {
  initial: string;
  locked?: boolean;
}) {
  const [value, setValue] = useState(initial);
  return (
    <DraftScope user={initial} org="synthetic-append">
      <DraftForm
        draftKey="append-text"
        title="Wording"
        draftState={{ value }}
        restoreState={(s) => setValue(s.value)}
        onSubmit={(e) => e.preventDefault()}
      >
        <FormattedField
          label="Wording"
          value={value}
          originalImageWording={initial}
          previewValue={initial.replace(source, asset)}
          mediaBase="/synthetic/media"
          disabled={locked}
          onChange={setValue}
        />
        <output>{value}</output>
      </DraftForm>
    </DraftScope>
  );
}
async function run() {
  for (const [i, initial] of [
    '<img src="' + source + '" alt="Diagram">',
    '<img src="' + asset + '" alt="Diagram">',
    "<math><mi>x</mi></math>",
    '<p>Existing</p><img src="' + source + '"><math><mi>x</mi></math>',
  ].entries()) {
    root.render(<App key={i} initial={initial} />);
    await until(() => button("Apply text change"));
    document.querySelectorAll("details").forEach((d) => (d.open = true));
    const select = document.querySelector(
      "details select",
    ) as HTMLSelectElement;
    select.value = String(select.options.length - 1);
    select.dispatchEvent(new Event("change", { bubbles: true }));
    await until(() =>
      document
        .querySelector("details label")
        ?.parentElement?.textContent?.includes("New paragraph text"),
    );
    const text = document.querySelector(
      "details textarea",
    ) as HTMLTextAreaElement;
    Object.getOwnPropertyDescriptor(
      HTMLTextAreaElement.prototype,
      "value",
    )!.set!.call(text, "Added <script>literal</script> & text");
    text.dispatchEvent(new Event("input", { bubbles: true }));
    await until(() => !button("Apply text change")!.disabled);
    button("Apply text change")!.click();
    await until(() =>
      document
        .querySelector("output")
        ?.textContent?.includes(
          "<p>Added &lt;script&gt;literal&lt;/script&gt; &amp; text</p>",
        ),
    );
    const saved = document.querySelector("output")!.textContent!;
    if (!saved.startsWith(initial))
      throw Error("Existing image/formula changed");
    if (document.querySelector('[aria-label="Wording preview"] script'))
      throw Error("Executable text");
  }
  root.render(
    <App key="locked" initial={'<img src="' + source + '">'} locked />,
  );
  await until(() => button("Apply text change")!.disabled);
  if (
    !(document.querySelector("details textarea") as HTMLTextAreaElement)
      .disabled
  )
    throw Error("Locked append editable");
  document.body.prepend(
    Object.assign(document.createElement("h1"), {
      textContent:
        "PASS: append text to image-only, opaque translation, formula-only and mixed wording without changing media; locked editor preserved",
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
