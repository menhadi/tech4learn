import { useEffect, useRef, useState } from "react";
import { ExamRichContent } from "./ExamRichContent";
import { ExamCamera } from "./ExamCamera";
import { ExamCalculator } from "./ExamCalculator";
import { api, apiBase } from "./api";

type Question = {
  id: number;
  number: number;
  type: string;
  content: Record<string, string>;
  option_order?: number[];
  passage: { name: string; content: string } | null;
  blank_count: number;
  answer: any;
  review: boolean;
  answer_locked: boolean;
  revision: string;
  attachment_asset?: string | null;
  attachments_enabled?: boolean;
};
type Attempt = {
  attempt_id: number;
  exam_id: number;
  name: string;
  remaining_seconds: number;
  time_limited: boolean;
  questions: Question[];
  settings: {
    allow_answer_change: boolean;
    calculator_allowed?: boolean;
    proctor?: boolean;
    browser_tolerance?: boolean;
    tolerance_count?: number;
  };
  section_clock?: {
    mode: string;
    active: {
      key: string;
      label: string;
      questions: number[];
      remaining_seconds: number;
    } | null;
    remaining_seconds: number;
  } | null;
  tolerance_count?: number;
  completed?: boolean;
  result?: { status: string; score_percent: number } | null;
};
export function StudentExamAttempt({ base }: { base: string }) {
  const [history, setHistory] = useState<
    | {
        attempt_id: number;
        finished_at: string;
        result: { status: string; score_percent: number } | null;
      }[]
    | null
  >(null);
  const [prepared, setPrepared] = useState<{
    exam_id: number;
    proctor: boolean;
  } | null>(null);
  const [attempt, setAttempt] = useState<Attempt | null>(null),
    [index, setIndex] = useState(0),
    [answer, setAnswer] = useState<any>(""),
    [review, setReview] = useState(false),
    [dirty, setDirty] = useState(false),
    [busy, setBusy] = useState(false),
    [error, setError] = useState(""),
    [displayError, setDisplayError] = useState(""),
    [displayGeneration, setDisplayGeneration] = useState(0),
    [displayReady, setDisplayReady] = useState<Record<string, boolean>>({}),
    [remaining, setRemaining] = useState<number | null>(null),
    [sectionRemaining, setSectionRemaining] = useState<number | null>(null),
    [notice, setNotice] = useState(""),
    [visibilityQueued, setVisibilityQueued] = useState(0),
    [cameraReady, setCameraReady] = useState(false),
    [confirm, setConfirm] = useState(false);
  const pending = useRef<{ action: string; body: any } | null>(null),
    running = useRef(false),
    deadline = useRef(0),
    sectionDeadline = useRef(0);
  const q = attempt?.questions?.[index];
  function select(next: Attempt, i: number, refresh = true) {
    setIndex(i);
    if (refresh) {
      setDisplayError("");
      setDisplayReady({});
      setDisplayGeneration((generation) => generation + 1);
    }
    const question = next.questions?.[i];
    setAnswer(
      question?.type === "fill_blank"
        ? Array.from({ length: question.blank_count }, (_, n) =>
            String(question.answer?.[n] ?? ""),
          )
        : (question?.answer ??
            (question?.type.startsWith("multiple_choice") ? [] : "")),
    );
    setReview(question?.review ?? false);
    setDirty(false);
  }
  async function send(action: string, body: any) {
    if (running.current) return;
    running.current = true;
    if (action === "history") setHistory(null);
    setBusy(true);
    setError("");
    pending.current ??= {
      action,
      body: {
        ...body,
        ...(action === "start" ? { camera_ready: cameraReady } : {}),
        ...(action === "extract" ? {} : { request_id: crypto.randomUUID() }),
      },
    };
    try {
      const request = pending.current;
      const result = await api<any>(
        request.action === "attachment" ? `${base}/attachments` : request.action === "extract" ? `${base}/attachments/extract` : `${base}/attempt/${request.action}`,
        "POST",
        request.body,
      );
      if (request.action === "extract") {
        setAnswer(result.text);
        setDirty(true);
        setNotice("Review the extracted text, then save your answer. The text has not been saved yet.");
      } else if (request.action === "attachment") {
        const updated = { ...attempt!, questions: attempt!.questions.map(question => question.id === result.question_id
          ? { ...question, revision: result.revision, attachment_asset: result.asset } : question) };
        setAttempt(updated);
        setNotice("File saved. Extract its text or enter your written answer, then save the answer.");
      } else if (request.action === "prepare") {
        setPrepared(result);
      } else if (request.action === "history") {
        setHistory(result.items);
      } else if (request.action === "visibility" && !result.completed) {
        setAttempt((previous) =>
          previous
            ? { ...previous, tolerance_count: result.tolerance_count }
            : previous,
        );
        setNotice(
          `Tab leaves recorded: ${result.tolerance_count} of ${result.tolerance_limit}. Stay on this exam tab.`,
        );
      } else if (request.action === "answer") {
        const updated = {
          ...attempt!,
          questions: attempt!.questions.map((question) =>
            question.id === result.question_id
              ? {
                  ...question,
                  answer: request.body.fields.option_selected,
                  review: request.body.fields.review,
                  revision: result.revision,
                  answer_locked: result.answer_locked,
                }
              : question,
          ),
        };
        setAttempt(updated);
        select(updated, index, false);
      } else {
        if (request.action === "visibility")
          setNotice(
            "The exam was submitted at its configured tab-leave limit.",
          );
        setAttempt(result);
        const active = result.section_clock?.active;
        select(
          result,
          active
            ? Math.max(
                0,
                result.questions.findIndex((question: Question) =>
                  active.questions.includes(question.id),
                ),
              )
            : 0,
        );
        deadline.current = Date.now() + result.remaining_seconds * 1000;
        setRemaining(result.time_limited ? result.remaining_seconds : null);
        sectionDeadline.current = active
          ? Date.now() + active.remaining_seconds * 1000
          : 0;
        setSectionRemaining(
          result.section_clock ? (active?.remaining_seconds ?? 0) : null,
        );
      }
      pending.current = null;
      setConfirm(false);
    } catch (e) {
      setError(
        e instanceof Error
          ? e.message
          : "The exam request failed. Retry to check whether it was saved.",
      );
    } finally {
      running.current = false;
      setBusy(false);
    }
  }
  useEffect(() => {
    if (!attempt || attempt.completed || !attempt.time_limited) return;
    const timer = setInterval(() => {
      setRemaining(
        Math.max(0, Math.ceil((deadline.current - Date.now()) / 1000)),
      );
      if (sectionDeadline.current)
        setSectionRemaining(
          Math.max(0, Math.ceil((sectionDeadline.current - Date.now()) / 1000)),
        );
    }, 1000);
    return () => clearInterval(timer);
  }, [attempt?.attempt_id, attempt?.completed]);
  useEffect(() => {
    if (
      sectionRemaining !== 0 ||
      !attempt?.section_clock ||
      attempt.completed ||
      busy ||
      pending.current ||
      running.current
    )
      return;
    setNotice(
      dirty
        ? "Section time ended. Only saved answers were kept."
        : "Section time ended. Saved answers were kept.",
    );
    void send("start", {});
  }, [sectionRemaining, busy]);
  useEffect(() => {
    const warn = (e: BeforeUnloadEvent) => {
      if (dirty || busy || pending.current || visibilityQueued) {
        e.preventDefault();
        e.returnValue = "";
      }
    };
    window.addEventListener("beforeunload", warn);
    return () => window.removeEventListener("beforeunload", warn);
  }, [dirty, busy, visibilityQueued]);
  useEffect(() => {
    if (
      !attempt ||
      attempt.completed ||
      !attempt.settings.browser_tolerance ||
      !attempt.settings.tolerance_count
    ) {
      setVisibilityQueued(0);
      return;
    }
    let wasHidden = false;
    const changed = () => {
      if (document.hidden && !wasHidden)
        setVisibilityQueued((count) => count + 1);
      wasHidden = document.hidden;
    };
    document.addEventListener("visibilitychange", changed);
    changed();
    return () => document.removeEventListener("visibilitychange", changed);
  }, [
    attempt?.attempt_id,
    attempt?.completed,
    attempt?.settings?.browser_tolerance,
    attempt?.settings?.tolerance_count,
  ]);
  useEffect(() => {
    if (
      !visibilityQueued ||
      !attempt ||
      attempt.completed ||
      busy ||
      pending.current ||
      running.current
    )
      return;
    setVisibilityQueued((count) => count - 1);
    void send("visibility", {
      attempt_id: attempt.attempt_id,
      event: "hidden",
    });
  }, [visibilityQueued, busy, attempt?.completed]);
  const mediaBase =
    attempt && q
      ? `${apiBase}${base}/media/${attempt.attempt_id}/${q.id}`
      : undefined;
  const required = q
    ? [
        "question",
        ...(q.passage ? ["passage"] : []),
        ...(q.type.startsWith("multiple_choice")
          ? (q.option_order ?? [1, 2, 3, 4, 5, 6])
              .filter((n) => q.content[`option${n}`])
              .map((n) => `option${n}`)
          : []),
      ]
    : [];
  const ready = (key: string) => (value: boolean) =>
    setDisplayReady((previous) =>
      previous[key] === value ? previous : { ...previous, [key]: value },
    );
  const frozen =
    (!!attempt?.settings?.proctor && !cameraReady) ||
    visibilityQueued > 0 ||
    busy ||
    !!pending.current ||
    !!q?.answer_locked ||
    remaining === 0 ||
    sectionRemaining === 0 ||
    !!displayError ||
    required.some((key) => !displayReady[key]);
  const edit = (value: any) => {
    setAnswer(value);
    setDirty(true);
  };
  async function upload(file: File | undefined) {
    if (!file || !q || !attempt || frozen || dirty) return;
    if (!file.size || file.size > 10485760) { setError("Choose a file no larger than 10 MB."); return; }
    setBusy(true); setError("");
    try {
      const base64 = await new Promise<string>((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(String(reader.result).split(",")[1]);
        reader.onerror = () => reject(new Error("The file could not be read."));
        reader.readAsDataURL(file);
      });
      await send("attachment", { attempt_id: attempt.attempt_id, question_id: q.id, revision: q.revision, base64 });
    } catch (e) { setError(e instanceof Error ? e.message : "The file could not be read."); }
    finally { setBusy(false); }
  }
  return (
    <section aria-label="Exam attempt">
      {notice && <p role="status">{notice}</p>}
      {displayError && !pending.current && (
        <button
          className="secondary"
          disabled={busy}
          onClick={() => {
            if (
              !dirty ||
              window.confirm(
                "Reload saved answers and discard unsaved changes?",
              )
            )
              void send("start", {});
          }}
        >
          Reload saved answers
        </button>
      )}
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      {pending.current && !busy && (
        <>
          <button
            onClick={() =>
              void send(pending.current!.action, pending.current!.body)
            }
          >
            Retry last request
          </button>
          {attempt &&
            !attempt.completed &&
            pending.current.action !== "visibility" && (
              <button
                className="secondary"
                onClick={() => {
                  if (
                    window.confirm(
                      "Reload saved answers from the server? Any unsaved answer on this screen will be discarded.",
                    )
                  ) {
                    pending.current = null;
                    void send("start", {});
                  }
                }}
              >
                Resume saved answers
              </button>
            )}
        </>
      )}
      {(!attempt || attempt.completed) && (
        <section>
          <button
            className="secondary"
            disabled={busy || !!pending.current}
            onClick={() => void send("history", {})}
          >
            Load previous results
          </button>
          {history && (
            <>
              <h3>Latest submitted attempts (up to 50)</h3>
              {!history.length ? (
                <p>No submitted attempts were found for this exam.</p>
              ) : (
                <ul>
                  {history.map((row) => (
                    <li key={row.attempt_id}>
                      {new Date(row.finished_at).toLocaleString()} ·{" "}
                      {row.result
                        ? `${row.result.status} · ${row.result.score_percent}%`
                        : "Result not published"}{" "}
                      ·{" "}
                      <button
                        className="secondary"
                        disabled={busy || !!pending.current}
                        onClick={() =>
                          void send("result", { attempt_id: row.attempt_id })
                        }
                      >
                        View submitted result
                      </button>
                    </li>
                  ))}
                </ul>
              )}
            </>
          )}
        </section>
      )}
      {!attempt && !pending.current && (
        <>
          <p>
            Check exam setup first. The timer begins only when you start a new
            attempt; an existing attempt keeps its original timer.
          </p>
          {!prepared ? (
            <button disabled={busy} onClick={() => void send("prepare", {})}>
              Check exam setup
            </button>
          ) : (
            <button
              disabled={busy || (prepared.proctor && !cameraReady)}
              onClick={() => void send("start", {})}
            >
              Start or resume exam
            </button>
          )}
        </>
      )}
      {!attempt?.completed &&
        remaining !== 0 &&
        (attempt?.settings?.proctor ?? prepared?.proctor) && (
          <ExamCamera
            key={base}
            base={base}
            attemptId={attempt?.attempt_id ?? null}
            onReady={setCameraReady}
          />
        )}
      {attempt?.completed ? (
        <>
          <h3>Exam submitted</h3>
          <button
            className="secondary"
            disabled={busy || !!pending.current}
            onClick={() => {
              setAttempt(null);
              setPrepared(null);
              setRemaining(null);
              setSectionRemaining(null);
            }}
          >
            Exam setup
          </button>
          <p>Your saved answers have been submitted.</p>
          <button
            className="secondary"
            disabled={busy || !!pending.current}
            onClick={() =>
              void send("result", { attempt_id: attempt.attempt_id })
            }
          >
            Refresh result
          </button>
          {attempt.result ? (
            <p>
              Result: {attempt.result.status} · Score:{" "}
              {attempt.result.score_percent}%
            </p>
          ) : (
            <p>Your organisation will make results available when ready.</p>
          )}
        </>
      ) : (
        attempt && (
          <>
            <p role="timer">
              {remaining === null
                ? "No time limit"
                : `Time remaining: ${Math.floor(remaining / 60)}:${String(remaining % 60).padStart(2, "0")}`}
            </p>
            {attempt.settings.browser_tolerance &&
              !!attempt.settings.tolerance_count && (
                <p role="status">
                  Stay on this exam tab. Leaving it is recorded; at{" "}
                  {attempt.settings.tolerance_count} tab leaves, saved answers
                  are submitted automatically. Recorded:{" "}
                  {attempt.tolerance_count ?? 0}.
                </p>
              )}
            {attempt.settings.calculator_allowed && <ExamCalculator />}
            {attempt.section_clock?.active && (
              <div>
                <h3>{attempt.section_clock.active.label}</h3>
                <p>
                  Section time remaining:{" "}
                  {Math.floor((sectionRemaining ?? 0) / 60)}:
                  {String((sectionRemaining ?? 0) % 60).padStart(2, "0")}
                </p>
                <p>
                  Save each answer before time ends. Sections advance
                  automatically; finished sections cannot be reopened.
                </p>
              </div>
            )}
            {remaining === 0 && (
              <p role="alert">
                Time has ended. Submit your saved answers below. New answers
                cannot be saved.
              </p>
            )}
            {q && (
              <>
                <h3>
                  Question {index + 1} of {attempt.questions.length}
                </h3>
                {q.passage && (
                  <>
                    <h4>{q.passage.name}</h4>
                    <ExamRichContent
                      key={`${q.id}:${displayGeneration}:passage`}
                      mediaBase={mediaBase}
                      onReady={ready("passage")}
                      onError={setDisplayError}
                      value={q.passage.content}
                    />
                  </>
                )}
                <ExamRichContent
                  key={`${q.id}:${displayGeneration}:question`}
                  mediaBase={mediaBase}
                  onReady={ready("question")}
                  onError={setDisplayError}
                  value={q.content.question}
                />
                <fieldset disabled={frozen}>
                  <legend>Your answer</legend>
                  {q.type.startsWith("multiple_choice") &&
                    (q.option_order ?? [1, 2, 3, 4, 5, 6])
                      .filter((n) => q.content[`option${n}`])
                      .map((n) => (
                        <label
                          key={n}
                          style={{
                            display: "flex",
                            gap: 12,
                            alignItems: "baseline",
                          }}
                        >
                          <input
                            type={
                              q.type === "multiple_choice_radio"
                                ? "radio"
                                : "checkbox"
                            }
                            name="answer"
                            checked={
                              Array.isArray(answer) && answer.includes(n)
                            }
                            onChange={(e) =>
                              edit(
                                q.type === "multiple_choice_radio"
                                  ? [n]
                                  : e.target.checked
                                    ? [
                                        ...(Array.isArray(answer)
                                          ? answer
                                          : []),
                                        n,
                                      ]
                                    : (answer as number[]).filter(
                                        (v) => v !== n,
                                      ),
                              )
                            }
                          />
                          <ExamRichContent
                            key={`${q.id}:${displayGeneration}:option${n}`}
                            mediaBase={mediaBase}
                            onReady={ready(`option${n}`)}
                            onError={setDisplayError}
                            value={q.content[`option${n}`]}
                          />
                        </label>
                      ))}
                  {q.type === "true_false" &&
                    ["true", "false"].map((value) => (
                      <label key={value}>
                        <input
                          type="radio"
                          name="answer"
                          checked={answer === value}
                          onChange={() => edit(value)}
                        />
                        {value === "true" ? "True" : "False"}
                      </label>
                    ))}
                  {q.type === "nat" && (
                    <label>
                      Numerical answer
                      <input
                        type="text"
                        inputMode="decimal"
                        maxLength={100}
                        value={answer}
                        onChange={(e) => edit(e.target.value)}
                      />
                    </label>
                  )}
                  {q.type === "subjective" && (
                    <><label>
                      Written answer
                      <textarea
                        maxLength={20000}
                        value={answer}
                        onChange={(e) => edit(e.target.value)}
                      />
                    </label>
                    {q.attachments_enabled && <section aria-label="Answer file">
                      <label>Attach answer file (up to 10 MB)
                        <input type="file" accept=".txt,.pdf,.jpg,.jpeg,.png,.doc,.docx" disabled={frozen || dirty}
                          onChange={e => { const file = e.currentTarget.files?.[0]; e.currentTarget.value = ""; void upload(file); }} />
                      </label>
                      {dirty && <p>Save your written changes before attaching or extracting a file. For exams that lock answers, attach your file before saving.</p>}
                      {q.attachment_asset && /^[a-f0-9]{64}$/.test(q.attachment_asset) && <>
                        <a href={`${apiBase}${base}/attachments/${attempt.attempt_id}/${q.id}/${q.attachment_asset}`} download>Download saved answer file</a>
                        <button type="button" className="secondary" disabled={frozen || dirty}
                          onClick={() => void send("extract", { attempt_id: attempt.attempt_id, question_id: q.id, asset: q.attachment_asset })}>Extract text from saved file</button>
                        <p>Extraction uses English by default. Review the text before saving.</p>
                      </>}
                    </section>}</>
                  )}
                  {q.type === "fill_blank" &&
                    Array.from({ length: q.blank_count }, (_, i) => (
                      <label key={i}>
                        Blank {i + 1}
                        <input
                          maxLength={1000}
                          value={answer[i] ?? ""}
                          onChange={(e) => {
                            const values = [...answer];
                            values[i] = e.target.value;
                            edit(values);
                          }}
                        />
                      </label>
                    ))}
                  <label>
                    <input
                      type="checkbox"
                      checked={review}
                      onChange={(e) => {
                        setReview(e.target.checked);
                        setDirty(true);
                      }}
                    />
                    Mark for review
                  </label>
                </fieldset>
                <button
                  className="secondary"
                  disabled={frozen}
                  onClick={() =>
                    edit(
                      q.type === "fill_blank"
                        ? Array(q.blank_count).fill("")
                        : q.type.startsWith("multiple_choice")
                          ? []
                          : "",
                    )
                  }
                >
                  Clear answer
                </button>
                {q.answer_locked && (
                  <p>This answer is locked under the exam rules.</p>
                )}
                {!attempt.settings.allow_answer_change && (
                  <p>Saving locks this answer. Check it before saving.</p>
                )}
                <p role="status">
                  {dirty
                    ? "Unsaved changes"
                    : busy
                      ? "Saving…"
                      : "Saved answers are kept on the server."}
                </p>
                <button
                  disabled={frozen || !dirty}
                  onClick={() =>
                    void send("answer", {
                      attempt_id: attempt.attempt_id,
                      question_id: q.id,
                      revision: q.revision,
                      fields: {
                        option_selected: answer,
                        review,
                        lock_answer: !attempt.settings.allow_answer_change,
                      },
                    })
                  }
                >
                  Save answer
                </button>
                <div style={{ display: "flex", gap: 12, marginTop: 16 }}>
                  <button
                    className="secondary"
                    disabled={
                      busy ||
                      dirty ||
                      !!pending.current ||
                      index === 0 ||
                      sectionRemaining === 0 ||
                      (!!attempt.section_clock &&
                        !attempt.section_clock.active?.questions.includes(
                          attempt.questions[index - 1]?.id,
                        ))
                    }
                    onClick={() => select(attempt, index - 1)}
                  >
                    Previous question
                  </button>
                  <button
                    className="secondary"
                    disabled={
                      busy ||
                      dirty ||
                      !!pending.current ||
                      index === attempt.questions.length - 1 ||
                      sectionRemaining === 0 ||
                      (!!attempt.section_clock &&
                        !attempt.section_clock.active?.questions.includes(
                          attempt.questions[index + 1]?.id,
                        ))
                    }
                    onClick={() => select(attempt, index + 1)}
                  >
                    Next question
                  </button>
                </div>
              </>
            )}
            <hr />
            {!confirm ? (
              <button
                disabled={
                  busy ||
                  visibilityQueued > 0 ||
                  !!pending.current ||
                  (dirty && remaining !== 0)
                }
                onClick={() => setConfirm(true)}
              >
                Finish exam
              </button>
            ) : (
              <>
                <p>
                  Submit all saved answers? You cannot change them after
                  submission.
                  {dirty ? " Unsaved changes will not be included." : ""}
                </p>
                <button
                  disabled={busy || visibilityQueued > 0 || !!pending.current}
                  onClick={() =>
                    void send("submit", { attempt_id: attempt.attempt_id })
                  }
                >
                  Submit saved answers
                </button>
                <button
                  className="secondary"
                  disabled={busy}
                  onClick={() => setConfirm(false)}
                >
                  Keep working
                </button>
              </>
            )}
          </>
        )
      )}
    </section>
  );
}
