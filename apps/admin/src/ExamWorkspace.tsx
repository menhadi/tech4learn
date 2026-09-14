import { useEffect, useState, lazy, Suspense } from "react";
import { api } from "./api";
import { DraftForm } from "./DraftForm";
import { ExamQuestions } from "./ExamContent";
import { ExamTaxonomy } from "./ExamTaxonomy";
const ExamProctorReview = lazy(() =>
  import("./ExamProctorReview").then((module) => ({
    default: module.ExamProctorReview,
  })),
);
import { ExamBuilder } from "./ExamBuilder";

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
  const [page, setPage] = useState("questions");
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
  if (!controls && resultsOnly)
    return (
      <section>
        {error && (
          <p className="error" role="alert">
            {error}
          </p>
        )}
        {!rules && !error && <p role="status">Loading exam access…</p>}
        {rules &&
          (rules.restrictions.includes("results") ? (
            <p>
              Results and camera review are restricted for this organisation.
            </p>
          ) : (
            <Suspense fallback={<p role="status">Loading camera review…</p>}>
              <ExamProctorReview key={org} org={org} />
            </Suspense>
          ))}
      </section>
    );
  if (!controls)
    return resultsOnly ? null : (
      <section>
        {error && (
          <p className="error" role="alert">
            {error}
          </p>
        )}
        {!rules && !error && <p role="status">Loading exam access�</p>}
        {rules && (
          <>
            <nav aria-label="Exam tools">
              {!rules.restrictions.includes("questions") && (
                <button
                  className={page === "questions" ? "" : "secondary"}
                  onClick={() => setPage("questions")}
                >
                  Question bank
                </button>
              )}
              {!rules.restrictions.includes("subjects") && (
                <button
                  className={page === "subjects" ? "" : "secondary"}
                  onClick={() => setPage("subjects")}
                >
                  Subjects, topics and sections
                </button>
              )}
              {!rules.restrictions.includes("exams") && (
                <button
                  className={page === "exams" ? "" : "secondary"}
                  onClick={() => setPage("exams")}
                >
                  Create and manage exams
                </button>
              )}
            </nav>
            {page === "questions" &&
            !rules.restrictions.includes("questions") ? (
              <ExamQuestions org={org} />
            ) : page === "subjects" &&
              !rules.restrictions.includes("subjects") ? (
              <ExamTaxonomy org={org} />
            ) : page === "exams" && !rules.restrictions.includes("exams") ? (
              <ExamBuilder org={org} />
            ) : (
              <p>Select an available exam tool.</p>
            )}
          </>
        )}
      </section>
    );
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
