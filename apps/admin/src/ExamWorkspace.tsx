import { useEffect, useState, lazy, Suspense } from "react";
import { api } from "./api";
import { DraftForm } from "./DraftForm";
import { DirectoryTable } from "./DirectoryTable";
import { ExamQuestions } from "./ExamContent";
import { ExamTaxonomy } from "./ExamTaxonomy";
const ExamProctorReview = lazy(() =>
  import("./ExamProctorReview").then((module) => ({
    default: module.ExamProctorReview,
  })),
);
import { ExamBuilder } from "./ExamBuilder";
const ExamResults = lazy(() =>
  import("./ExamResults").then((module) => ({ default: module.ExamResults })),
);

const ExamStudentLinks = lazy(() =>
  import("./ExamStudentLinks").then((module) => ({
    default: module.ExamStudentLinks,
  })),
);

const labels = {
  subjects: "Subjects, topics and sections",
  questions: "Question bank",
  exams: "Create and manage exams",
  taking: "Student exam access",
  results: "Results and marking",
};
type Feature = keyof typeof labels;
type Rules = { restrictions: Feature[]; revision: number };
const coverage: Record<Feature, [string, string]> = {
  subjects: [
    "Groups, categories, subjects, topics, sections and language enabling/labels",
    "Packages, language disabling and central language administration",
  ],
  questions: [
    "Owned question editing, formula preview and image controls",
    "Central-original editing and visual formula editor",
  ],
  exams: [
    "Exam settings, question selection, sections and timers",
    "OMR and PDF workflows",
  ],
  taking: [
    "Scoped student links, start, resume, answers and submission",
    "Student answer uploads and broader device verification",
  ],
  results: [
    "Result visibility, history and pending-answer marking",
    "Student media answers and broader report parity",
  ],
};
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
  const [notice, setNotice] = useState("");
  const [page, setPage] = useState("questions");
  const [restrictions, setRestrictions] = useState<Feature[]>([]);
  useEffect(() => {
    let active = true;
    setRules(null);
    setError("");
    setNotice("");
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
            <Suspense fallback={<p role="status">Loading results…</p>}>
              <ExamResults key={`results:${org}`} org={org} />
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
              {!rules.restrictions.includes("taking") &&
                !rules.restrictions.includes("exams") && (
                  <button
                    className={page === "taking" ? "" : "secondary"}
                    onClick={() => setPage("taking")}
                  >
                    Student exam access
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
            ) : page === "taking" &&
              !rules.restrictions.includes("taking") &&
              !rules.restrictions.includes("exams") ? (
              <Suspense fallback={<p role="status">Loading student access…</p>}>
                <ExamStudentLinks key={org} org={org} />
              </Suspense>
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
          ? "Choose which feature groups this organisation can use when Exams is enabled. The coverage table shows implemented tools and remaining work; plan assignment is not yet available."
          : "Use the ExamElite interface to manage subjects, questions, exams and results. Shared content opens as your organisation’s own editable version."}
      </p>
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      {!rules && !error && <p role="status">Loading exam access…</p>}
      {notice && <p role="status">{notice}</p>}
      {rules &&
        (controls ? (
          <DraftForm
            draftKey={`exam-features-${org}`}
            title="Exam feature restrictions"
            draftState={{ restrictions, revision: rules.revision }}
            restoreState={(v) => {
              if (
                v?.revision === rules.revision &&
                Array.isArray(v.restrictions)
              )
                setRestrictions(
                  v.restrictions.filter(
                    (f: unknown): f is Feature =>
                      typeof f === "string" && Object.hasOwn(labels, f),
                  ),
                );
              else
                setError(
                  "This draft belongs to an older access revision. Reload saved access before making changes.",
                );
            }}
            onSubmit={async (e) => {
              e.preventDefault();
              setBusy(true);
              setError("");
              setNotice("");
              try {
                const r = await api<Rules>(base + "/restrictions", "POST", {
                  restrictions,
                  revision: rules.revision,
                });
                setRules(r);
                setRestrictions(r.restrictions);
                setNotice("Exam feature access saved.");
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
              <label key={f} data-no-draft="true">
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
            <button
              type="button"
              disabled={busy}
              onClick={() => setRestrictions([])}
            >
              Allow all feature groups
            </button>
            <button
              type="button"
              disabled={busy}
              onClick={() => setRestrictions(Object.keys(labels) as Feature[])}
            >
              Restrict all feature groups
            </button>
            <button
              type="button"
              disabled={busy}
              onClick={async () => {
                setBusy(true);
                setError("");
                setNotice("");
                try {
                  const saved = await api<Rules>(base);
                  setRules(saved);
                  setRestrictions(saved.restrictions);
                  setNotice("Saved access reloaded.");
                } catch (cause) {
                  setError(
                    cause instanceof Error
                      ? cause.message
                      : "Unable to reload access.",
                  );
                } finally {
                  setBusy(false);
                }
              }}
            >
              Reload saved access
            </button>
          </DraftForm>
        ) : null)}
      {controls && rules && (
        <DirectoryTable
          title="Exam feature coverage"
          columns={[
            "Feature group",
            "Saved access",
            "Implemented tools",
            "Remaining work",
          ]}
        >
          {(Object.keys(labels) as Feature[]).map((feature) => (
            <tr key={feature}>
              <td>{labels[feature]}</td>
              <td>
                {rules.restrictions.includes(feature)
                  ? "Restricted"
                  : "Allowed when Exams is enabled"}
              </td>
              <td>{coverage[feature][0]}</td>
              <td>{coverage[feature][1]}</td>
            </tr>
          ))}
        </DirectoryTable>
      )}
    </section>
  );
}
