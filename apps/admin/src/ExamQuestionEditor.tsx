import { useEffect, useRef, useState } from "react";
import DOMPurify from "dompurify";
import { api } from "./api";
import { DraftForm } from "./DraftForm";

type Snapshot = {
  id: number;
  revision: string;
  fields: Record<string, any>;
  type?: string;
  type_name?: string;
};
function FormattedField({
  label,
  value,
  onChange,
  disabled,
}: {
  label: string;
  value: string;
  onChange: (v: string) => void;
  disabled: boolean;
}) {
  const editor = useRef<HTMLDivElement>(null);
  const media = /<(?:img|svg|math|math-field)\b/i.test(value);
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
        <p>
          Image or formula content is preserved in ExamElite but is not fully
          displayed here. This field is read-only until the native media editor
          is integrated.
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
        onInput={(e) =>
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
          )
        }
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
    </fieldset>
  );
}
export function ExamQuestionEditor({
  org,
  id,
  onClose,
}: {
  org: string;
  id: number;
  onClose: () => void;
}) {
  const [record, setRecord] = useState<Snapshot | null>(null),
    [changes, setChanges] = useState<Record<string, any>>({}),
    [busy, setBusy] = useState(false),
    [error, setError] = useState(""),
    [notice, setNotice] = useState(""),
    [request, setRequest] = useState<string | null>(null),
    [reload, setReload] = useState(0);
  const base = `/organisations/${org}/exam-content/questions/${id}`;
  useEffect(() => {
    let active = true;
    setBusy(true);
    setError("");
    api<Snapshot>(base)
      .then((r) => {
        if (active) {
          setRecord(r);
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
  }, [base, reload]);
  const set = (key: string, value: any) => {
    setChanges((old) => ({ ...old, [key]: value }));
    setRequest(null);
    setNotice("");
  };
  const values = { ...record?.fields, ...changes };
  return (
    <section className="panel">
      <h3>Question #{id}</h3>
      <button className="secondary" onClick={onClose} disabled={busy}>
        Back to question bank
      </button>
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      {notice && <p role="status">{notice}</p>}
      {record && (
        <DraftForm
          draftKey={`exam-question-${org}-${id}`}
          title={record.type_name ?? "Question details"}
          draftState={{ revision: record.revision, changes }}
          restoreState={(s) => {
            if (
              s?.revision === record.revision &&
              s.changes &&
              typeof s.changes === "object" &&
              !Array.isArray(s.changes)
            ) {
              setChanges(s.changes);
              setRequest(null);
            } else
              setError(
                "This draft belongs to an older question version. Reload and review the current question before making changes.",
              );
          }}
          onSubmit={async (e) => {
            e.preventDefault();
            const requestId = request ?? crypto.randomUUID();
            setRequest(requestId);
            setBusy(true);
            setError("");
            setNotice("");
            try {
              const saved = await api<Snapshot>(base, "POST", {
                fields: changes,
                revision: record.revision,
                request_id: requestId,
              });
              setRecord({ ...record, ...saved });
              setChanges({});
              setRequest(null);
              setNotice("Question saved in your organisation.");
            } catch (e) {
              setError(
                e instanceof Error ? e.message : "Unable to save question.",
              );
              throw e;
            } finally {
              setBusy(false);
            }
          }}
        >
          <FormattedField
            label="Question"
            value={String(values.question ?? "")}
            disabled={busy}
            onChange={(v) => set("question", v)}
          />
          {record.type === "M" && (
            <fieldset>
              <legend>Options and correct answers</legend>
              {[1, 2, 3, 4, 5, 6].map((n) => (
                <div key={n}>
                  <FormattedField
                    label={`Option ${n}`}
                    value={String(values["option" + n] ?? "")}
                    disabled={busy}
                    onChange={(v) => set("option" + n, v)}
                  />
                  <label>
                    <input
                      type="checkbox"
                      checked={(values.correct_answers ?? []).includes(n)}
                      disabled={busy}
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
          {record.type === "T" && (
            <label>
              Correct answer
              <select
                value={values.true_false ?? ""}
                disabled={busy}
                onChange={(e) => set("true_false", e.target.value)}
              >
                <option value="">Choose answer</option>
                <option value="True">True</option>
                <option value="False">False</option>
              </select>
            </label>
          )}
          {record.type === "NAT" && (
            <fieldset>
              <legend>Numerical answer</legend>
              <label>
                Answer rule
                <select
                  value={values.nat_mode ?? "exact"}
                  disabled={busy}
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
                    disabled={busy}
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
          {["F", "B"].includes(record.type ?? "") && (
            <fieldset>
              <legend>Accepted answers for each blank</legend>
              {(values.fill_blank_answers ?? []).map(
                (blank: any, i: number) => (
                  <label key={i}>
                    Blank {i + 1} — separate alternatives with |
                    <input
                      value={blank.accepted_answers ?? ""}
                      disabled={busy}
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
                  busy || (values.fill_blank_answers ?? []).length >= 20
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
          {record.type === "S" && (
            <FormattedField
              label="Model answer"
              value={String(values.si_answer1 ?? "")}
              disabled={busy}
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
                  disabled={busy}
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
            value={String(values.hint ?? "")}
            disabled={busy}
            onChange={(v) => set("hint", v)}
          />
          <FormattedField
            label="Explanation"
            value={String(values.explanation ?? "")}
            disabled={busy}
            onChange={(v) => set("explanation", v)}
          />
          <button disabled={busy || Object.keys(changes).length === 0}>
            Save question
          </button>
          <button
            className="secondary"
            type="button"
            disabled={busy}
            onClick={() => setReload((v) => v + 1)}
          >
            Reload saved question
          </button>
        </DraftForm>
      )}
    </section>
  );
}
