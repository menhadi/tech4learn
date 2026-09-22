import { useEffect, useState } from "react";
import { api, apiBase, ApiError } from "./api";
import { DraftForm } from "./DraftForm";
import { ExamRichContent } from "./ExamRichContent";
import { DirectoryTable } from "./DirectoryTable";
import { ExamLearnerPicker, type ExamLearner } from "./ExamLearnerPicker";

type Summary = {
  attempt_id: number;
  result: string;
  score_percent: number;
  obtained_marks: number;
  total_marks: number;
};
type Attempt = Summary & {
  exam_id: number;
  exam_name: string;
  finished_at: string;
  pending_count: number;
};
type Review = {
  revision: string;
  summary: Summary;
  questions: {
    stat_id: number;
    question_id: number;
    question_html: string;
    answer_html: string;
    reference_html: string;
    review_supported: boolean;
    passage: { name: string; html: string } | null;
    maximum_marks: number;
    attachment_asset?: string | null;
    exam_id?: number | null;
  }[];
};
function printCertificate(student: string, attempt: Attempt) {
  const escape = (value: string | number) => String(value).replace(/[&<>"']/g, (char) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[char] || char);
  const popup = window.open("", "_blank", "noopener,noreferrer");
  if (!popup) return;
  popup.document.write(`<!doctype html><title>Exam result certificate</title><style>body{font-family:Arial,sans-serif;background:#f4f7f5;margin:0;padding:40px}.certificate{max-width:800px;margin:auto;padding:64px;background:#fff;border:12px solid #175d50;text-align:center}.mark{color:#175d50;font-size:20px;letter-spacing:3px;text-transform:uppercase}.name{font:700 40px Georgia,serif;margin:30px 0}.exam{font-size:24px;font-weight:700}.score{font-size:20px;margin-top:30px}@media print{body{padding:0;background:#fff}.certificate{border-width:8px}}</style><main class="certificate"><p class="mark">Tech4Learn examination record</p><h1>Certificate of completion</h1><p>This confirms that</p><p class="name">${escape(student)}</p><p>completed the examination</p><p class="exam">${escape(attempt.exam_name)}</p><p class="score">Result: <strong>${escape(attempt.result)}</strong> · ${escape(attempt.obtained_marks)} / ${escape(attempt.total_marks)} marks (${escape(attempt.score_percent)}%)</p><p>Completed: ${escape(new Date(attempt.finished_at).toLocaleString())}</p></main><script>window.print()</script>`);
  popup.document.close();
}

export function ExamResults({ org }: { org: string }) {
  const [learner, setLearner] = useState<ExamLearner | null>(null);
  const [busy, setBusy] = useState(false);
  return (
    <section className="panel">
      <h3>Results and marking</h3>
      <p>
        Review submitted exams and mark pending written answers. The exam service
        calculates the result using the paper’s settings.
      </p>
      {learner ? (
        <>
          <p>
            Student: <strong>{learner.name}</strong> · {learner.code}
          </p>
          <button
            className="secondary"
            disabled={busy}
            onClick={() => setLearner(null)}
          >
            Choose another student
          </button>
          <StudentResults
            key={`${org}:${learner.id}`}
            org={org}
            learner={learner.id}
            learnerName={learner.name}
            onBusy={setBusy}
          />
        </>
      ) : (
        <ExamLearnerPicker
          org={org}
          onSelect={setLearner}
          title="Choose a student for results"
          action="View results"
        />
      )}
    </section>
  );
}
function StudentResults({
  org,
  learner,
  learnerName,
  onBusy,
}: {
  org: string;
  learner: string;
  learnerName: string;
  onBusy: (value: boolean) => void;
}) {
  const base = `/organisations/${org}/exam-results/${learner}/attempts`;
  const [rows, setRows] = useState<Attempt[]>([]);
  const [next, setNext] = useState<number | null>(null);
  const [page, setPage] = useState({ after: 0, revision: 0 });
  const [attempt, setAttempt] = useState<Attempt | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  useEffect(() => {
    onBusy(busy);
    return () => onBusy(false);
  }, [busy, onBusy]);
  useEffect(() => {
    let active = true;
    setLoading(true);
    setError("");
    void api<{ items: Attempt[]; next: number | null }>(
      `${base}?after=${page.after}`,
    )
      .then((result) => {
        if (active) {
          setRows((old) =>
            page.after ? [...old, ...result.items] : result.items,
          );
          setNext(result.next);
        }
      })
      .catch((cause) => {
        if (active) {
          setRows([]);
          setNext(null);
          setAttempt(null);
          setError(cause.message);
        }
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
    };
  }, [base, page]);
  const refresh = () => {
    setAttempt(null);
    setPage((old) => ({ after: 0, revision: old.revision + 1 }));
  };
  return (
    <>
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      <button
        className="secondary"
        disabled={loading || busy}
        onClick={refresh}
      >
        Refresh results
      </button>
      {loading && <p role="status">Loading results…</p>}
      {!loading && !error && !rows.length && (
        <p>No submitted exams were found for this student.</p>
      )}
      {!!rows.length && (
        <DirectoryTable
          title="Submitted exams — loaded records"
          columns={[
            "Exam",
            "Score (%)",
            "Result",
            "Pending answers",
            "Finished",
            "Actions",
          ]}
        >
          {rows.map((row) => (
            <tr key={row.attempt_id}>
              <td>{row.exam_name}</td>
              <td>{row.score_percent}</td>
              <td>{row.result}</td>
              <td>{row.pending_count}</td>
              <td>{new Date(row.finished_at).toLocaleString()}</td>
              <td>
                <button disabled={busy} onClick={() => setAttempt(row)}>
                  Review result
                </button>
                <button className="secondary" disabled={busy} onClick={() => printCertificate(learnerName, row)}>
                  Print certificate
                </button>
              </td>
            </tr>
          ))}
        </DirectoryTable>
      )}
      {next !== null && (
        <button
          className="secondary"
          disabled={loading || busy}
          onClick={() => setPage((old) => ({ ...old, after: next }))}
        >
          Load more results
        </button>
      )}
      {attempt && (
        <Marking
          key={attempt.attempt_id}
          base={`${base}/${attempt.attempt_id}`}
          learner={learner}
          name={attempt.exam_name}
          onBusy={setBusy}
          onSaved={refresh}
        />
      )}
    </>
  );
}
function Marking({
  base,
  learner,
  name,
  onBusy,
  onSaved,
}: {
  base: string;
  learner: string;
  name: string;
  onBusy: (value: boolean) => void;
  onSaved: () => void;
}) {
  const [review, setReview] = useState<Review | null>(null);
  const [reload, setReload] = useState(0);
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const [stale, setStale] = useState(false);
  const [pending, setPending] = useState<{
    request_id: string;
    revision: string;
    marks: Record<string, number>;
  } | null>(null);
  const [ready, setReady] = useState<Record<string, boolean>>({});
  const contentReady =
    !!review &&
    review.questions.every(
      (q) =>
        (!q.passage || ready[`${q.stat_id}:passage`]) &&
        ready[`${q.stat_id}:question`] &&
        ready[`${q.stat_id}:answer`] &&
        (!q.reference_html || ready[`${q.stat_id}:reference`]),
    );
  const track = (key: string, value: boolean) =>
    setReady((old) => (old[key] === value ? old : { ...old, [key]: value }));
  useEffect(() => {
    onBusy(busy || pending !== null);
    return () => onBusy(false);
  }, [busy, pending, onBusy]);
  useEffect(() => {
    let active = true;
    setReview(null);
    setReady({});
    setError("");
    setStale(false);
    void api<Review>(base)
      .then((result) => {
        if (active) setReview(result);
      })
      .catch((cause) => {
        if (active) setError(cause.message);
      });
    return () => {
      active = false;
    };
  }, [base, reload]);
  return (
    <section className="panel">
      <h4>{name}</h4>
      {error && (
        <p role="alert" className="error">
          {error}
        </p>
      )}
      {!review && !error && <p role="status">Loading submitted answers…</p>}
      <button
        className="secondary"
        disabled={busy}
        onClick={() => {
          setPending(null);
          setReload((value) => value + 1);
        }}
      >
        Reload result
      </button>
      {review && (
        <>
          {review.questions.some((q) => !q.review_supported) && (
            <p role="alert">
              This attempt contains media. Marking is unavailable until the
              complete review display is integrated.
            </p>
          )}
          <p>
            Result: {review.summary.result} · {review.summary.obtained_marks} /{" "}
            {review.summary.total_marks} marks · {review.summary.score_percent}%
          </p>
          {!review.questions.length ? (
            <p>No answers are awaiting manual marking.</p>
          ) : (
            <>
              <p>Enter marks for every pending answer before saving.</p>
              {!contentReady && (
                <p role="status">
                  Waiting for all answer content to display before marking.
                </p>
              )}
              {pending && (
                <p role="status">
                  Save was not confirmed. Retry the same marks, or reload to
                  check the current result.
                </p>
              )}
              <DraftForm
                draftKey={`exam-marking:${learner}:${review.summary.attempt_id}:${review.revision}`}
                title="Pending answer marks"
                onSubmit={async (event) => {
                  event.preventDefault();
                  if (
                    !contentReady ||
                    busy ||
                    stale ||
                    review.questions.some((q) => !q.review_supported)
                  )
                    return;
                  const form = new FormData(event.currentTarget);
                  const request = pending ?? {
                    request_id: crypto.randomUUID(),
                    revision: review.revision,
                    marks: Object.fromEntries(
                      review.questions.map((q) => [
                        q.stat_id,
                        Number(form.get(`mark-${q.stat_id}`)),
                      ]),
                    ),
                  };
                  setPending(request);
                  setBusy(true);
                  setError("");
                  try {
                    await api(base, "POST", request);
                    setPending(null);
                    onSaved();
                  } catch (cause) {
                    setError(
                      cause instanceof Error
                        ? cause.message
                        : "Marking was not confirmed.",
                    );
                    if (
                      cause instanceof ApiError &&
                      [400, 401, 403, 404, 409].includes(cause.status)
                    ) {
                      setPending(null);
                      setStale(cause.status !== 400);
                    }
                  } finally {
                    setBusy(false);
                  }
                }}
              >
                <fieldset
                  disabled={
                    !contentReady ||
                    busy ||
                    pending !== null ||
                    stale ||
                    review.questions.some((q) => !q.review_supported)
                  }
                >
                  <legend>Pending answers</legend>
                  {review.questions.map((q, index) => (
                    <div className="panel" key={q.stat_id}>
                      <h5>Question {index + 1}</h5>
                      {q.passage && (
                        <section>
                          <h5>{q.passage.name || "Passage"}</h5>
                          <ExamRichContent
                            value={q.passage.html}
                            mediaBase={`${apiBase}${base}/media/${q.stat_id}`}
                            onReady={(value) =>
                              track(`${q.stat_id}:passage`, value)
                            }
                          />
                        </section>
                      )}
                      <ExamRichContent
                        value={q.question_html}
                        mediaBase={`${apiBase}${base}/media/${q.stat_id}`}
                        onReady={(value) =>
                          track(`${q.stat_id}:question`, value)
                        }
                      />
                      <p>
                        <strong>Student answer</strong>
                      </p>
                      <ExamRichContent
                        value={q.answer_html || "No written answer"}
                        onReady={(value) => track(`${q.stat_id}:answer`, value)}
                      />
                      {q.attachment_asset && q.exam_id && <p>
                        <a href={`${apiBase}${base.replace(/\/attempts\/[0-9]+$/, "")}/exams/${q.exam_id}/attempts/${review.summary.attempt_id}/attachments/${q.question_id}/${q.attachment_asset}`} download>Download student answer file</a>
                      </p>}
                      {q.reference_html && (
                        <>
                          <p>
                            <strong>Reference answer</strong>
                          </p>
                          <ExamRichContent
                            value={q.reference_html}
                            mediaBase={`${apiBase}${base}/media/${q.stat_id}`}
                            onReady={(value) =>
                              track(`${q.stat_id}:reference`, value)
                            }
                          />
                        </>
                      )}
                      <label>
                        Marks (maximum {q.maximum_marks})
                        <input
                          name={`mark-${q.stat_id}`}
                          type="number"
                          min={0}
                          max={q.maximum_marks}
                          step="any"
                          required
                        />
                      </label>
                    </div>
                  ))}
                </fieldset>
                <button
                  type="submit"
                  disabled={
                    !contentReady ||
                    busy ||
                    stale ||
                    review.questions.some((q) => !q.review_supported)
                  }
                >
                  {busy
                    ? "Saving…"
                    : pending
                      ? "Retry saving marks"
                      : "Save all marks"}
                </button>
              </DraftForm>
            </>
          )}
        </>
      )}
    </section>
  );
}
