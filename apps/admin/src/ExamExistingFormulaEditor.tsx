import { useState } from "react";
import { ExamFormulaInsert } from "./ExamFormulaInsert";
import {
  ExamRichContent,
  examTextTags,
  mathTags,
  mathAttributes,
} from "./ExamRichContent";

/** Refuse lossy conversions: every retained element must fit the native write format. */
function parse(value: string, images = false) {
  if (value.length > 200000) return null;
  const root = document.createElement("template");
  root.innerHTML = value;
  const elements = [...root.content.querySelectorAll("*")];
  if (elements.length > 2000) return null;
  for (const element of elements) {
    const tag = element.localName;
    const math = mathTags.includes(tag);
    if (images && tag === "img") {
      if (!element.getAttribute("src")?.trim()) return null;
      for (const attribute of [...element.attributes]) {
        if (
          !(
            attribute.name === "src" ||
            (attribute.name === "alt" && attribute.value.length <= 1000) ||
            (["width", "height"].includes(attribute.name) &&
              /^[1-9][0-9]{0,3}$/.test(attribute.value))
          )
        )
          return null;
      }
      continue;
    }
    if (!math && !examTextTags.includes(tag)) return null;
    if (math && tag !== "math" && !element.closest("math")) return null;
    for (const attribute of [...element.attributes]) {
      const valid = math
        ? (attribute.name === "xmlns" &&
            tag === "math" &&
            attribute.value === "http://www.w3.org/1998/Math/MathML") ||
          (attribute.name !== "xmlns" &&
            mathAttributes.includes(attribute.name) &&
            attribute.value.length <= 160 &&
            /^[a-zA-Z0-9 .,%+_\-]*$/.test(attribute.value))
        : ["colspan", "rowspan"].includes(attribute.name) &&
          /^[1-9][0-9]{0,2}$/.test(attribute.value);
      if (!valid) return null;
    }
  }
  const formulas = [...root.content.querySelectorAll("math")].filter(
    (element) => !element.parentElement?.closest("math"),
  );
  return formulas.length ? { root, formulas } : null;
}

export function canReplaceExistingFormula(value: string, retainedImages = false) {
  return parse(value, retainedImages) !== null;
}

export function ExamExistingFormulaEditor({
  value,
  disabled,
  onChange,
  retainedImages = false,
}: {
  value: string;
  retainedImages?: boolean;
  disabled: boolean;
  onChange: (value: string) => void;
}) {
  const [selected, setSelected] = useState(0);
  const parsed = parse(value, retainedImages);
  if (!parsed) return null;
  const index = Math.min(selected, parsed.formulas.length - 1);
  return (
    <section data-no-draft="true" aria-label="Edit existing formulas">
      <label>
        Formula to replace
        <select
          value={index}
          disabled={disabled}
          onChange={(event) => setSelected(Number(event.target.value))}
        >
          {parsed.formulas.map((formula, i) => (
            <option key={i} value={i}>
              Formula {i + 1}: {(formula.textContent ?? "").slice(0, 80)}
            </option>
          ))}
        </select>
      </label>
      <section aria-label="Selected formula">
        <ExamRichContent value={parsed.formulas[index].outerHTML} />
      </section>
      <ExamFormulaInsert
        key={value + ":" + index}
        replace
        disabled={disabled}
        onInsert={(html) => {
          if (disabled) return;
          const current = parse(value, retainedImages);
          if (!current?.formulas[index]) return;
          const replacement = document.createElement("template");
          replacement.innerHTML = html;
          current.formulas[index].replaceWith(replacement.content);
          onChange(current.root.innerHTML);
        }}
      />
    </section>
  );
}
