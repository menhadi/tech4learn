import { useEffect, useRef, useState } from "react";
import DOMPurify from "dompurify";
import { api, apiBase, ApiError } from "./api";
import { DraftForm } from "./DraftForm";
import { QuestionChoiceField } from "./QuestionChoiceField";
import { ExamRichContent } from "./ExamRichContent";
import { QuestionImageUpload } from "./QuestionImageUpload";
import { ExamFormulaInsert } from "./ExamFormulaInsert";
import { retainedFormulaPreview } from "./retained-formula-preview";
import { ExamExistingFormulaEditor } from "./ExamExistingFormulaEditor";
import { ExamExistingTextEditor } from "./ExamExistingTextEditor";

export type Snapshot = {
  id: number;
  revision: string;
  fields: Record<string, any>;
  preview_fields?: Record<string, string>;
  type?: string;
  type_name?: string;
};
export function FormattedField({
  label,
  value,
  onChange,
  disabled,
  previewValue,
  mediaBase,
  originalImageWording,
}: {
  label: string;
  value: string;
  onChange: (v: string) => void;
  disabled: boolean;
  previewValue?: string;
  mediaBase?: string;
  originalImageWording?: string;
}) {
  const editor = useRef<HTMLDivElement>(null);
  const [preview, setPreview] = useState(false);
  const retainedPreview =
    originalImageWording !== undefined && previewValue !== undefined
      ? retainedFormulaPreview(value, originalImageWording, previewValue)
      : null;
  const media = /<(?:img|svg|math|math-field)\b/i.test(value);
  const applyFormula = (updated: string) => {
    if (disabled) return;
    onChange(updated);
    // Mark the owning DraftForm dirty even when only template buttons were used.
    if (editor.current) {
      if (!media) editor.current.innerHTML = updated;
      editor.current.dispatchEvent(new Event("input", { bubbles: true }));
    }
  };
  const clean = DOMPurify.sanitize(value, {
    ALLOWED_TAGS: [
      "p",
      "div",
      "br",
      "b",
      "strong",
      "i",
      "em",
      "u",
      "s",
      "sub",
      "sup",
      "ul",
      "ol",
      "li",
      "span",
      "table",
      "thead",
      "tbody",
      "tr",
      "td",
      "th",
    ],
    ALLOWED_ATTR: ["colspan", "rowspan"],
  });
  useEffect(() => {
    if (editor.current && editor.current.innerHTML !== clean)
      editor.current.innerHTML = clean;
  }, [clean]);
  return (
    <fieldset>
      <legend>{label}</legend>
      {media && (
        <ExamExistingTextEditor
          key={value}
          value={value}
          retainedImages={retainedPreview !== null}
          disabled={disabled}
          onChange={applyFormula}
        />
      )}
      {media && (
        <p>
          This field contains image or formula markup. Use the text, formula or
          image controls to change it. Saving other fields preserves its
          content.
        </p>
      )}
      <div
        ref={editor}
        role="textbox"
        aria-label={label}
        aria-multiline="true"
        aria-readonly={disabled || media}
        contentEditable={!disabled && !media}
        suppressContentEditableWarning
        style={{
          minHeight: 90,
          border: "1px solid #d1d5db",
          padding: 12,
          whiteSpace: "pre-wrap",
        }}
        onInput={(e) => {
          if (disabled || media) return;
          onChange(
            DOMPurify.sanitize(e.currentTarget.innerHTML, {
              ALLOWED_TAGS: [
                "p",
                "div",
                "br",
                "b",
                "strong",
                "i",
                "em",
                "u",
                "s",
                "sub",
                "sup",
                "ul",
                "ol",
                "li",
                "span",
                "table",
                "thead",
                "tbody",
                "tr",
                "td",
                "th",
              ],
              ALLOWED_ATTR: ["colspan", "rowspan"],
            }),
          );
        }}
        onPaste={(e) => {
          e.preventDefault();
          if (disabled || media) return;
          document.execCommand(
            "insertText",
            false,
            e.clipboardData.getData("text/plain"),
          );
        }}
        onDrop={(e) => e.preventDefault()}
      />
      {media && (
        <ExamExistingFormulaEditor
          value={value}
          retainedImages={retainedPreview !== null}
          disabled={disabled}
          onChange={applyFormula}
        />
      )}
      {media &&
        ((previewValue !== undefined && mediaBase) ||
          /<math\b/i.test(value)) && (
          <section aria-label={`${label} preview`}>
            <ExamRichContent
              value={
                /<img\b/i.test(value)
                  ? (retainedPreview ?? previewValue ?? "")
                  : value
              }
              mediaBase={mediaBase}
            />
          </section>
        )}
      {!media && (
        <>
          <ExamFormulaInsert
            disabled={disabled}
            onInsert={(formula) => applyFormula(clean + formula)}
          />
          <p>
            Write formulas using {"\\(x^2\\)"} within text or {"\\[x^2\\]"} on a
            separate line. Preview checks how the content will look to students.
          </p>
          <button type="button" onClick={() => setPreview(!preview)}>
            {preview ? "Hide preview" : "Preview formatting"}
          </button>
          {preview && (
            <section aria-label={`${label} preview`}>
              <ExamRichContent value={clean} />
            </section>
          )}
        </>
      )}
    </fieldset>
  );
}
export function ExamQuestionEditor({
  org,
  central = false,
  id,
  onClose,
}: {
  org: string;
  central?: boolean;
  id: number | "new";
  onClose: () => void;
}) {
  const [record, setRecord] = useState<Snapshot | null>(null),
    [changes, setChanges] = useState<Record<string, any>>({}),
    [busy, setBusy] = useState(false),
    [error, setError] = useState(""),
    [notice, setNotice] = useState(""),
    [request, setRequest] = useState<string | null>(null),
    [reload, setReload] = useState(0);
  const [activeId, setActiveId] = useState<number | "new">(id);
  const [selectedType, setSelectedType] = useState("");
  const [imagePending, setImagePending] = useState(false);
  const base = `${central ? `/platform/exam-content/${org}/central` : `/organisations/${org}/exam-content`}/questions/${activeId}`;
  const mediaBase = `${apiBase}${base}${central && record ? `/${record.revision}` : ""}/media`;
  useEffect(() => {
    let active = true;
    setBusy(true);
    setError("");
    if (central) {
      setRecord(null);
      setNotice("");
    }
    api<Snapshot>(base)
      .then((r) => {
        if (active) {
          setRecord(r);
          setSelectedType(r.type ?? "");
          setChanges({});
          setRequest(null);
        }
      })
      .catch((e) => {
        if (active) setError(e.message);
      })
      .finally(() => {
        if (active) setBusy(false);
      });
    return () => {
      active = false;
    };
  }, [base, central, reload]);
  const writePending = request !== null;
  const set = (key: string, value: any) => {
    if (imagePending || writePending) return;
    setChanges((old) => ({ ...old, [key]: value }));
    setRequest(null);
    setNotice("");
  };
  const values = { ...record?.fields, ...changes };
  return (
    <section className="panel">
      <h3>
        {activeId === "new" ? "Create question" : `Question #${activeId}`}
      </h3>
      {central && (
        <p>
          {activeId === "new"
            ? "Creating a question in the shared central bank."
            : "Editing the shared central original. Existing organisation copies keep their own changes."}
        </p>
      )}
      <button
        className="secondary"
        onClick={onClose}
        disabled={busy || imagePending || writePending}
      >
        Back to question bank
      </button>
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      {notice && <p role="status">{notice}</p>}
      {writePending && !busy && (
        <p role="status">
          The save has not been confirmed. Retry Save question to check the same
          request before editing further.
        </p>
      )}
      {record && (
        <DraftForm
          key={activeId}
          draftKey={`exam-question-${central ? "central-" : ""}${org}-${activeId}`}
          title={record.type_name ?? "Question details"}
          draftState={{
            revision: record.revision,
            changes,
            selectedType,
            request,
          }}
          restoreState={(s) => {
            if (imagePending || writePending) return;
            if (
              s?.revision === record.revision &&
              s.changes &&
              typeof s.changes === "object" &&
              !Array.isArray(s.changes)
            ) {
              setChanges(s.changes);
              setSelectedType(
                typeof s.selectedType === "string"
                  ? s.selectedType
                  : (record.type ?? ""),
              );
              setRequest(typeof s.request === "string" ? s.request : null);
            } else
              setError(
                "This draft belongs to an older question version. Reload and review the current question before making changes.",
              );
          }}
          onSubmit={async (e) => {
            e.preventDefault();
            if (imagePending) return;
            const requestId = request ?? crypto.randomUUID();
            setRequest(requestId);
            setBusy(true);
            setError("");
            setNotice("");
            try {
              const saved = await api<Snapshot>(
                activeId === "new" ? base.replace(/\/new$/, "") : base,
                "POST",
                {
                  fields: activeId === "new" ? values : changes,
                  revision: record.revision,
                  request_id: requestId,
                },
              );
              setRecord({ ...record, ...saved });
              if (activeId === "new") setActiveId(saved.id);
              setChanges({});
              setRequest(null);
              setNotice(
                central
                  ? "Central original saved. Existing organisation copies are unchanged."
                  : "Question saved in your organisation.",
              );
            } catch (e) {
              if (e instanceof ApiError && [400, 409, 422].includes(e.status))
                setRequest(null);
              setError(
                e instanceof Error ? e.message : "Unable to save question.",
              );
              throw e;
            } finally {
              setBusy(false);
            }
          }}
        >
          {activeId === "new" && (
            <QuestionChoiceField
              org={org}
              central={central}
              kind="types"
              label="Question type"
              value={values.qtype_id ?? null}
              required
              disabled={busy || imagePending || writePending}
              onChange={(v, option) => {
                set("qtype_id", v);
                setSelectedType(option?.type ?? "");
              }}
            />
          )}
          <details open={activeId === "new"}>
            <summary>Classification and language</summary>
            <QuestionChoiceField
              org={org}
              central={central}
              kind="groups"
              label="Exam groups"
              multiple
              required
              value={values.group_ids ?? []}
              disabled={busy || imagePending || writePending}
              onChange={(v) => set("group_ids", v)}
            />
            <QuestionChoiceField
              org={org}
              central={central}
              kind="languages"
              label="Language"
              required
              value={values.language_id ?? null}
              disabled={busy || imagePending || writePending}
              onChange={(v) => set("language_id", v)}
            />
            {(
              [
                ["subjects", "subject_id", "Subject"],
                ["topics", "topic_id", "Topic"],
                ["subtopics", "stopic_id", "Subtopic"],
                ["sections", "question_section_id", "Question section"],
                ["passages", "passage_id", "Passage"],
                ["difficulties", "diff_id", "Difficulty"],
              ] as const
            ).map(([kind, key, label]) => (
              <QuestionChoiceField
                key={key}
                org={org}
                central={central}
                kind={kind}
                label={label}
                value={values[key] ?? null}
                disabled={busy || imagePending || writePending}
                onChange={(v) => set(key, v)}
              />
            ))}
            <p>
              ExamElite validates that subjects, topics and sections belong to
              the selected exam groups.
            </p>
          </details>
          <FormattedField
            label="Question"
            previewValue={record?.preview_fields?.question}
            originalImageWording={
              record.id > 0 ? String(record.fields.question ?? "") : undefined
            }
            mediaBase={mediaBase}
            value={String(values.question ?? "")}
            disabled={busy || imagePending || writePending}
            onChange={(v) => set("question", v)}
          />
          {selectedType === "M" && (
            <fieldset>
              <legend>Options and correct answers</legend>
              {[1, 2, 3, 4, 5, 6].map((n) => (
                <div key={n}>
                  <FormattedField
                    label={`Option ${n}`}
                    previewValue={record?.preview_fields?.["option" + n]}
                    originalImageWording={
                      record.id > 0
                        ? String(record.fields["option" + n] ?? "")
                        : undefined
                    }
                    mediaBase={mediaBase}
                    value={String(values["option" + n] ?? "")}
                    disabled={busy || imagePending || writePending}
                    onChange={(v) => set("option" + n, v)}
                  />
                  <label>
                    <input
                      type="checkbox"
                      checked={(values.correct_answers ?? []).includes(n)}
                      disabled={busy || imagePending || writePending}
                      onChange={(e) =>
                        set(
                          "correct_answers",
                          e.target.checked
                            ? [...(values.correct_answers ?? []), n]
                            : (values.correct_answers ?? []).filter(
                                (x: number) => x !== n,
                              ),
                        )
                      }
                    />{" "}
                    Option {n} is correct
                  </label>
                </div>
              ))}
            </fieldset>
          )}
          {selectedType === "T" && (
            <label>
              Correct answer
              <select
                value={values.true_false ?? ""}
                disabled={busy || imagePending || writePending}
                onChange={(e) => set("true_false", e.target.value)}
              >
                <option value="">Choose answer</option>
                <option value="True">True</option>
                <option value="False">False</option>
              </select>
            </label>
          )}
          {selectedType === "NAT" && (
            <fieldset>
              <legend>Numerical answer</legend>
              <label>
                Answer rule
                <select
                  value={values.nat_mode ?? "exact"}
                  disabled={busy || imagePending || writePending}
                  onChange={(e) => set("nat_mode", e.target.value)}
                >
                  <option value="exact">Exact value</option>
                  <option value="range">Accepted range</option>
                  <option value="tolerance">Value with tolerance</option>
                </select>
              </label>
              {(values.nat_mode === "range"
                ? ["nat_min", "nat_max"]
                : values.nat_mode === "tolerance"
                  ? ["nat_value", "nat_tolerance"]
                  : ["nat_value"]
              ).map((key) => (
                <label key={key}>
                  {
                    {
                      nat_min: "Minimum",
                      nat_max: "Maximum",
                      nat_value: "Answer",
                      nat_tolerance: "Tolerance",
                    }[key]
                  }
                  <input
                    type="number"
                    step="any"
                    value={values[key] ?? ""}
                    disabled={busy || imagePending || writePending}
                    onChange={(e) =>
                      set(
                        key,
                        e.target.value === "" ? null : Number(e.target.value),
                      )
                    }
                  />
                </label>
              ))}
            </fieldset>
          )}
          {["F", "B"].includes(selectedType) && (
            <fieldset>
              <legend>Accepted answers for each blank</legend>
              {(values.fill_blank_answers ?? []).map(
                (blank: any, i: number) => (
                  <label key={i}>
                    Blank {i + 1} — separate alternatives with |
                    <input
                      value={blank.accepted_answers ?? ""}
                      disabled={busy || imagePending || writePending}
                      onChange={(e) =>
                        set(
                          "fill_blank_answers",
                          values.fill_blank_answers.map((b: any, n: number) =>
                            n === i ? { accepted_answers: e.target.value } : b,
                          ),
                        )
                      }
                    />
                  </label>
                ),
              )}
              <button
                type="button"
                className="secondary"
                disabled={
                  busy ||
                  imagePending ||
                  writePending ||
                  (values.fill_blank_answers ?? []).length >= 20
                }
                onClick={() =>
                  set("fill_blank_answers", [
                    ...(values.fill_blank_answers ?? []),
                    { accepted_answers: "" },
                  ])
                }
              >
                Add blank
              </button>
            </fieldset>
          )}
          {selectedType === "S" && (
            <FormattedField
              label="Model answer"
              previewValue={record?.preview_fields?.si_answer1}
              originalImageWording={
                record.id > 0
                  ? String(record.fields.si_answer1 ?? "")
                  : undefined
              }
              mediaBase={mediaBase}
              value={String(values.si_answer1 ?? "")}
              disabled={busy || imagePending || writePending}
              onChange={(v) => set("si_answer1", v)}
            />
          )}
          <fieldset>
            <legend>Marking</legend>
            {[
              ["marks", "Marks"],
              ["negative_marks", "Negative marks"],
            ].map(([key, label]) => (
              <label key={key}>
                {label}
                <input
                  type="number"
                  step="any"
                  value={values[key] ?? ""}
                  disabled={busy || imagePending || writePending}
                  onChange={(e) =>
                    set(
                      key,
                      e.target.value === "" ? null : Number(e.target.value),
                    )
                  }
                />
              </label>
            ))}
          </fieldset>
          <FormattedField
            label="Hint"
            previewValue={record?.preview_fields?.hint}
            originalImageWording={
              record.id > 0 ? String(record.fields.hint ?? "") : undefined
            }
            mediaBase={mediaBase}
            value={String(values.hint ?? "")}
            disabled={busy || imagePending || writePending}
            onChange={(v) => set("hint", v)}
          />
          <FormattedField
            label="Explanation"
            previewValue={record?.preview_fields?.explanation}
            originalImageWording={
              record.id > 0
                ? String(record.fields.explanation ?? "")
                : undefined
            }
            mediaBase={mediaBase}
            value={String(values.explanation ?? "")}
            disabled={busy || imagePending || writePending}
            onChange={(v) => set("explanation", v)}
          />
          <button
            disabled={busy || imagePending || Object.keys(changes).length === 0}
          >
            Save question
          </button>
          <button
            className="secondary"
            type="button"
            disabled={busy || imagePending || writePending}
            onClick={() => setReload((v) => v + 1)}
          >
            Reload saved question
          </button>
        </DraftForm>
      )}
      {record && activeId !== "new" && (
        <QuestionImageUpload
          key={`${base}-${record.revision}-${reload}`}
          base={base}
          central={central}
          record={record}
          disabled={busy || writePending || Object.keys(changes).length > 0}
          onPending={setImagePending}
          onSaved={(saved) => {
            setRecord(saved);
            setImagePending(false);
            setNotice("Question image saved.");
          }}
          onReload={() => {
            setImagePending(false);
            setReload((value) => value + 1);
          }}
        />
      )}
    </section>
  );
}
