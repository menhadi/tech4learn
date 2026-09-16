import { useEffect, useRef, useState } from "react";
import { ExamRichContent } from "./ExamRichContent";

const examples: Record<string, string> = {
  Fraction: String.raw`\frac{a}{b}`,
  Power: String.raw`x^{2}`,
  Root: String.raw`\sqrt{x}`,
  Sum: String.raw`\sum_{i=1}^{n} i`,
  Integral: String.raw`\int_{a}^{b} f(x)\,dx`,
  Matrix: String.raw`\begin{pmatrix} a & b \\ c & d \end{pmatrix}`,
  Chemistry: String.raw`\ce{H2O}`,
};

/** Uses the existing bounded student renderer; stores TeX, not rendered markup. */
export function ExamFormulaInsert({
  disabled,
  onInsert,
}: {
  disabled: boolean;
  onInsert: (html: string) => void;
}) {
  const [source, setSource] = useState("");
  const [display, setDisplay] = useState(false);
  const [checked, setChecked] = useState<{
    key: string;
    math: string;
    insertion: string;
  } | null>(null);
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const generation = useRef(0);
  const disabledNow = useRef(disabled);
  disabledNow.current = disabled;
  useEffect(
    () => () => {
      generation.current++;
    },
    [],
  );
  const key = JSON.stringify([source, display]);
  function invalidate() {
    generation.current++;
    setChecked(null);
    setError("");
    setBusy(false);
  }
  async function check() {
    const token = ++generation.current;
    setBusy(true);
    setError("");
    setChecked(null);
    try {
      if (
        !source.trim() ||
        source.length > 10000 ||
        /\\[()[\]]|(?<!\\)\$/.test(source)
      )
        throw new Error(
          "Enter the formula without surrounding math delimiters.",
        );
      const { texToMathML } = await import("./exam-math");
      const math = texToMathML(source, display);
      if (token !== generation.current || disabledNow.current) return;
      const text = document.createElement("span");
      text.textContent = display ? `\\[${source}\\]` : `\\(${source}\\)`;
      setChecked({
        key,
        math,
        insertion: display ? `<div>${text.innerHTML}</div>` : text.innerHTML,
      });
    } catch {
      if (token === generation.current)
        setError(
          "Check the TeX commands and brackets. Enter only the formula, without surrounding math delimiters.",
        );
    } finally {
      if (token === generation.current) setBusy(false);
    }
  }
  return (
    <details data-no-draft="true">
      <summary>Insert a formula</summary>
      <p>
        Choose a starting formula and edit its TeX. Check it, then insert it at
        the end of this field. Inserted formulas become part of the field’s
        draft.
      </p>
      <div aria-label="Formula examples">
        {Object.entries(examples).map(([label, example]) => (
          <button
            key={label}
            type="button"
            className="secondary"
            disabled={disabled || busy}
            onClick={() => {
              invalidate();
              setSource(example);
            }}
          >
            {label}
          </button>
        ))}
      </div>
      <label>
        Formula TeX
        <textarea
          value={source}
          maxLength={10000}
          disabled={disabled || busy}
          onChange={(e) => {
            invalidate();
            setSource(e.target.value);
          }}
        />
      </label>
      <label>
        <input
          type="checkbox"
          checked={display}
          disabled={disabled || busy}
          onChange={(e) => {
            invalidate();
            setDisplay(e.target.checked);
          }}
        />{" "}
        Display on a separate line
      </label>
      <button
        type="button"
        disabled={disabled || busy || !source.trim()}
        onClick={() => void check()}
      >
        {busy ? "Checking formula…" : "Check formula"}
      </button>{" "}
      <button
        type="button"
        disabled={disabled || busy || checked?.key !== key}
        onClick={() => {
          if (disabledNow.current || checked?.key !== key) return;
          onInsert(checked.insertion);
          invalidate();
          setSource("");
        }}
      >
        Insert formula at end
      </button>
      {error && (
        <p role="alert" className="error">
          {error}
        </p>
      )}
      {checked?.key === key && (
        <section aria-label="Formula preview">
          <ExamRichContent value={checked.math} />
        </section>
      )}
    </details>
  );
}
