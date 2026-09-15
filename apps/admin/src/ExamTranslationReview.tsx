import { useEffect, useRef, useState } from "react";
import { api, apiBase, ApiError } from "./api";
import { QuestionChoiceField } from "./QuestionChoiceField";
import { ExamRichContent } from "./ExamRichContent";
import type { Exam } from "./ExamBuilder";

type Wording = Record<string, string | null>;
type Review = {
  exam_id: number;
  language_id: number;
  language_name: string;
  revision: string;
  approved: boolean;
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
  record,
  disabled,
}: {
  org: string;
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
      setBusy(false);
    }
  }, [disabled]);
  const base = `/organisations/${org}/exam-content/exams/${record.id}/translations/${language}`;
  async function load(nextPage = 0, restart = false) {
    if (disabled || busy || language === null) return;
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
        Compare saved source and translated wording. Translation edits and
        approval are not yet available here.
      </p>
      <QuestionChoiceField
        org={org}
        kind="languages"
        label="Review language"
        value={language}
        allowedIds={record.fields.language_ids}
        disabled={disabled || busy}
        onChange={(value) => {
          setLanguage(value);
          setReview(null);
          setPage(0);
          setCursors([0]);
          setMessage("");
        }}
      />
      <button
        type="button"
        disabled={disabled || busy || language === null}
        onClick={() => void load(0, true)}
      >
        {busy ? "Loading review…" : "Reload translation review"}
      </button>
      {message && (
        <p role="alert" className="error">
          {message}
        </p>
      )}
      {review && (
        <>
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
          </details>
          {review.items.length ? (
            <>
              <label>
                Question on this page
                <select
                  value={selected}
                  disabled={busy}
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
            </>
          ) : (
            <p>No questions on this page.</p>
          )}
          <p>Review page {page + 1}. Up to 50 questions per page.</p>
          <button
            type="button"
            disabled={busy || page === 0}
            onClick={() => void load(page - 1)}
          >
            Previous review page
          </button>
          <button
            type="button"
            disabled={busy || review.next === null}
            onClick={() => void load(page + 1)}
          >
            Next review page
          </button>
        </>
      )}
    </details>
  );
}
