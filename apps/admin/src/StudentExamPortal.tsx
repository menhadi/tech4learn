import { useEffect, useState } from "react";
import { api } from "./api";

export type StudentEntry = { org: string; token: string; requested: boolean };
export function takeStudentEntry(): StudentEntry {
  const params = new URLSearchParams(location.hash.slice(1));
  const value = params.get("student-exam");
  const url = new URL(location.href);
  const requested = value !== null || url.searchParams.has("studentExam");
  const pieces = value?.split(".") ?? [];
  const org = pieces[0] || url.searchParams.get("studentExam") || "";
  const valid =
    /^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/.test(org);
  if (value !== null) {
    url.hash = "";
    if (valid) url.searchParams.set("studentExam", org);
    history.replaceState(null, "", url.pathname + url.search);
  }
  return {
    org: valid ? org : "",
    token:
      pieces.length === 2 && /^[a-f0-9]{64}$/.test(pieces[1]) ? pieces[1] : "",
    requested,
  };
}
type StudentSession = {
  organisation_id: string;
  organisation_name: string;
  student_name: string;
  exam_name: string;
  expires_at: string;
};
export function StudentExamPortal({ entry }: { entry: StudentEntry }) {
  const [secret, setSecret] = useState(entry.token),
    [session, setSession] = useState<StudentSession | null>(null),
    [busy, setBusy] = useState(false),
    [error, setError] = useState("");
  const base = `/organisations/${entry.org}/student-exam`;
  async function refresh() {
    setBusy(true);
    setError("");
    try {
      setSession(await api<StudentSession>(`${base}/me`));
    } catch (e) {
      setSession(null);
      setError(e instanceof Error ? e.message : "Unable to open exam access.");
    } finally {
      setBusy(false);
    }
  }
  useEffect(() => {
    if (entry.org && !entry.token) void refresh();
  }, []);
  return (
    <main
      className="panel"
      style={{ maxWidth: 760, margin: "32px auto", padding: 24 }}
    >
      <h1>{session?.organisation_name || "Student exams"}</h1>
      {!entry.org ? (
        <p role="alert" className="error">
          This exam link is invalid. Ask your organisation for a new link.
        </p>
      ) : (
        <>
          {error && (
            <p role="alert" className="error">
              {error}
            </p>
          )}
          {secret && !session && (
            <>
              <p>Use this one-time link to sign in to your assigned exam.</p>
              <button
                disabled={busy}
                onClick={async () => {
                  setBusy(true);
                  setError("");
                  try {
                    await api(`${base}/exchange`, "POST", { token: secret });
                    setSecret("");
                    await refresh();
                  } catch (e) {
                    setError(
                      e instanceof Error ? e.message : "Unable to sign in.",
                    );
                  } finally {
                    setBusy(false);
                  }
                }}
              >
                Sign in to assigned exam
              </button>
            </>
          )}
          {!secret && !session && (
            <button
              className="secondary"
              disabled={busy}
              onClick={() => void refresh()}
            >
              Check exam access
            </button>
          )}
          {session && (
            <>
              <h2>{session.exam_name}</h2>
              <p>Student: {session.student_name}</p>
              <p>
                Access expires: {new Date(session.expires_at).toLocaleString()}
              </p>
              <p role="status">
                Exam delivery is not yet available in this build. Your
                organisation will provide the completed exam screen when it is
                ready.
              </p>
              <button
                className="secondary"
                disabled={busy}
                onClick={async () => {
                  setBusy(true);
                  setError("");
                  try {
                    await api(`${base}/logout`, "POST", {});
                    setSession(null);
                    setSecret("");
                    setError(
                      "Signed out. Ask your organisation for a new link to sign in again.",
                    );
                  } catch (e) {
                    setError(
                      e instanceof Error ? e.message : "Unable to sign out.",
                    );
                  } finally {
                    setBusy(false);
                  }
                }}
              >
                Sign out of exam
              </button>
            </>
          )}
        </>
      )}
    </main>
  );
}
