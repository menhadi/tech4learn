import { useState } from "react";
import { api } from "./api";
import type { Exam } from "./ExamBuilder";

type Question = { id: number; question: string };

export function ExamOmrSheet({
  org,
  central = false,
  record,
  disabled,
}: {
  org: string;
  central?: boolean;
  record: Exam;
  disabled: boolean;
}) {
  const [questions, setQuestions] = useState<Question[] | null>(null);
  const [options, setOptions] = useState(4);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  const base = central
    ? `/platform/exam-content/${org}/central`
    : `/organisations/${org}/exam-content`;
  async function load() {
    if (busy || disabled) return;
    setBusy(true);
    setMessage("");
    try {
      const collected: Question[] = [];
      let after = 0;
      do {
        const page = await api<{ items: Question[]; next: number | null }>(
          `${base}/exams/${record.id}/questions?after=${after}`,
        );
        if (!Array.isArray(page.items) || page.items.length > 100)
          throw new Error("The exam questions could not be loaded.");
        collected.push(...page.items);
        after = page.next ?? 0;
      } while (after && collected.length <= 200);
      if (!collected.length || collected.length > 200)
        throw new Error("Use an exam with 1–200 questions for this answer sheet.");
      setQuestions(collected);
    } catch (error) {
      setMessage(
        error instanceof Error ? error.message : "Unable to load exam questions.",
      );
    } finally {
      setBusy(false);
    }
  }
  return (
    <section className="panel">
      <h3>Printable OMR answer sheet</h3>
      <p>
        Create a blank answer sheet for this saved exam. Students fill bubbles
        on paper; staff retain the completed sheet for review. Automated scan
        reading and result import are not enabled yet.
      </p>
      <label>
        Answer choices per question
        <select
          value={options}
          disabled={busy || disabled || Boolean(questions)}
          onChange={(event) => setOptions(Number(event.target.value))}
        >
          {[4, 5, 6].map((count) => (
            <option key={count} value={count}>
              {count} choices ({String.fromCharCode(65 + count - 1)} maximum)
            </option>
          ))}
        </select>
      </label>
      {!questions && (
        <button type="button" disabled={busy || disabled} onClick={() => void load()}>
          {busy ? "Preparing sheet…" : "Prepare answer sheet"}
        </button>
      )}
      {message && <p className="error" role="alert">{message}</p>}
      {questions && (
        <div className="omr-sheet" aria-label="Printable OMR answer sheet">
          <div className="omr-sheet-header">
            <h4>{String(record.fields.name ?? "Exam")}</h4>
            <p>Student name: ____________________ &nbsp; Code: ____________________</p>
            <p>Date: ____________________ &nbsp; Signature: ____________________</p>
            <p>Fill one bubble per question using dark ink. Do not fold this sheet.</p>
          </div>
          <ol className="omr-bubbles">
            {questions.map((question, index) => (
              <li key={question.id}>
                <span className="omr-number">{index + 1}</span>
                {Array.from({ length: options }, (_, option) => (
                  <span className="omr-bubble" key={option}>
                    {String.fromCharCode(65 + option)}
                  </span>
                ))}
              </li>
            ))}
          </ol>
          <button type="button" onClick={() => window.print()}>
            Print answer sheet
          </button>
          <button type="button" className="secondary" onClick={() => setQuestions(null)}>
            Change sheet options
          </button>
        </div>
      )}
    </section>
  );
}
