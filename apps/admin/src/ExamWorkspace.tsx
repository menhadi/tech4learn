import { useEffect, useState } from "react";
import { api } from "./api";
import { DraftForm } from "./DraftForm";

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
  const [search, setSearch] = useState(""),
    [student, setStudent] = useState("");
  const [students, setStudents] = useState<
    { id: string; name: string; code: string }[]
  >([]);
  const [link, setLink] = useState<{ url: string; expires: number } | null>(
    null,
  );
  useEffect(() => {
    let active = true;
    setRules(null);
    setLink(null);
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
  useEffect(() => {
    if (controls || resultsOnly || !rules || rules.restrictions.includes("taking")) return;
    let active = true;
    const timer = setTimeout(() => {
      api<typeof students>(
        base + "/students?search=" + encodeURIComponent(search),
      )
        .then((s) => {
          if (active) setStudents(s);
        })
        .catch((e) => {
          if (active) setError(e.message);
        });
    }, 250);
    return () => {
      active = false;
      clearTimeout(timer);
    };
  }, [base, search, controls, resultsOnly, rules]);
  useEffect(() => {
    if (!link) return;
    const timer = setTimeout(
      () => setLink(null),
      Math.max(0, link.expires - Date.now()),
    );
    return () => clearTimeout(timer);
  }, [link]);
  async function launch(feature: Feature) {
    setBusy(true);
    setError("");
    setLink(null);
    try {
      const r = await api<{ url: string; expiresIn: number }>(
        base + "/launch",
        "POST",
        { feature, learner: feature === "taking" ? student : undefined },
      );
      setLink({ url: r.url, expires: Date.now() + r.expiresIn * 1000 });
    } catch (e) {
      setError(e instanceof Error ? e.message : "Unable to open exams.");
    } finally {
      setBusy(false);
    }
  }
  return (
    <section className="panel">
      <h3>{controls ? "Organisation exam features" : resultsOnly ? "Results and marking" : "Exam workspace"}</h3>
      <p>
        {controls
          ? "All exam features are available by default. Select only the features you want to restrict."
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
                  checked={restrictions.includes(f)}
                  onChange={(e) =>
                    setRestrictions((old) =>
                      e.target.checked
                        ? [...old, f]
                        : old.filter((x) => x !== f),
                    )
                  }
                />{" "}
                Restrict {labels[f].toLowerCase()}
              </label>
            ))}
            <button disabled={busy}>Save feature restrictions</button>
          </DraftForm>
        ) : (
          <>
            <div className="actions">
              {((resultsOnly ? ["results"] : ["subjects", "questions", "exams", "results"]) as Feature[]).map(
                (f) => (
                  <button
                    key={f}
                    type="button"
                    disabled={busy || rules.restrictions.includes(f)}
                    onClick={() => void launch(f)}
                  >
                    {labels[f]}
                  </button>
                ),
              )}
            </div>
            {!resultsOnly && !rules.restrictions.includes("taking") && (
              <fieldset className="exam-student-access">
                <legend>Student exam access</legend>
                <label>
                  Find a student
                  <input
                    value={search}
                    onChange={(e) => {
                      setSearch(e.target.value);
                      setStudent("");
                      setLink(null);
                    }}
                    placeholder="Name or student code"
                  />
                </label>
                <label>
                  Student
                  <select
                    value={student}
                    onChange={(e) => {
                      setStudent(e.target.value);
                      setLink(null);
                    }}
                  >
                    <option value="">Choose a student</option>
                    {students.map((s) => (
                      <option key={s.id} value={s.id}>
                        {s.name} · {s.code}
                      </option>
                    ))}
                  </select>
                </label>
                <button
                  type="button"
                  disabled={busy || !student}
                  onClick={() => void launch("taking")}
                >
                  Create student exam link
                </button>
              </fieldset>
            )}
            {link && (
              <div role="status">
                <a
                  className="button"
                  href={link.url}
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  Open ExamElite
                </a>
                <p>This link can be used once and expires in two minutes.</p>
              </div>
            )}
          </>
        ))}
    </section>
  );
}
