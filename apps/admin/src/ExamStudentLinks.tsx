import { useEffect, useState } from "react";
import { api } from "./api";
import { DraftForm } from "./DraftForm";
import { DirectoryTable } from "./DirectoryTable";
import { ExamLearnerPicker, type ExamLearner } from "./ExamLearnerPicker";
import type { AcademicGroup } from "./AcademicStructure";

type Grant = {
  id: string;
  exam_name: string;
  expires_at: string;
  consumed_at: string | null;
  revoked_at: string | null;
};
export function ExamStudentLinks({ org, groups = [] }: { org: string; groups?: AcademicGroup[] }) {
  const [learner, setLearner] = useState<ExamLearner | null>(null);
  const [busy, setBusy] = useState(false);
  return (
    <section className="panel">
      <h3>Student exam access</h3>
      <p>
        Create a private sign-in link for one student and one organisation exam.
        Students stay on this domain and receive no staff or attendance access.
      </p>
      {learner ? (
        <>
          <p>
            Student: <strong>{learner.name}</strong> · {learner.code}
          </p>
          <button
            className="secondary"
            disabled={busy}
            onClick={() => setLearner(null)}
          >
            Choose another student
          </button>
          <StudentLinks
            key={`${org}:${learner.id}`}
            org={org}
            learner={learner.id}
            onBusy={setBusy}
          />
        </>
      ) : (
        <ExamLearnerPicker org={org} groups={groups} onSelect={setLearner} />
      )}
    </section>
  );
}
function StudentLinks({
  org,
  learner,
  onBusy,
}: {
  org: string;
  learner: string;
  onBusy: (busy: boolean) => void;
}) {
  const base = `/organisations/${org}/exam-student-access`;
  const [exam, setExam] = useState("");
  const [hours, setHours] = useState(24);
  const [choices, setChoices] = useState<{ id: number; label: string }[]>([]);
  const [lookup, setLookup] = useState({ search: "", after: 0 });
  const [next, setNext] = useState<number | null>(null);
  const [choiceLoading, setChoiceLoading] = useState(true);
  const [choiceError, setChoiceError] = useState("");
  const [grants, setGrants] = useState<Grant[]>([]);
  const [revision, setRevision] = useState(0);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [issued, setIssued] = useState<{
    id: string;
    expires_at: string;
    url: string;
  } | null>(null);
  useEffect(() => {
    onBusy(busy);
    return () => onBusy(false);
  }, [busy, onBusy]);
  useEffect(() => {
    setIssued(null);
    setNotice("");
  }, [exam]);
  useEffect(() => {
    let active = true;
    setChoiceLoading(true);
    setChoiceError("");
    if (!lookup.after) setChoices([]);
    setNext(null);
    const timer = setTimeout(() => {
      void api<{ items: { id: number; label: string }[]; next: number | null }>(
        `/organisations/${org}/exam-content/choices/exams?search=${encodeURIComponent(lookup.search)}&after=${lookup.after}`,
      )
        .then((page) => {
          if (active) {
            setChoices((old) =>
              [...new Map((lookup.after ? [...old, ...page.items] : page.items).map(item => [item.id, item])).values()],
            );
            setNext(page.next);
          }
        })
        .catch((cause) => {
          if (active) setChoiceError(cause.message);
        })
        .finally(() => {
          if (active) setChoiceLoading(false);
        });
    }, 200);
    return () => {
      active = false;
      clearTimeout(timer);
    };
  }, [org, lookup]);
  useEffect(() => {
    let active = true;
    setLoading(true);
    setError("");
    void api<Grant[]>(`${base}?learner=${learner}`)
      .then((rows) => {
        if (active) setGrants(rows);
      })
      .catch((cause) => {
        if (active) {
          setGrants([]);
          setError(cause.message);
        }
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
    };
  }, [base, learner, revision]);
  async function revoke(id: string) {
    setBusy(true);
    setError("");
    try {
      await api(`${base}/${id}/revoke`, "POST", {});
      if (issued?.id === id) setIssued(null);
      setNotice(
        "Exam access revoked. Its sign-in link and sessions no longer work.",
      );
      setRevision((r) => r + 1);
    } catch (cause) {
      setError(
        cause instanceof Error
          ? cause.message
          : "Could not revoke exam access.",
      );
    } finally {
      setBusy(false);
    }
  }
  return (
    <>
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      {choiceError && (
        <div role="alert"><p className="error">{choiceError}</p><button type="button" className="secondary" disabled={busy || choiceLoading} onClick={() => setLookup(current => ({...current}))}>Retry exam search</button></div>
      )}
      {notice && <p role="status">{notice}</p>}
      <label>
        Find organisation exam
        <input
          type="search"
          value={lookup.search}
          disabled={busy}
          onChange={(e) => setLookup({ search: e.target.value, after: 0 })}
        />
      </label>
      {choiceLoading && <p role="status">Loading organisation exams…</p>}
      {!choiceLoading && !choiceError && !choices.length && <p role="status">No organisation exams match. Change the search or create an exam first.</p>}
      {next !== null && (
        <button
          className="secondary"
          disabled={busy || choiceLoading}
          onClick={() => setLookup({ ...lookup, after: next })}
        >
          Load more exams
        </button>
      )}
      <DraftForm
        draftKey={`exam-link-${learner}`}
        title="Issue student exam link"
        draftState={{ exam, hours }}
        restoreState={(state) => {
          if (
            typeof state?.exam === "string" &&
            /^[1-9][0-9]{0,14}$/.test(state.exam)
          )
            setExam(state.exam);
          if (
            Number.isInteger(state?.hours) &&
            state.hours >= 1 &&
            state.hours <= 168
          )
            setHours(state.hours);
        }}
        onSubmit={async (event) => {
          event.preventDefault();
          if (!exam || choiceLoading || choiceError) {
            setError("Load organisation exams successfully and choose an exam before creating access.");
            return;
          }
          setBusy(true);
          setIssued(null);
          setError("");
          setNotice("");
          try {
            const result = await api<{
              id: string;
              expires_at: string;
              fragment: string;
            }>(base, "POST", { learner_id: learner, exam_id: exam, hours });
            if (
              !new RegExp(`^student-exam=${org}\\.[a-f0-9]{64}$`).test(
                result.fragment,
              )
            )
              throw new Error(
                "The link could not be displayed. Refresh access history and issue a replacement.",
              );
            setIssued({
              id: result.id,
              expires_at: result.expires_at,
              url: `${window.location.origin}/#${result.fragment}`,
            });
            setNotice(
              "Link created. Copy it now; it cannot be retrieved later.",
            );
            setRevision((r) => r + 1);
          } catch (cause) {
            setError(
              cause instanceof Error
                ? cause.message
                : "Link creation was not confirmed. A replacement will revoke earlier access for this paper.",
            );
          } finally {
            setBusy(false);
          }
        }}
      >
        <fieldset disabled={busy}>
          <legend>Exam and access duration</legend>
          <label>
            Organisation exam
            <select
              required
              value={exam}
              disabled={choiceLoading || !!choiceError}
              onChange={(e) => setExam(e.target.value)}
            >
              <option value="">Choose an exam</option>
              {exam &&
                !choices.some((choice) => String(choice.id) === exam) && (
                  <option value={exam}>Selected exam #{exam}</option>
                )}
              {choices.map((choice) => (
                <option key={choice.id} value={choice.id}>
                  {choice.label}
                </option>
              ))}
            </select>
          </label>
          <label>
            Access duration (hours)
            <input
              type="number"
              min={1}
              max={168}
              required
              value={hours}
              onChange={(e) => setHours(Number(e.target.value))}
            />
          </label>
          <p>
            Make sure the exam is active and configured for online delivery.
            Creating a link replaces previous access for this student and paper,
            including any signed-in session. Access duration does not change the
            exam timer.
          </p>
          <button type="submit" disabled={!exam || choiceLoading || !!choiceError}>
            Create link and replace previous access
          </button>
        </fieldset>
      </DraftForm>
      {issued && (
        <section aria-label="New student exam link" data-no-draft="true">
          <p>
            Expires: {new Date(issued.expires_at).toLocaleString()}. Share only
            with this student. Do not open it in the staff browser.
          </p>
          <label>
            Private sign-in link
            <input
              readOnly
              value={issued.url}
              onFocus={(e) => e.target.select()}
            />
          </label>
          <button
            onClick={async () => {
              try {
                await navigator.clipboard.writeText(issued.url);
                setNotice("Link copied.");
              } catch {
                setNotice("Select the link text and copy it manually.");
              }
            }}
          >
            Copy student link
          </button>
          <button className="secondary" onClick={() => setIssued(null)}>
            Hide link
          </button>
        </section>
      )}
      <button
        className="secondary"
        disabled={busy || loading}
        onClick={() => setRevision((r) => r + 1)}
      >
        Refresh access history
      </button>
      {loading && <p role="status">Loading access history…</p>}
      <DirectoryTable
        title="Latest 50 exam access records"
        columns={["Exam", "Expires", "Status", "Actions"]}
      >
        {grants.map((grant) => (
          <tr key={grant.id}>
            <td>{grant.exam_name}</td>
            <td>{new Date(grant.expires_at).toLocaleString()}</td>
            <td>
              {grant.revoked_at
                ? "Revoked"
                : Date.parse(grant.expires_at) <= Date.now()
                  ? "Expired"
                  : grant.consumed_at
                    ? "Sign-in used"
                    : "Awaiting sign-in"}
            </td>
            <td>
              <button
                disabled={busy || loading || !!grant.revoked_at || Date.parse(grant.expires_at) <= Date.now()}
                onClick={() => void revoke(grant.id)}
              >
                Revoke access
              </button>
            </td>
          </tr>
        ))}
      </DirectoryTable>
    </>
  );
}
