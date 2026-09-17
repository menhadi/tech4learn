import { useState } from "react";
import { parseRetainedExamContent } from "./ExamExistingFormulaEditor";

function sections(value: string, retainedImages: boolean) {
  const parsed = parseRetainedExamContent(value, retainedImages);
  if (!parsed) return null;
  const walker = document.createTreeWalker(
    parsed.root.content,
    NodeFilter.SHOW_TEXT,
  );
  const nodes: Text[] = [];
  while (walker.nextNode()) {
    const node = walker.currentNode as Text;
    if (node.textContent?.trim() && !node.parentElement?.closest("math"))
      nodes.push(node);
  }
  return { root: parsed.root, nodes };
}

export function canEditExistingText(value: string, retainedImages = false) {
  return sections(value, retainedImages) !== null;
}

/** Edit text or append a plain paragraph without rebuilding images or formulas. */
export function ExamExistingTextEditor({
  value,
  retainedImages,
  disabled,
  onChange,
}: {
  value: string;
  retainedImages: boolean;
  disabled: boolean;
  onChange: (value: string) => void;
}) {
  const [selected, setSelected] = useState(0);
  const [draft, setDraft] = useState<string | null>(null);
  const parsed = sections(value, retainedImages);
  if (!parsed) return null;
  const index = Math.min(selected, parsed.nodes.length);
  const append = index === parsed.nodes.length;
  const original = append ? "" : (parsed.nodes[index].textContent ?? "");
  const replacement = draft ?? original;
  const newParagraph = document.createElement("p");
  if (append) {
    newParagraph.textContent = replacement;
    parsed.root.content.append(newParagraph);
  } else parsed.nodes[index].textContent = replacement;
  const updated = parsed.root.innerHTML;
  if (append) newParagraph.remove();
  else parsed.nodes[index].textContent = original;
  const tooLong = updated.length > 200000;
  const tooComplex =
    append && parsed.root.content.querySelectorAll("*").length >= 2000;
  return (
    <details data-no-draft="true">
      <summary>Edit surrounding text</summary>
      <p>
        Edit a text section or add a paragraph at the end. Images and formatting
        stay in place. Apply the text, then save the record.
      </p>
      <label>
        Text section
        <select
          value={index}
          disabled={disabled}
          onChange={(event) => {
            setSelected(Number(event.target.value));
            setDraft(null);
          }}
        >
          {parsed.nodes.map((node, i) => (
            <option key={i} value={i}>
              {i + 1}: {(node.textContent ?? "").trim().slice(0, 100)}
            </option>
          ))}
          <option value={parsed.nodes.length}>New paragraph at the end</option>
        </select>
      </label>
      <label>
        {append ? "New paragraph text" : "Replacement text"}
        <textarea
          value={replacement}
          maxLength={200000}
          disabled={disabled}
          onChange={(event) => setDraft(event.target.value)}
        />
      </label>
      {tooLong && (
        <p role="alert">
          This change is too long. Shorten the replacement text.
        </p>
      )}
      {tooComplex && (
        <p role="alert">
          This field has too much formatting to add another paragraph.
        </p>
      )}
      <button
        type="button"
        disabled={disabled || replacement === original || tooLong || tooComplex}
        onClick={() => {
          if (disabled || tooLong || tooComplex) return;
          onChange(updated);
          setDraft(null);
        }}
      >
        Apply text change
      </button>
    </details>
  );
}
