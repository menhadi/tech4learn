import { useEffect, useState } from "react";
import { api } from "./api";
import { SmartTable } from "./DirectoryTable";
type Student = { id: string; name: string; code: string };
type Row = {
  id: number;
  name?: string;
  duration?: number;
  exam_name?: string;
  percent?: number;
  result?: string;
  end_time?: string;
};
export function ExamElite({
  org,
  results = false,
}: {
  org: string;
  results?: boolean;
}) {
  const [connection, setConnection] = useState<{
    connected: boolean;
    message?: string;
    linkedLearners?: Student[];
  } | null>(null);
  const [learner, setLearner] = useState(""),
    [rows, setRows] = useState<Row[]>([]),
    [next, setNext] = useState<number | null>(null);
  const [error, setError] = useState(""),
    [busy, setBusy] = useState(false),
    [revision, setRevision] = useState(0);
  const base = `/organisations/${org}/examelite`;
  useEffect(() => {
    let active = true;
    setConnection(null);
    setRows([]);
    setNext(null);
    setLearner("");
    setError("");
    api<any>(base + "/status")
      .then((c) => {
        if (active) setConnection(c);
      })
      .catch((e) => {
        if (active) setError(e.message);
      });
    return () => {
      active = false;
    };
  }, [base, revision]);
  useEffect(() => {
    setRows([]);
    setNext(null);
    setError("");
  }, [learner, results]);
  async function load(after = 0) {
    setBusy(true);
    setError("");
    try {
      const page = await api<{ items: Row[]; next: number | null }>(
        `${base}/${results ? `learners/${learner}/results` : "exams"}?after=${after}`,
      );
      setRows((old) => (after ? [...old, ...page.items] : page.items));
      setNext(page.next);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Unable to load ExamElite.");
    } finally {
      setBusy(false);
    }
  }
  return (
    <section className="panel">
      <h3>{results ? "ExamElite results" : "ExamElite exams"}</h3>
      <p>
        Read shared exams and linked student results from ExamElite. Exam
        creation, delivery and marking continue in ExamElite.
      </p>
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      {!connection && !error && <p role="status">Checking connection…</p>}
      {connection && (
        <p role="status">
          {connection.connected
            ? "Connected to ExamElite."
            : connection.message}
        </p>
      )}
      <button
        type="button"
        className="secondary"
        disabled={busy}
        onClick={() => setRevision((v) => v + 1)}
      >
        Check connection
      </button>
      {connection?.connected && (
        <>
          {results && (
            <label>
              Student
              <select
                disabled={busy}
                value={learner}
                onChange={(e) => setLearner(e.target.value)}
              >
                <option value="">Choose a linked student</option>
                {connection.linkedLearners?.map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.name} · {s.code}
                  </option>
                ))}
              </select>
            </label>
          )}
          {results && !connection.linkedLearners?.length && (
            <p>
              No accessible students are linked yet. Ask the platform
              administrator to configure the student mapping.
            </p>
          )}
          <button
            type="button"
            disabled={busy || (results && !learner)}
            onClick={() => void load()}
          >
            {busy ? "Loading…" : results ? "Load results" : "Load shared exams"}
          </button>
          <SmartTable>
            <caption>
              {results ? "Result summaries" : "Shared exam catalogue"} —{" "}
              {rows.length} loaded
            </caption>
            <thead>
              <tr>
                <th>{results ? "Exam" : "Name"}</th>
                <th>{results ? "Score (%)" : "Duration (minutes)"}</th>
                {results && <th>Result</th>}
                {results && <th>Finished</th>}
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.id}>
                  <th scope="row">{results ? r.exam_name : r.name}</th>
                  <td>{results ? r.percent : r.duration}</td>
                  {results && <td>{r.result}</td>}
                  {results && <td>{r.end_time}</td>}
                </tr>
              ))}
            </tbody>
          </SmartTable>
          {next !== null && (
            <button
              type="button"
              disabled={busy}
              onClick={() => void load(next)}
            >
              Load next 50
            </button>
          )}
          <p>
            Refresh the list to read corrections made in ExamElite. These are
            live summaries, not a synchronised local results archive.
          </p>
        </>
      )}
    </section>
  );
}
