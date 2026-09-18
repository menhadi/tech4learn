import { useState } from "react";
import { canReplaceExistingFormula } from "./ExamExistingFormulaEditor";
import { api, ApiError } from "./api";
import { DraftForm } from "./DraftForm";
import { FormattedField } from "./ExamQuestionEditor";
import { retainedFormulaPreview } from "./retained-formula-preview";
import { canEditExistingText } from "./ExamExistingTextEditor";
import { QuestionImageUpload } from "./QuestionImageUpload";

const questionFields = [
  "question",
  "option1",
  "option2",
  "option3",
  "option4",
  "option5",
  "option6",
  "hint",
  "explanation",
  "fill_blank",
  "si_answer1",
];
const labels: Record<string, string> = {
  name: "Translated exam title",
  instruction: "Translated instructions",
  syllabus: "Translated syllabus",
  question: "Translated question",
  hint: "Translated hint",
  explanation: "Translated explanation",
  fill_blank: "Translated fill in the blank",
  si_answer1: "Translated model answer",
};
type Wording = Record<string, string | null>;
export function ExamTranslationEditor({
  org,
  central = false,
  mode = "question",
  examId,
  examRevision,
  languageId,
  translationRevision,
  question,
  mediaBase,
  onClose,
  onSaved,
}: {
  org: string;
  central?: boolean;
  mode?: "question" | "exam";
  examId: number;
  examRevision: string;
  languageId: number;
  translationRevision: string;
  question: {
    question_id: number;
    source: Wording;
    translation: Wording | null;
  };
  mediaBase: string;
  onClose: () => void;
  onSaved: () => void;
}) {
  const fields =
    mode === "exam" ? ["name", "instruction", "syllabus"] : questionFields;
  const [changes, setChanges] = useState<Record<string, string | null>>({});
  const [pending, setPending] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [imagePending, setImagePending] = useState(false);
  const validPatch = (value: any) =>
    value &&
    typeof value === "object" &&
    !Array.isArray(value) &&
    Object.entries(value).every(
      ([key, text]) =>
        fields.includes(key) &&
        (text === null || typeof text === "string") &&
        (!/<(?:img|svg|math|math-field)\b/i.test(
          question.translation?.[key] ?? "",
        ) ||
          ((canReplaceExistingFormula(
            question.translation?.[key] ?? "",
            mode === "question" || key !== "name",
          ) ||
            canEditExistingText(
              question.translation?.[key] ?? "",
              mode === "question" || key !== "name",
            )) &&
            (!/<img\b/i.test(question.translation?.[key] ?? "") ||
              (typeof text === "string" &&
                retainedFormulaPreview(
                  text,
                  question.translation?.[key] ?? "",
                  question.translation?.[key] ?? "",
                ) !== null)))),
    );
  return (
    <section className="panel">
      <h3>Edit translated {mode === "exam" ? "exam wording" : "question"}</h3>
      <p>
        Changes apply to{" "}
        {central ? "the central bank's" : "this organisation's"} translated{" "}
        {mode === "exam" ? "exam wording" : "question wherever it is used"}.
        Affected papers need translation approval again. Supported MathML
        formulas can be replaced using checked TeX. Images and unsupported
        formula markup are preserved.
      </p>
      <button type="button" disabled={busy || imagePending} onClick={onClose}>
        Close translation editor
      </button>
      {error && (
        <p role="alert" className="error">
          {error}
        </p>
      )}
      <DraftForm
        draftKey={`${central ? "central-exam" : "exam"}-translation-${org}-${examId}-${languageId}-${question.question_id}`}
        title="Translated wording"
        draftState={{ examRevision, translationRevision, changes, pending }}
        restoreState={(state) => {
          if (busy || pending || imagePending) return;
          if (
            state?.examRevision !== examRevision ||
            state?.translationRevision !== translationRevision ||
            !validPatch(state.changes)
          ) {
            setError(
              "This draft belongs to an older translation review. Reload and review the current wording before editing.",
            );
            return;
          }
          setChanges(state.changes);
          setPending(
            typeof state.pending === "string" &&
              /^[a-f0-9-]{36}$/.test(state.pending)
              ? state.pending
              : null,
          );
        }}
        onSubmit={async (event) => {
          event.preventDefault();
          if (busy || imagePending || !Object.keys(changes).length) return;
          const requestId = pending ?? crypto.randomUUID();
          setPending(requestId);
          setBusy(true);
          setError("");
          try {
            await api(
              `${central ? `/platform/exam-content/${org}/central` : `/organisations/${org}/exam-content`}/exams/${examId}/actions/save-${mode}-translation`,
              "POST",
              {
                revision: examRevision,
                request_id: requestId,
                fields: {
                  language_id: languageId,
                  translation_revision: translationRevision,
                  ...(mode === "question"
                    ? { question_id: question.question_id }
                    : {}),
                  wording: changes,
                },
              },
              35000,
            );
            onSaved();
          } catch (err) {
            if (
              err instanceof ApiError &&
              [400, 403, 404, 409].includes(err.status)
            )
              setPending(null);
            setError(
              err instanceof Error
                ? err.message
                : "Translation save could not be confirmed. Retry the same request.",
            );
          } finally {
            setBusy(false);
          }
        }}
      >
        <div data-no-draft="true">
          {fields
            .filter(
              (field) =>
                field === "question" ||
                field === "name" ||
                question.source[field] ||
                question.translation?.[field],
            )
            .map((field) => (
              <FormattedField
                key={field}
                label={
                  labels[field] ?? field.replace("option", "Translated option ")
                }
                value={
                  field in changes
                    ? (changes[field] ?? "")
                    : (question.translation?.[field] ?? "")
                }
                previewValue={
                  /<img\b/i.test(question.translation?.[field] ?? "")
                    ? (question.translation?.[field] ?? "")
                    : field in changes
                      ? (changes[field] ?? "")
                      : (question.translation?.[field] ?? "")
                }
                originalImageWording={
                  mode === "question" || field !== "name"
                    ? (question.translation?.[field] ?? "")
                    : undefined
                }
                mediaBase={mediaBase}
                disabled={busy || pending !== null || imagePending}
                onChange={(value) => {
                  setChanges((old) => ({ ...old, [field]: value }));
                  setError("");
                }}
              />
            ))}
        </div>
        {pending && (
          <p role="status">
            Retry keeps the same changes and reviewed version. Close and reload
            the review to check the saved state.
          </p>
        )}
        <button
          type="submit"
          disabled={busy || imagePending || !Object.keys(changes).length}
        >
          {busy
            ? "Saving translation…"
            : pending
              ? "Retry translation save"
              : "Save translated wording"}
        </button>
      </DraftForm>
      {question.translation && (
        <QuestionImageUpload
          key={`${examRevision}-${translationRevision}`}
          base={`${central ? `/platform/exam-content/${org}/central` : `/organisations/${org}/exam-content`}/exams/${examId}`}
          central={central}
          record={{
            id: examId,
            revision: examRevision,
            fields: {},
            preview_fields: Object.fromEntries(
              Object.entries(question.translation).map(([key, value]) => [
                key,
                value ?? "",
              ]),
            ),
          }}
          translation={{
            languageId,
            questionId: mode === "exam" ? 0 : question.question_id,
            revision: translationRevision,
            fields:
              mode === "exam"
                ? ["instruction", "syllabus"]
                : questionFields.filter(
                    (field) =>
                      field !== "fill_blank" &&
                      (field === "question" ||
                        question.source[field] ||
                        question.translation?.[field]),
                  ),
          }}
          disabled={busy || pending !== null || Object.keys(changes).length > 0}
          onPending={setImagePending}
          onSaved={() => onSaved()}
          onReload={onSaved}
        />
      )}
    </section>
  );
}
