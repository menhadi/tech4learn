import { useState } from "react";
import { api, apiBase } from "./api";
type Face = {
  box: { x_min: number; y_min: number; x_max: number; y_max: number };
  learnerId: string | null;
  similarity: number;
};
export function FaceMatching({
  org,
  id,
  roster,
  onSuggestions,
}: {
  org: string;
  id: string;
  roster: { id: string; name: string }[];
  onSuggestions: (v: Record<string, string>) => void;
}) {
  const [faces, setFaces] = useState<Face[] | null>(null),
    [busy, setBusy] = useState(false),
    [error, setError] = useState(""),
    [size, setSize] = useState({ w: 1, h: 1 });
  return (
    <section className="subpanel">
      <h4>Match enrolled faces</h4>
      <p>
        Compare this photo with checked references for students currently in
        this section. Unknown or ambiguous faces stay unresolved. Pilot limit:
        20 enrolled students; newest checked reference per student.
      </p>
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      <button
        disabled={busy}
        onClick={() => {
          setBusy(true);
          setError("");
          setFaces(null);
          void api<{ faces: Face[] }>(
            `/organisations/${org}/attendance/${id}/face-match`,
            "POST",
            {},
            90000,
          )
            .then((r) => setFaces(r.faces))
            .catch((e) => setError(e.message))
            .finally(() => setBusy(false));
        }}
      >
        {busy ? "Comparing faces…" : "Generate face suggestions"}
      </button>
      {faces && (
        <>
          <div className="face-overlay">
            <img
              src={`${apiBase}/organisations/${org}/attendance/${id}/photo`}
              alt="Attendance capture with numbered face suggestions"
              onLoad={(e) =>
                setSize({
                  w: e.currentTarget.naturalWidth,
                  h: e.currentTarget.naturalHeight,
                })
              }
            />
            {faces.map((f, i) => (
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
          <ol>
            {faces.map((f, i) => (
              <li key={i}>
                {f.learnerId
                  ? roster.find((l) => l.id === f.learnerId)?.name ||
                    "Unresolved"
                  : "Unresolved — review manually"}
                {f.learnerId ? ` · similarity ${f.similarity.toFixed(2)}` : ""}
              </li>
            ))}
          </ol>
          <p>
            Similarity is a model score, not a probability. Review each numbered
            face before using the draft.
          </p>
          <button
            disabled={!faces.some((f) => f.learnerId)}
            onClick={() =>
              onSuggestions(
                Object.fromEntries(
                  faces
                    .filter((f) => f.learnerId)
                    .map((f) => [f.learnerId!, "present"]),
                ),
              )
            }
          >
            Use suggested present marks in review
          </button>
        </>
      )}
    </section>
  );
}
