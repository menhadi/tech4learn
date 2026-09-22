import { useEffect, useState } from "react";
import { api } from "./api";
import { SmartTable } from "./DirectoryTable";
import { ExamBuilder } from "./ExamBuilder";
import { ExamTaxonomy } from "./ExamTaxonomy";
import { ExamWorkspace } from "./ExamWorkspace";
import { ExamAiSettings } from "./ExamAiSettings";
import { ExamModuleControl, ExamQuestions } from "./ExamContent";

type Organisation = { id: string; name: string; enabled: boolean };
type Exam = { id: number; name: string };
type Student = { id: string; name: string; code: string; connected: boolean };
type Sharing = {
  enabled: boolean;
  exam_ids: number[];
  revision: number;
  students: Student[];
};
export function ExamElitePlatform() {
  const [status, setStatus] = useState<{
    connected: boolean;
    message?: string;
    organisations: Organisation[];
  } | null>(null);
  const [org, setOrg] = useState("");
  return (
    <section className="panel">
      <h2>Central exam service</h2>
      <p>
        Manage the shared exam catalogue and each organisation’s exam access.
        Student records and attendance stay in Tech4Learn.
      </p>
      <ConnectionStatus onStatus={setStatus} />
      {status?.connected && (
        <>
          <label>
            Organisation
            <select value={org} onChange={(e) => setOrg(e.target.value)}>
              <option value="">Choose an organisation</option>
              {status.organisations.map((o) => (
                <option key={o.id} value={o.id}>
                  {o.name}
                </option>
              ))}
            </select>
          </label>
          {org && (
            <div key={org}>
              <ExamAiSettings org={org} />
              <ExamModuleControl org={org} />
              <ExamWorkspace org={org} controls />
              <ExamQuestions org={org} central />
              <ExamTaxonomy org={org} central />
              <ExamBuilder org={org} central />
              <details>
                <summary>Legacy catalogue and student links</summary>
                <OrganisationSharing org={org} />
              </details>
            </div>
          )}
        </>
      )}
    </section>
  );
}
function ConnectionStatus({ onStatus }: { onStatus: (s: any) => void }) {
  const [text, setText] = useState("Checking connection…"),
    [busy, setBusy] = useState(false),
    [revision, setRevision] = useState(0);
  useEffect(() => {
    let active = true;
    setBusy(true);
    onStatus(null);
    api<any>("/platform/examelite")
      .then((s) => {
        if (active) {
          onStatus(s);
          setText(s.connected ? "Central exam service is connected." : s.message);
        }
      })
      .catch((e) => {
        if (active) setText(e.message);
      })
      .finally(() => {
        if (active) setBusy(false);
      });
    return () => {
      active = false;
    };
  }, [revision]);
  return (
    <>
      <p role="status">{text}</p>
      <button
        className="secondary"
        disabled={busy}
        onClick={() => setRevision((v) => v + 1)}
      >
        Check connection
      </button>
    </>
  );
}
function OrganisationSharing({ org }: { org: string }) {
  const base = `/platform/examelite/organisations/${org}`;
  const [sharing, setSharing] = useState<Sharing | null>(null),
    [exams, setExams] = useState<Exam[]>([]);
  const [selected, setSelected] = useState<number[]>([]),
    [enabled, setEnabled] = useState(false),
    [dirty, setDirty] = useState(false);
  const [search, setSearch] = useState(""),
    [studentSearch, setStudentSearch] = useState(""),
    [catalogSearch, setCatalogSearch] = useState("");
  const [next, setNext] = useState<number | null>(null),
    [busy, setBusy] = useState(false),
    [error, setError] = useState(""),
    [notice, setNotice] = useState("");
  const accept = (s: Sharing) => {
    setSharing(s);
    setSelected(s.exam_ids);
    setEnabled(s.enabled);
    setDirty(false);
  };
  useEffect(() => {
    let active = true;
    api<Sharing>(base)
      .then((s) => {
        if (active) accept(s);
      })
      .catch((e) => {
        if (active) setError(e.message);
      });
    return () => {
      active = false;
    };
  }, [base]);
  async function act(fn: () => Promise<void>) {
    setBusy(true);
    setError("");
    setNotice("");
    try {
      await fn();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Unable to complete request.");
    } finally {
      setBusy(false);
    }
  }
  async function catalogue(after = 0) {
    const term = after ? catalogSearch : search;
    const page = await api<{ items: Exam[]; next: number | null }>(
      `/platform/examelite/exams?after=${after}&search=${encodeURIComponent(term)}`,
    );
    setExams((old) => (after ? [...old, ...page.items] : page.items));
    setCatalogSearch(term);
    setNext(page.next);
  }
  return (
    <>
      {error && (
        <p role="alert" className="error">
          {error}
        </p>
      )}
      {notice && <p role="status">{notice}</p>}
      {!sharing ? (
        <p>Loading organisation…</p>
      ) : (
        <>
          <h3>Shared exams</h3>
          <label>
            <input
              type="checkbox"
              checked={enabled}
              disabled={busy}
              onChange={(e) => {
                setEnabled(e.target.checked);
                setDirty(true);
              }}
            />{" "}
            Enable exam access for this organisation
          </label>
          <form
            onSubmit={(e) => {
              e.preventDefault();
              void act(() => catalogue());
            }}
          >
            <label>
              Find an exam
              <input
                value={search}
                maxLength={150}
                onChange={(e) => setSearch(e.target.value)}
                disabled={busy}
              />
            </label>
            <button disabled={busy}>Search catalogue</button>
          </form>
          <SmartTable>
            <caption>Exam catalogue</caption>
            <thead>
              <tr>
                <th>Exam</th>
                <th>Share with this organisation</th>
              </tr>
            </thead>
            <tbody>
              {exams.map((exam) => (
                <tr key={exam.id}>
                  <td>{exam.name}</td>
                  <td>
                    <input
                      type="checkbox"
                      aria-label={`Share ${exam.name}`}
                      disabled={busy}
                      checked={selected.includes(exam.id)}
                      onChange={(e) => {
                        setSelected((ids) =>
                          e.target.checked
                            ? [...ids, exam.id]
                            : ids.filter((id) => id !== exam.id),
                        );
                        setDirty(true);
                      }}
                    />
                  </td>
                </tr>
              ))}
            </tbody>
          </SmartTable>
          {next !== null && (
            <button
              className="secondary"
              disabled={busy}
              onClick={() => void act(() => catalogue(next))}
            >
              Load next 50 exams
            </button>
          )}
          <p>
            {selected.length} exams selected. Search results do not clear your
            selections.
          </p>
          {selected.length > 0 && (
            <details>
              <summary>Review selected exams</summary>
              <ul>
                {selected.map((id) => (
                  <li key={id}>
                    {exams.find((e) => e.id === id)?.name ?? `Exam #${id}`}{" "}
                    <button
                      className="text-button"
                      disabled={busy}
                      onClick={() => {
                        setSelected((ids) => ids.filter((v) => v !== id));
                        setDirty(true);
                      }}
                    >
                      Remove
                    </button>
                  </li>
                ))}
              </ul>
            </details>
          )}
          <button
            disabled={busy || !dirty}
            onClick={() =>
              void act(async () => {
                accept(
                  await api<Sharing>(base, "POST", {
                    enabled,
                    exam_ids: selected,
                    revision: sharing.revision,
                  }),
                );
                setNotice("Exam sharing saved.");
              })
            }
          >
            Save sharing
          </button>
          <button
            className="secondary"
            disabled={busy}
            onClick={() =>
              void act(async () => accept(await api<Sharing>(base)))
            }
          >
            Reload saved settings
          </button>
          <h3>Connect Tech4Learn students</h3>
          <p>
            Connect creates an exam identity automatically or reuses a
            previously reviewed link. It does not send attendance records,
            photos or login emails. Existing external exam students are not
            imported.
          </p>
          <p>
            Exam sign-in and launch from Tech4Learn are a later step; creating
            the identity does not yet provide a student login.
          </p>
          <form
            onSubmit={(e) => {
              e.preventDefault();
              void act(async () => {
                const data = await api<Sharing>(
                  `${base}?search=${encodeURIComponent(studentSearch)}`,
                );
                setSharing((s) => (s ? { ...s, students: data.students } : s));
              });
            }}
          >
            <label>
              Find student by name or code
              <input
                maxLength={150}
                value={studentSearch}
                disabled={busy}
                onChange={(e) => setStudentSearch(e.target.value)}
              />
            </label>
            <button disabled={busy}>Find students</button>
          </form>
          <p>
            Showing up to 50 matching students. Use search to find another
            student.
          </p>
          <SmartTable>
            <caption>Students in this organisation</caption>
            <thead>
              <tr>
                <th>Student</th>
                <th>Code</th>
                <th>Exam connection</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              {sharing.students.map((s) => (
                <tr key={s.id}>
                  <td>{s.name}</td>
                  <td>{s.code}</td>
                  <td>{s.connected ? "Connected" : "Not connected"}</td>
                  <td>
                    <button
                      disabled={
                        busy || dirty || !sharing.enabled || s.connected
                      }
                      onClick={() =>
                        void act(async () => {
                          await api(`${base}/learners/${s.id}`, "POST", {});
                          const data = await api<Sharing>(
                            `${base}?search=${encodeURIComponent(studentSearch)}`,
                          );
                          setSharing((old) =>
                            old ? { ...old, students: data.students } : old,
                          );
                          setNotice(`${s.name} is connected to the central exam service.`);
                        })
                      }
                    >
                      {s.connected ? "Connected" : "Connect student"}
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </SmartTable>
          {dirty && <p>Save sharing before connecting students.</p>}
        </>
      )}
    </>
  );
}
