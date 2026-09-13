import { useEffect, useRef, useState } from "react";
import { ExamRichContent } from "./ExamRichContent";
import { api, apiBase } from "./api";

type Question = {
  id: number;
  number: number;
  type: string;
  content: Record<string, string>;
  passage: { name: string; content: string } | null;
  blank_count: number;
  answer: any;
  review: boolean;
  answer_locked: boolean;
  revision: string;
};
type Attempt = {
  attempt_id: number;
  exam_id: number;
  name: string;
  remaining_seconds: number;
  time_limited: boolean;
  questions: Question[];
  settings: { allow_answer_change: boolean };
  completed?: boolean;
  result?: { status: string; score_percent: number } | null;
};
export function StudentExamAttempt({ base }: { base: string }) {
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
    [confirm, setConfirm] = useState(false);
  const pending = useRef<{ action: string; body: any } | null>(null),
    running = useRef(false),
    deadline = useRef(0);
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
    setBusy(true);
    setError("");
    pending.current ??= {
      action,
      body: { ...body, request_id: crypto.randomUUID() },
    };
    try {
      const request = pending.current;
      const result = await api<any>(
        `${base}/attempt/${request.action}`,
        "POST",
        request.body,
      );
      if (request.action === "answer") {
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
        setAttempt(result);
        select(result, 0);
        deadline.current = Date.now() + result.remaining_seconds * 1000;
        setRemaining(result.time_limited ? result.remaining_seconds : null);
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
    const timer = setInterval(
      () =>
        setRemaining(
          Math.max(0, Math.ceil((deadline.current - Date.now()) / 1000)),
        ),
      1000,
    );
    return () => clearInterval(timer);
  }, [attempt?.attempt_id, attempt?.completed]);
  useEffect(() => {
    const warn = (e: BeforeUnloadEvent) => {
      if (dirty || pending.current) {
        e.preventDefault();
        e.returnValue = "";
      }
    };
    window.addEventListener("beforeunload", warn);
    return () => window.removeEventListener("beforeunload", warn);
  }, [dirty]);
  const mediaBase =
    attempt && q
      ? `${apiBase}${base}/media/${attempt.attempt_id}/${q.id}`
      : undefined;
  const required = q
    ? [
        "question",
        ...(q.passage ? ["passage"] : []),
        ...(q.type.startsWith("multiple_choice")
          ? [1, 2, 3, 4, 5, 6]
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
    busy ||
    !!pending.current ||
    !!q?.answer_locked ||
    remaining === 0 ||
    !!displayError ||
    required.some((key) => !displayReady[key]);
  const edit = (value: any) => {
    setAnswer(value);
    setDirty(true);
  };
  return (
    <section aria-label="Exam attempt">
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
          {attempt && !attempt.completed && (
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
      {!attempt && !pending.current && (
        <>
          <p>
            Start begins the exam timer. Use Save answer before changing
            questions. You can resume saved work here after refreshing.
          </p>
          <button disabled={busy} onClick={() => void send("start", {})}>
            Start or resume exam
          </button>
        </>
      )}
      {attempt?.completed ? (
        <>
          <h3>Exam submitted</h3>
          <p>Your saved answers have been submitted.</p>
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
                    [1, 2, 3, 4, 5, 6]
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
                    <label>
                      Written answer
                      <textarea
                        maxLength={20000}
                        value={answer}
                        onChange={(e) => edit(e.target.value)}
                      />
                    </label>
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
                    disabled={busy || dirty || !!pending.current || index === 0}
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
                      index === attempt.questions.length - 1
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
                  busy || !!pending.current || (dirty && remaining !== 0)
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
                  disabled={busy}
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
