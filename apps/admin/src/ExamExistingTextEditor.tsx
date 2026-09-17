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
  return nodes.length ? { root: parsed.root, nodes } : null;
}

export function canEditExistingText(value: string, retainedImages = false) {
  return sections(value, retainedImages) !== null;
}

/** Change text nodes only; never reconstruct images or formula internals. */
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
  const index = Math.min(selected, parsed.nodes.length - 1);
  const original = parsed.nodes[index].textContent ?? "";
  const replacement = draft ?? original;
  parsed.nodes[index].textContent = replacement;
  const updated = parsed.root.innerHTML;
  parsed.nodes[index].textContent = original;
  const tooLong = updated.length > 200000;
  return (
    <details data-no-draft="true">
      <summary>Edit surrounding text</summary>
      <p>
        Select a text section and edit its wording. Images and formatting stay
        in place. Apply the text, then save the record.
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
        </select>
      </label>
      <label>
        Replacement text
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
      <button
        type="button"
        disabled={disabled || replacement === original || tooLong}
        onClick={() => {
          if (disabled || tooLong) return;
          onChange(updated);
          setDraft(null);
        }}
      >
        Apply text change
      </button>
    </details>
  );
}
