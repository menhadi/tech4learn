import { useEffect, useState } from "react";
import { api } from "./api";
import type { Exam } from "./ExamBuilder";
import { ExamLearnerPicker, type ExamLearner } from "./ExamLearnerPicker";

type Question = { id: number; question: string };
type Scan = { id: string; learner_id: string; content_type: string; answers: Record<string, string>; status: "uploaded" | "reviewed"; revision: number; created_at: string };

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
  const [learner, setLearner] = useState<ExamLearner | null>(null);
  const [scanFile, setScanFile] = useState<File | null>(null);
  const [scans, setScans] = useState<Scan[]>([]);
  const [review, setReview] = useState<Scan | null>(null);
  const [answers, setAnswers] = useState<Record<string, string>>({});
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
  async function loadScans() {
    try { setScans(await api<Scan[]>(`${base}/exams/${record.id}/omr-scans`)); }
    catch (error) { setMessage(error instanceof Error ? error.message : "Unable to load scans."); }
  }
  useEffect(() => { if (!central) void loadScans(); }, [record.id, central]);
  async function uploadScan() {
    if (!learner || !scanFile) { setMessage("Choose the student and completed scan."); return; }
    if (scanFile.size > 10 * 1024 * 1024 || !["image/jpeg", "image/png", "application/pdf"].includes(scanFile.type)) { setMessage("Use a JPEG, PNG or PDF scan up to 10 MB."); return; }
    setBusy(true); setMessage("");
    try {
      const file = await new Promise<string>((resolve, reject) => { const reader = new FileReader(); reader.onerror = () => reject(new Error("Unable to read scan.")); reader.onload = () => resolve(String(reader.result)); reader.readAsDataURL(scanFile); });
      await api(`${base}/exams/${record.id}/omr-scans`, "POST", { learner_id: learner.id, content_type: scanFile.type, file });
      setScanFile(null); setMessage("Scan saved for manual review."); await loadScans();
    } catch (error) { setMessage(error instanceof Error ? error.message : "Unable to save scan."); }
    finally { setBusy(false); }
  }
  async function saveReview() {
    if (!review) return; setBusy(true); setMessage("");
    try { await api(`${base}/exams/omr-scans/${review.id}/review`, "POST", { revision: review.revision, answers }); setReview(null); setAnswers({}); setMessage("Reviewed answers saved. Result import is not enabled yet."); await loadScans(); }
    catch (error) { setMessage(error instanceof Error ? error.message : "Unable to save answers."); }
    finally { setBusy(false); }
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
      {!central && <section className="omr-review">
        <h4>Completed-sheet review</h4>
        <p>Upload the completed sheet, then enter the marked answers manually. This keeps a review record; it does not yet create an exam result.</p>
        <ExamLearnerPicker org={org} title="Student for completed OMR sheet" action="Choose student" onSelect={setLearner} />
        {learner && <p>Selected student: <strong>{learner.name} · {learner.code}</strong></p>}
        <label>Completed scan<input type="file" accept="image/jpeg,image/png,application/pdf" disabled={busy || disabled} onChange={(event) => setScanFile(event.target.files?.[0] ?? null)} /></label>
        <button type="button" disabled={busy || disabled || !learner || !scanFile} onClick={() => void uploadScan()}>{busy ? "Saving…" : "Upload completed sheet"}</button>
        <button type="button" className="secondary" disabled={busy} onClick={() => void loadScans()}>Refresh scans</button>
        {!!scans.length && <ul className="omr-scan-list">{scans.map((scan) => <li key={scan.id}><span>{scan.status === "reviewed" ? "Reviewed" : "Needs review"} · {new Date(scan.created_at).toLocaleString()}</span><button type="button" className="secondary" disabled={busy} onClick={() => { setReview(scan); setAnswers(scan.answers || {}); }}>Review answers</button></li>)}</ul>}
        {review && <div className="omr-answer-review"><h5>Review completed sheet</h5><p>Enter only the choices marked on the paper. Question numbers use the printed sheet.</p><div className="omr-answer-grid">{Array.from({ length: Math.min(questions?.length ?? 200, 200) }, (_, index) => <label key={index}>{index + 1}<select value={answers[String(index + 1)] || ""} onChange={(event) => setAnswers((old) => ({ ...old, [String(index + 1)]: event.target.value }))}><option value="">—</option>{["A", "B", "C", "D", "E", "F"].slice(0, options).map((choice) => <option key={choice} value={choice}>{choice}</option>)}</select></label>)}</div><button type="button" disabled={busy} onClick={() => void saveReview()}>Save manual answers</button><button type="button" className="secondary" disabled={busy} onClick={() => setReview(null)}>Cancel</button></div>}
      </section>}
    </section>
  );
}
