import { useEffect, useState } from "react";
import { api, apiBase } from "./api";
import { ExamRichContent } from "./ExamRichContent";

type Preview = {
  id: number;
  revision: string;
  type_name?: string;
  type?: string;
  fields: Record<string, unknown>;
  preview_fields: Record<string, string>;
};
const wording: Record<string, string> = {
  question: "Question",
  option1: "Option 1",
  option2: "Option 2",
  option3: "Option 3",
  option4: "Option 4",
  option5: "Option 5",
  option6: "Option 6",
  hint: "Hint",
  explanation: "Explanation",
  si_answer1: "Model answer",
};
const answers: Record<string, string> = {
  marks: "Marks",
  negative_marks: "Negative marks",
  correct_answers: "Correct options",
  true_false: "True / false answer",
  fill_blank_answers: "Accepted blank answers",
  nat_mode: "Numerical answer mode",
  nat_value: "Numerical answer",
  nat_min: "Minimum answer",
  nat_max: "Maximum answer",
  nat_tolerance: "Answer tolerance",
  answer: "Answer",
};
export function ExamCentralQuestionPreview({
  org,
  id,
  onClose,
}: {
  org: string;
  id: number;
  onClose: () => void;
}) {
  const [record, setRecord] = useState<Preview | null>(null);
  const [error, setError] = useState("");
  const [reload, setReload] = useState(0);
  const base = `/platform/exam-content/${org}/central/questions/${id}`;
  useEffect(() => {
    let active = true;
    setRecord(null);
    setError("");
    api<Preview>(base)
      .then((value) => {
        if (active) setRecord(value);
      })
      .catch((error) => {
        if (active) setError(error.message);
      });
    return () => {
      active = false;
    };
  }, [base, reload]);
  return (
    <section className="panel">
      <h3>Central question {id}</h3>
      <p>
        Review the shared original before copying it to an organisation. Copies
        retain their own edits.
      </p>
      <button className="secondary" onClick={onClose}>
        Back to question sharing
      </button>{" "}
      <button
        className="secondary"
        onClick={() => setReload((value) => value + 1)}
      >
        Reload preview
      </button>
      {error && (
        <p role="alert" className="error">
          {error}
        </p>
      )}
      {!record && !error && <p role="status">Loading central question…</p>}
      {record && (
        <>
          <p>{record.type_name}</p>
          {Object.entries(wording).map(([field, label]) =>
            record.preview_fields[field] ? (
              <section key={field}>
                <h4>{label}</h4>
                <ExamRichContent
                  value={record.preview_fields[field]}
                  mediaBase={`${apiBase}${base}/${record.revision}/media`}
                />
              </section>
            ) : null,
          )}
          <dl>
            {Object.entries(answers).map(([field, label]) => {
              const value = record.fields[field];
              if (
                value === null ||
                value === undefined ||
                value === "" ||
                (Array.isArray(value) && !value.length)
              )
                return null;
              if (field.startsWith("nat_") && record.type !== "NAT")
                return null;
              return (
                <div key={field}>
                  <dt>{label}</dt>
                  <dd>
                    {typeof value === "object"
                      ? JSON.stringify(value)
                      : String(value)}
                  </dd>
                </div>
              );
            })}
          </dl>
        </>
      )}
    </section>
  );
}
