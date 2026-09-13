import { useState } from "react";
import { examCalculator } from "./exam-calculator";
export function ExamCalculator() {
  const [open, setOpen] = useState(false),
    [value, setValue] = useState(""),
    [error, setError] = useState("");
  const keys = [
    "sin",
    "cos",
    "tan",
    "C",
    "log",
    "ln",
    "sqrt",
    "/",
    "7",
    "8",
    "9",
    "*",
    "4",
    "5",
    "6",
    "-",
    "1",
    "2",
    "3",
    "+",
    "(",
    "0",
    ")",
    "=",
  ];
  const labels: Record<string, string> = { sqrt: "√", "/": "÷", "*": "×" };
  function press(key: string) {
    setError("");
    if (key === "C") {
      setValue("");
      return;
    }
    if (key === "=") {
      try {
        setValue(String(examCalculator(value)));
      } catch {
        setError("Invalid expression. Check the calculation or clear it.");
      }
      return;
    }
    const next =
      value +
      (["sin", "cos", "tan", "log", "ln", "sqrt"].includes(key)
        ? key + "("
        : key);
    if (next.length <= 256) setValue(next);
    else setError("Expression is too long.");
  }
  return (
    <aside aria-label="Exam calculator">
      <button
        className="secondary"
        onClick={() => setOpen(!open)}
        aria-expanded={open}
      >
        {open ? "Close calculator" : "Calculator"}
      </button>
      {open && (
        <div className="panel" style={{ maxWidth: 320 }}>
          <h3>Scientific calculator</h3>
          <p>Angles use radians.</p>
          <label>
            Calculation
            <input readOnly value={value} />
          </label>
          {error && (
            <p role="alert" className="error">
              {error}
            </p>
          )}
          <div
            style={{
              display: "grid",
              gridTemplateColumns: "repeat(4,1fr)",
              gap: 6,
            }}
          >
            {keys.map((key) => (
              <button
                className="secondary"
                key={key}
                onClick={() => press(key)}
                aria-label={
                  key === "C"
                    ? "Clear calculation"
                    : key === "="
                      ? "Calculate"
                      : key
                }
              >
                {labels[key] ?? key}
              </button>
            ))}
          </div>
        </div>
      )}
    </aside>
  );
}
