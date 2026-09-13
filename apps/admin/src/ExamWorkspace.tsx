import { useEffect, useState } from "react";
import { api } from "./api";
import { DraftForm } from "./DraftForm";
import { ExamQuestions } from "./ExamContent";

const labels = {
  subjects: "Subjects, topics and sections",
  questions: "Question bank",
  exams: "Create and manage exams",
  taking: "Student exam access",
  results: "Results and marking",
};
type Feature = keyof typeof labels;
type Rules = { restrictions: Feature[]; revision: number };
export function ExamWorkspace({
  org,
  controls = false,
  resultsOnly = false,
}: {
  org: string;
  controls?: boolean;
  resultsOnly?: boolean;
}) {
  const base = `/organisations/${org}/exam-workspace`;
  const [rules, setRules] = useState<Rules | null>(null),
    [error, setError] = useState(""),
    [busy, setBusy] = useState(false);
  const [restrictions, setRestrictions] = useState<Feature[]>([]);
  useEffect(() => {
    let active = true;
    setRules(null);
    setError("");
    api<Rules>(base)
      .then((r) => {
        if (active) {
          setRules(r);
          setRestrictions(r.restrictions);
        }
      })
      .catch((e) => {
        if (active) setError(e.message);
      });
    return () => {
      active = false;
    };
  }, [base]);
  if (!controls) return resultsOnly ? null : <ExamQuestions org={org} />;
  return (
    <section className="panel">
      <h3>
        {controls
          ? "Organisation exam features"
          : resultsOnly
            ? "Results and marking"
            : "Exam workspace"}
      </h3>
      <p>
        {controls
          ? "Choose which capabilities this organisation can use when Exams is enabled. Internal authoring and exam-taking screens are still being integrated."
          : "Use the ExamElite interface to manage subjects, questions, exams and results. Shared content opens as your organisation’s own editable version."}
      </p>
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      {!rules && !error && <p role="status">Loading exam access…</p>}
      {rules &&
        (controls ? (
          <DraftForm
            draftKey={`exam-features-${org}`}
            title="Exam feature restrictions"
            draftState={restrictions}
            restoreState={(v) => {
              if (Array.isArray(v))
                setRestrictions(
                  v.filter(
                    (f) => typeof f === "string" && Object.hasOwn(labels, f),
                  ),
                );
            }}
            onSubmit={async (e) => {
              e.preventDefault();
              setBusy(true);
              setError("");
              try {
                const r = await api<Rules>(base + "/restrictions", "POST", {
                  restrictions,
                  revision: rules.revision,
                });
                setRules(r);
                setRestrictions(r.restrictions);
              } catch (e) {
                setError(
                  e instanceof Error
                    ? e.message
                    : "Unable to save restrictions.",
                );
                throw e;
              } finally {
                setBusy(false);
              }
            }}
          >
            {(Object.keys(labels) as Feature[]).map((f) => (
              <label key={f}>
                <input
                  type="checkbox"
                  checked={!restrictions.includes(f)}
                  disabled={busy}
                  onChange={(e) =>
                    setRestrictions((old) =>
                      e.target.checked
                        ? old.filter((x) => x !== f)
                        : [...old, f],
                    )
                  }
                />{" "}
                Allow {labels[f].toLowerCase()}
              </label>
            ))}
            <button disabled={busy}>Save feature restrictions</button>
          </DraftForm>
        ) : null)}
    </section>
  );
}
