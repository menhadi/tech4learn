import { useEffect, useState } from "react";
import { api, apiBase } from "./api";
import { DirectoryTable } from "./DirectoryTable";

import { ExamLearnerPicker } from "./ExamLearnerPicker";

type Learner = { id: string; name: string; code: string };
type Attempt = {
  attempt_id: number;
  exam_name: string;
  started_at: string | null;
  finished_at: string | null;
};
type Capture = { capture_id: string; received_at: string; expires_at: string };
const date = (value: string | null) =>
  value ? new Date(value).toLocaleString() : "—";

export function ExamProctorReview({ org }: { org: string }) {
  const [learner, setLearner] = useState<Learner | null>(null);
  return (
    <section className="panel">
      <h3>Exam camera review</h3>
      <p>
        Choose a student, then an attempt to review its private camera images.
        Images are available for 30 days. Opening an image is recorded in the
        audit history.
      </p>
      {learner ? (
        <>
          <p>
            Student: <strong>{learner.name}</strong> · {learner.code}
          </p>
          <button className="secondary" onClick={() => setLearner(null)}>
            Choose another student
          </button>
          <LearnerEvidence
            key={`${org}:${learner.id}`}
            org={org}
            learner={learner.id}
          />
        </>
      ) : (
        <ExamLearnerPicker
          org={org}
          onSelect={setLearner}
          title="Choose a student for camera review"
          action="Review attempts"
        />
      )}
    </section>
  );
}

function LearnerEvidence({ org, learner }: { org: string; learner: string }) {
  const base = `/organisations/${org}/exam-proctor/${learner}/attempts`;
  const [rows, setRows] = useState<Attempt[]>([]);
  const [next, setNext] = useState<number | null>(null);
  const [attempt, setAttempt] = useState<Attempt | null>(null);
  const [page, setPage] = useState({ after: 0, revision: 0 });
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);
  useEffect(() => {
    let active = true;
    setLoading(true);
    setError("");
    void api<{ items: Attempt[]; next: number | null }>(
      `${base}?after=${page.after}`,
    )
      .then((result) => {
        if (active) {
          setRows((old) =>
            page.after ? [...old, ...result.items] : result.items,
          );
          setNext(result.next);
        }
      })
      .catch((cause) => {
        if (active) {
          setRows([]);
          setNext(null);
          setAttempt(null);
          setError(cause.message);
        }
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
    };
  }, [base, page]);
  return (
    <>
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      <button
        className="secondary"
        disabled={loading}
        onClick={() => {
          setAttempt(null);
          setPage({ after: 0, revision: page.revision + 1 });
        }}
      >
        Refresh attempts
      </button>
      {loading && <p role="status">Loading camera evidence…</p>}
      {!loading && !error && !rows.length && (
        <p>No retained camera images were found for this student.</p>
      )}
      {!!rows.length && (
        <DirectoryTable
          title="Attempts with retained camera images"
          columns={["Exam", "Started", "Finished", "Actions"]}
        >
          {rows.map((row) => (
            <tr key={row.attempt_id}>
              <td>{row.exam_name}</td>
              <td>{date(row.started_at)}</td>
              <td>{date(row.finished_at)}</td>
              <td>
                <button disabled={loading} onClick={() => setAttempt(row)}>
                  View camera images
                </button>
              </td>
            </tr>
          ))}
        </DirectoryTable>
      )}
      {next !== null && (
        <button
          className="secondary"
          disabled={loading}
          onClick={() => setPage({ after: next, revision: page.revision + 1 })}
        >
          Load more attempts
        </button>
      )}
      {attempt && (
        <AttemptEvidence
          key={attempt.attempt_id}
          base={`${base}/${attempt.attempt_id}/captures`}
          name={attempt.exam_name}
        />
      )}
    </>
  );
}

function AttemptEvidence({ base, name }: { base: string; name: string }) {
  const [rows, setRows] = useState<Capture[]>([]);
  const [capture, setCapture] = useState<Capture | null>(null);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);
  const [revision, setRevision] = useState(0);
  useEffect(() => {
    let active = true;
    setLoading(true);
    setError("");
    setCapture(null);
    void api<{ items: Capture[] }>(base)
      .then((result) => {
        if (active) setRows(result.items);
      })
      .catch((cause) => {
        if (active) {
          setRows([]);
          setError(cause.message);
        }
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
    };
  }, [base, revision]);
  return (
    <section aria-label="Attempt camera evidence">
      <h4>{name}</h4>
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      <button
        className="secondary"
        disabled={loading}
        onClick={() => setRevision((r) => r + 1)}
      >
        Refresh camera images
      </button>
      {loading && <p role="status">Loading image list…</p>}
      {!loading && !error && !rows.length && (
        <p>No unexpired camera images remain in this attempt.</p>
      )}
      {!!rows.length && (
        <DirectoryTable
          title="Retained camera images"
          columns={["Received", "Expires", "Actions"]}
        >
          {rows.map((row) => (
            <tr key={row.capture_id}>
              <td>{date(row.received_at)}</td>
              <td>{date(row.expires_at)}</td>
              <td>
                <button disabled={loading} onClick={() => setCapture(row)}>
                  Open private image
                </button>
              </td>
            </tr>
          ))}
        </DirectoryTable>
      )}
      {capture && (
        <PrivateCapture
          key={`${base}:${capture.capture_id}`}
          source={`${apiBase}${base}/${capture.capture_id}`}
          capture={capture}
          close={() => setCapture(null)}
        />
      )}
    </section>
  );
}

function PrivateCapture({
  source,
  capture,
  close,
}: {
  source: string;
  capture: Capture;
  close: () => void;
}) {
  const [error, setError] = useState(false);
  const [expired, setExpired] = useState(
    Date.parse(capture.expires_at) <= Date.now(),
  );
  useEffect(() => {
    let timer: ReturnType<typeof setTimeout>;
    const check = () => {
      const remaining = Date.parse(capture.expires_at) - Date.now();
      if (remaining <= 0) setExpired(true);
      else timer = setTimeout(check, Math.min(remaining, 2147483647));
    };
    check();
    return () => clearTimeout(timer);
  }, [capture.expires_at]);
  return (
    <figure>
      <figcaption>Camera image received {date(capture.received_at)}</figcaption>
      {expired ? (
        <p role="status">This image has expired.</p>
      ) : error ? (
        <p className="error" role="alert">
          Image unavailable. It may have expired or access may have changed.
          Close it and refresh the list.
        </p>
      ) : (
        <img
          src={source}
          alt="Private exam camera capture"
          onError={() => setError(true)}
          style={{ maxWidth: "100%", width: 640 }}
        />
      )}
      <button className="secondary" onClick={close}>
        Close image
      </button>
    </figure>
  );
}
