import { SmartTable } from "./DirectoryTable";
import { useEffect, useState } from "react";
import { api, apiBase } from "./api";
type Face = {
  box: { x_min: number; y_min: number; x_max: number; y_max: number };
  learnerId: string | null;
  similarity: number;
};
type Job = {
  id: string;
  status: string;
  completed: number;
  total: number;
  error?: string;
  result?: {
    photos: { photoId: string; faces: Face[] }[];
    students: {
      id: string;
      name: string;
      code: string;
      status: string;
      photoIds: string[];
    }[];
  } | null;
};
export function FaceMatching({
  org,
  id,
  onSuggestions,
}: {
  org: string;
  id: string;
  roster: { id: string; name: string }[];
  onSuggestions: (v: Record<string, string>) => void;
}) {
  const [job, setJob] = useState<Job | null>(null),
    [busy, setBusy] = useState(false),
    [error, setError] = useState(""),
    [photoIndex, setPhotoIndex] = useState(0),
    [size, setSize] = useState({ w: 1, h: 1 });
  const base = `/organisations/${org}/attendance/${id}`;
  useEffect(() => {
    let active = true;
    api<Job | null>(base + "/face-jobs")
      .then((j) => {
        if (active) setJob(j);
      })
      .catch((e) => {
        if (active) setError(e.message);
      });
    return () => {
      active = false;
    };
  }, [base]);
  useEffect(() => {
    if (!job || !["queued", "processing"].includes(job.status)) return;
    let active = true;
    const timer = setTimeout(() => {
      api<Job>(base + "/face-jobs/" + job.id)
        .then((j) => {
          if (active) setJob(j);
        })
        .catch((e) => {
          if (active) {
            setError(e.message);
            setJob(null);
          }
        });
    }, 2000);
    return () => {
      active = false;
      clearTimeout(timer);
    };
  }, [base, job]);
  const running = !!job && ["queued", "processing"].includes(job.status),
    result = job?.status === "completed" ? job.result : null;
  const photo = result?.photos[photoIndex];
  const students = result?.students || [];
  return (
    <section className="subpanel">
      <h4>Compare all class photos</h4>
      <p>
        Up to 50 students and five photos in one session. Matching runs one
        comparison at a time on the server. You can leave this page and return
        to check progress.
      </p>
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      {job?.error && (
        <p className="error" role="alert">
          {job.error}
        </p>
      )}
      <button
        disabled={busy || running}
        onClick={() => {
          setBusy(true);
          setError("");
          setJob(null);
          setPhotoIndex(0);
          void api<Job>(base + "/face-match", "POST", {})
            .then(setJob)
            .catch((e) => setError(e.message))
            .finally(() => setBusy(false));
        }}
      >
        {busy
          ? "Adding to queue…"
          : running
            ? "Matching in progress…"
            : job
              ? "Run comparison again"
              : "Compare class photos"}
      </button>
      {running && (
        <div role="status">
          <p>
            {job.status === "queued"
              ? "Waiting for the face service"
              : "Comparing photos"}{" "}
            · {job.completed} / {job.total} comparisons
          </p>
          <progress max={job.total || 1} value={job.completed} />
          <p>Attendance stays unchanged until a teacher confirms it.</p>
        </div>
      )}
      {result && (
        <>
          <p role="status">
            {students.filter((s) => s.status === "suggested_present").length}{" "}
            suggested present ·{" "}
            {students.filter((s) => s.status === "needs_review").length} need
            review ·{" "}
            {students.filter((s) => s.status === "not_identified").length} not
            yet identified
          </p>
          <div className="record-navigation" aria-label="Matched photos">
            {result.photos.map((p, i) => (
              <button
                key={p.photoId}
                type="button"
                aria-pressed={i === photoIndex}
                onClick={() => setPhotoIndex(i)}
              >
                Photo {i + 1}
              </button>
            ))}
          </div>
          {photo && (
            <div className="face-overlay">
              <img
                key={photo.photoId}
                src={`${apiBase}${base}/${photo.photoId === "original" ? "photo" : "photos/" + photo.photoId}`}
                alt={`Class photo ${photoIndex + 1} with numbered faces`}
                onLoad={(e) =>
                  setSize({
                    w: e.currentTarget.naturalWidth,
                    h: e.currentTarget.naturalHeight,
                  })
                }
              />
              {photo.faces.map((f, i) => (
                <span
                  key={i}
                  style={{
                    left: `${(f.box.x_min / size.w) * 100}%`,
                    top: `${(f.box.y_min / size.h) * 100}%`,
                    width: `${((f.box.x_max - f.box.x_min) / size.w) * 100}%`,
                    height: `${((f.box.y_max - f.box.y_min) / size.h) * 100}%`,
                  }}
                >
                  <b>{i + 1}</b>
                </span>
              ))}
            </div>
          )}
          {photo && (
            <ol>
              {photo.faces.map((f, i) => (
                <li key={i}>
                  {f.learnerId
                    ? students.find((s) => s.id === f.learnerId)?.name ||
                      "Unresolved"
                    : "Unresolved — review manually"}
                </li>
              ))}
            </ol>
          )}
          <div className="table-container">
            <SmartTable>
              <caption>
                Combined suggestions — each student appears once
              </caption>
              <thead>
                <tr>
                  <th>Student</th>
                  <th>Code</th>
                  <th>Suggestion</th>
                  <th>Seen in photos</th>
                </tr>
              </thead>
              <tbody>
                {students.map((s) => (
                  <tr key={s.id}>
                    <th scope="row">{s.name}</th>
                    <td>{s.code}</td>
                    <td>
                      {s.status === "suggested_present"
                        ? "Suggested present"
                        : s.status === "needs_review"
                          ? "Needs review"
                          : "Not yet identified"}
                    </td>
                    <td>
                      {s.photoIds
                        .map(
                          (p) =>
                            result.photos.findIndex((x) => x.photoId === p) + 1,
                        )
                        .join(", ") || "—"}
                    </td>
                  </tr>
                ))}
              </tbody>
            </SmartTable>
          </div>
          <p>
            Unidentified students are not automatically absent. Multiple photos
            improve coverage, not certainty. Review the photos before using
            suggestions.
          </p>
          <button
            disabled={!students.some((s) => s.status === "suggested_present")}
            onClick={() =>
              onSuggestions(
                Object.fromEntries(
                  students
                    .filter((s) => s.status === "suggested_present")
                    .map((s) => [s.id, "present"]),
                ),
              )
            }
          >
            Fill unmarked students with suggested present
          </button>
        </>
      )}
    </section>
  );
}
