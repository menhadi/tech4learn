import { useEffect, useRef, useState } from "react";
import { api, apiBase, ApiError } from "./api";
import { QuestionChoiceField } from "./QuestionChoiceField";
import { ExamRichContent } from "./ExamRichContent";
import type { Exam } from "./ExamBuilder";
import { ExamTranslationEditor } from "./ExamTranslationEditor";

type Wording = Record<string, string | null>;
type Review = {
  exam_id: number;
  language_id: number;
  language_name: string;
  revision: string;
  approved: boolean;
  is_source_language: boolean;
  progress: {
    status: string;
    translated: number;
    remaining: number;
    total: number;
    exam_content_ready: boolean;
  };
  source: Wording;
  translation: Wording | null;
  items: {
    question_id: number;
    source: Wording;
    translation: Wording | null;
    stale_fields: string[];
  }[];
  next: number | null;
};
const labels: Record<string, string> = {
  name: "Exam name",
  instruction: "Instructions",
  syllabus: "Syllabus",
  question: "Question",
  hint: "Hint",
  explanation: "Explanation",
  fill_blank: "Fill in the blank",
  si_answer1: "Short answer",
};
function WordingComparison({
  source,
  translation,
  stale = [],
  mediaBase,
}: {
  source: Wording;
  translation: Wording | null;
  stale?: string[];
  mediaBase: string;
}) {
  return (
    <>
      {Object.keys(source)
        .filter((field) => source[field] || translation?.[field])
        .map((field) => (
          <section key={field}>
            <h4>{labels[field] ?? field.replace("option", "Option ")}</h4>
            {stale.includes(field) && (
              <p role="status">Missing or outdated translation</p>
            )}
            <div
              style={{
                display: "grid",
                gridTemplateColumns:
                  "repeat(auto-fit, minmax(min(260px, 100%), 1fr))",
                gap: "1rem",
              }}
            >
              <div>
                <strong>Source</strong>
                <ExamRichContent
                  value={source[field] ?? ""}
                  mediaBase={mediaBase}
                />
              </div>
              <div>
                <strong>Translation</strong>
                {translation?.[field] ? (
                  <ExamRichContent
                    value={translation[field]!}
                    mediaBase={mediaBase}
                  />
                ) : (
                  <p>Not translated</p>
                )}
              </div>
            </div>
          </section>
        ))}
    </>
  );
}

export function ExamTranslationReview({
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
  const [language, setLanguage] = useState<number | null>(null);
  const [review, setReview] = useState<Review | null>(null);
  const [selected, setSelected] = useState(0);
  const [cursors, setCursors] = useState([0]);
  const [page, setPage] = useState(0);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  const [editing, setEditing] = useState(false);
  const [editingExam, setEditingExam] = useState(false);
  const [confirmed, setConfirmed] = useState(false);
  const [pending, setPending] = useState<{
    action: "approve-translation" | "refresh-translation";
    fields: { language_id: number; translation_revision: string };
    revision: string;
    request_id: string;
  } | null>(null);
  const request = useRef(0);
  useEffect(
    () => () => {
      request.current++;
    },
    [],
  );
  useEffect(() => {
    if (disabled) {
      request.current++;
      setReview(null);
      setEditing(false);
      setConfirmed(false);
      setBusy(false);
    }
  }, [disabled]);
  const base = `${central ? `/platform/exam-content/${org}/central` : `/organisations/${org}/exam-content`}/exams/${record.id}/translations/${language}`;
  async function approve(
    requestedAction:
      | "approve-translation"
      | "refresh-translation" = "approve-translation",
  ) {
    const action = pending?.action ?? requestedAction;
    const complete =
      review?.progress.remaining === 0 && review.progress.exam_content_ready;
    if (
      central ||
      disabled ||
      editing ||
      busy ||
      (!pending &&
        (!review ||
          (action === "approve-translation"
            ? !confirmed || review.approved || !complete
            : complete || review.progress.status === "processing")))
    )
      return;
    const payload = pending ?? {
      action,
      fields: {
        language_id: review!.language_id,
        translation_revision: review!.revision,
      },
      revision: record.revision,
      request_id: crypto.randomUUID(),
    };
    const version = ++request.current;
    setPending(payload);
    setBusy(true);
    setMessage("");
    try {
      const { action: _action, ...body } = payload;
      await api(
        `/organisations/${org}/exam-content/exams/${record.id}/actions/${action}`,
        "POST",
        body,
        35000,
      );
      if (version !== request.current) return;
      setPending(null);
      setReview(null);
      setConfirmed(false);
      setMessage(
        action === "approve-translation"
          ? "Translation approved. Reload the review to see its current status. Automatic PDFs follow the saved exam settings."
          : "Translation refresh requested. The ExamElite worker must finish before the new wording can be reviewed. Reload to check progress.",
      );
    } catch (error) {
      if (version !== request.current) return;
      if (
        error instanceof ApiError &&
        [400, 403, 404, 409].includes(error.status)
      ) {
        setPending(null);
        setReview(null);
        setConfirmed(false);
      }
      setMessage(
        error instanceof Error
          ? error.message
          : "The translation request could not be confirmed. Retry the same request or reload the review.",
      );
    } finally {
      if (version === request.current) setBusy(false);
    }
  }
  async function load(nextPage = 0, restart = false) {
    if (disabled || editing || busy || language === null) return;
    const version = ++request.current;
    setBusy(true);
    setMessage("");
    const after = restart
      ? 0
      : nextPage > page
        ? review?.next
        : cursors[nextPage];
    const params = new URLSearchParams({ after: String(after ?? 0) });
    if (!restart && review) params.set("revision", review.revision);
    try {
      const result = await api<Review>(`${base}?${params}`);
      if (version !== request.current) return;
      setReview(result);
      setConfirmed(false);
      if (restart) setPending(null);
      setSelected(result.items[0]?.question_id ?? 0);
      setPage(nextPage);
      setCursors(
        restart
          ? [0]
          : (old) => {
              const copy = [...old];
              copy[nextPage] = after ?? 0;
              return copy;
            },
      );
    } catch (error) {
      if (version !== request.current) return;
      if (error instanceof ApiError && [403, 404, 409].includes(error.status))
        setReview(null);
      setMessage(
        error instanceof ApiError && error.status === 409
          ? "The source or translation changed. Reload the review from the first page."
          : error instanceof Error
            ? error.message
            : "Unable to load translation review.",
      );
    } finally {
      if (version === request.current) setBusy(false);
    }
  }
  const question = review?.items.find((item) => item.question_id === selected);
  const mediaBase = (questionId: number) =>
    `${apiBase}${base}/media/${questionId}/${review!.revision}`;
  return (
    <details className="card">
      <summary>Review translations</summary>
      <p>
        {central
          ? "Compare saved central source and translated wording. Central editing, refresh and approval controls are not available yet."
          : "Compare saved source and translated wording before approval. Basic translated text can be edited below."}
      </p>
      <QuestionChoiceField
        central={central}
        org={org}
        kind="languages"
        label="Review language"
        value={language}
        allowedIds={record.fields.language_ids}
        disabled={disabled || busy || pending !== null || editing}
        onChange={(value) => {
          setLanguage(value);
          setReview(null);
          setConfirmed(false);
          setPage(0);
          setCursors([0]);
          setMessage("");
        }}
      />
      <button
        type="button"
        disabled={disabled || busy || language === null || editing}
        onClick={() => void load(0, true)}
      >
        {busy ? "Loading review…" : "Reload translation review"}
      </button>
      {message && (
        <p role="alert" className="error">
          {message}
        </p>
      )}
      {pending && (
        <div role="status">
          <p>
            The translation request has not been confirmed. Retry preserves the
            same action, reviewed version and request. Reloading checks the
            saved status before starting another request.
          </p>
          <button
            type="button"
            disabled={disabled || busy}
            onClick={() => void approve()}
          >
            {pending.action === "approve-translation"
              ? "Retry translation approval"
              : "Retry translation refresh"}
          </button>
        </div>
      )}
      {review && (
        <>
          {!central &&
            (review.progress.remaining > 0 ||
              !review.progress.exam_content_ready) &&
            !pending && (
              <div>
                <p>
                  Refresh uses the organisation's ExamElite AI translation
                  service. Automatic approval and PDFs follow its saved
                  automation settings.
                </p>
                <button
                  type="button"
                  disabled={
                    disabled ||
                    busy ||
                    editing ||
                    review.progress.status === "processing"
                  }
                  onClick={() => void approve("refresh-translation")}
                >
                  {review.progress.status === "processing"
                    ? "Translation is processing"
                    : "Refresh missing or outdated translations"}
                </button>
              </div>
            )}
          <p>
            {review.progress.translated} of {review.progress.total} questions
            have current translations; {review.progress.remaining} need
            translation or refresh.
          </p>
          <p>
            Exam wording:{" "}
            {review.progress.exam_content_ready
              ? "current"
              : "missing or outdated"}
            .{" "}
            {review.approved
              ? "Native approval is recorded."
              : "Native approval is not recorded."}
          </p>
          <details>
            <summary>Exam wording</summary>
            <WordingComparison
              source={review.source}
              translation={review.translation}
              mediaBase={mediaBase(0)}
            />
            {!central &&
              !review.is_source_language &&
              !pending &&
              review.progress.status !== "processing" &&
              !editing && (
                <button
                  type="button"
                  disabled={busy || disabled}
                  onClick={() => {
                    setEditingExam(true);
                    setEditing(true);
                  }}
                >
                  Edit translated exam wording
                </button>
              )}
            {!central && editing && editingExam && (
              <ExamTranslationEditor
                key={`${review.revision}-exam`}
                mode="exam"
                org={org}
                examId={record.id}
                examRevision={record.revision}
                languageId={review.language_id}
                translationRevision={review.revision}
                question={{
                  question_id: 0,
                  source: review.source,
                  translation: review.translation,
                }}
                mediaBase={mediaBase(0)}
                onClose={() => setEditing(false)}
                onSaved={() => {
                  setEditing(false);
                  setReview(null);
                  setConfirmed(false);
                  setMessage(
                    "Exam wording saved. Reload the translation review before approving it.",
                  );
                }}
              />
            )}
          </details>
          {review.items.length ? (
            <>
              <label>
                Question on this page
                <select
                  value={selected}
                  disabled={busy || pending !== null || editing}
                  onChange={(event) => setSelected(Number(event.target.value))}
                >
                  {review.items.map((item, index) => (
                    <option key={item.question_id} value={item.question_id}>
                      Question {page * 50 + index + 1}
                      {item.stale_fields.length
                        ? " — needs translation review"
                        : ""}
                    </option>
                  ))}
                </select>
              </label>
              {question && (
                <WordingComparison
                  key={`${review.revision}-${selected}`}
                  source={question.source}
                  translation={question.translation}
                  stale={question.stale_fields}
                  mediaBase={mediaBase(selected)}
                />
              )}
              {!central &&
                question &&
                !review.is_source_language &&
                !pending &&
                review.progress.status !== "processing" &&
                !editing && (
                  <button
                    type="button"
                    disabled={busy || disabled}
                    onClick={() => {
                      setEditingExam(false);
                      setEditing(true);
                    }}
                  >
                    Edit translated wording
                  </button>
                )}
              {!central && question && editing && !editingExam && (
                <ExamTranslationEditor
                  key={`${review.revision}-${selected}`}
                  org={org}
                  examId={record.id}
                  examRevision={record.revision}
                  languageId={review.language_id}
                  translationRevision={review.revision}
                  question={question}
                  mediaBase={mediaBase(selected)}
                  onClose={() => setEditing(false)}
                  onSaved={() => {
                    setEditing(false);
                    setReview(null);
                    setConfirmed(false);
                    setMessage(
                      "Translated wording saved. Reload the review before approving it.",
                    );
                  }}
                />
              )}
            </>
          ) : (
            <p>No questions on this page.</p>
          )}
          <p>Review page {page + 1}. Up to 50 questions per page.</p>
          <button
            type="button"
            disabled={busy || pending !== null || page === 0 || editing}
            onClick={() => void load(page - 1)}
          >
            Previous review page
          </button>
          <button
            type="button"
            disabled={
              busy || pending !== null || review.next === null || editing
            }
            onClick={() => void load(page + 1)}
          >
            Next review page
          </button>
          {!central &&
            !review.approved &&
            review.progress.remaining === 0 &&
            review.progress.exam_content_ready &&
            !pending && (
              <fieldset disabled={disabled || busy || editing}>
                <legend>Approve this translation</legend>
                <p>
                  Approval may start automatic PDF generation for the exam's
                  packages.
                </p>
                <label>
                  <input
                    type="checkbox"
                    checked={confirmed}
                    onChange={(event) => setConfirmed(event.target.checked)}
                  />
                  I have reviewed the exam wording and all translated questions.
                </label>
                <button
                  type="button"
                  disabled={!confirmed}
                  onClick={() => void approve()}
                >
                  Approve reviewed translation
                </button>
              </fieldset>
            )}
        </>
      )}
    </details>
  );
}
