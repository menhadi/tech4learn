import { useEffect, useState } from "react";
import { api, apiBase } from "./api";
type Purpose = "profile" | "reference";
type Photo = {
  id: string;
  purpose: Purpose;
  checked: boolean;
  width: number;
  height: number;
};
type State = {
  photos: Photo[];
  consents: {
    purpose: Purpose;
    granted: boolean;
    version: number;
    recorded_at: string;
  }[];
  verificationConfigured: boolean;
};
async function prepare(file: File) {
  if (
    !["image/jpeg", "image/png", "image/webp"].includes(file.type) ||
    file.size > 10000000
  )
    throw new Error("Choose a JPEG, PNG or WebP image up to 10 MB.");
  const bitmap = await createImageBitmap(file);
  try {
    if (
      bitmap.width < 160 ||
      bitmap.height < 160 ||
      bitmap.width * bitmap.height > 24000000
    )
      throw new Error(
        "Use a clear photo at least 160 × 160 pixels and below 24 megapixels.",
      );
    const ratio = Math.min(1, 800 / Math.max(bitmap.width, bitmap.height)),
      canvas = document.createElement("canvas");
    canvas.width = Math.round(bitmap.width * ratio);
    canvas.height = Math.round(bitmap.height * ratio);
    const ctx = canvas.getContext("2d")!;
    ctx.fillStyle = "#fff";
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    let encoded = canvas.toDataURL("image/jpeg", 0.85);
    for (let q = 0.75; encoded.length > 349550 && q >= 0.35; q -= 0.1)
      encoded = canvas.toDataURL("image/jpeg", q);
    if (encoded.length > 349550)
      throw new Error(
        "Photo is too large after resizing. Choose a simpler portrait.",
      );
    const px = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
    let sum = 0;
    for (let i = 0; i < px.length; i += 4)
      sum += (px[i] + px[i + 1] + px[i + 2]) / 3;
    const brightness = sum / (px.length / 4);
    return {
      url: encoded,
      warning:
        brightness < 45 || brightness > 220
          ? "Lighting may be too dark or bright. Retake the photo if the face is unclear."
          : "",
    };
  } finally {
    bitmap.close();
  }
}
export function StudentPhotos({
  org,
  id,
  permissions,
  archived,
  demo,
}: {
  org: string;
  id: string;
  permissions: string[];
  archived: boolean;
  demo: boolean;
}) {
  const [state, setState] = useState<State | null>(null),
    [error, setError] = useState(""),
    [busy, setBusy] = useState(false),
    [purpose, setPurpose] = useState<Purpose>("profile"),
    [prepared, setPrepared] = useState<{ url: string; warning: string } | null>(
      null,
    ),
    [notice, setNotice] = useState("");
  const base = `/organisations/${org}/learners/${id}`;
  const manage = permissions.includes("learners.photo_manage");
  async function refresh() {
    setState(await api<State>(base + "/photos"));
  }
  useEffect(() => {
    let active = true;
    api<State>(base + "/photos")
      .then((s) => active && setState(s))
      .catch((e) => active && setError(e.message));
    return () => {
      active = false;
    };
  }, [org, id]);
  async function act(run: () => Promise<unknown>, message: string) {
    setBusy(true);
    setError("");
    try {
      await run();
      await refresh();
      setNotice(message);
    } catch (e) {
      setError(
        e instanceof Error ? e.message : "Unable to complete photo action.",
      );
    } finally {
      setBusy(false);
    }
  }
  const consent = state?.consents.find((c) => c.purpose === purpose);
  return (
    <section className="subpanel student-photos">
      <h3>Photos and attendance enrolment</h3>
      <p>
        Profile photos identify the student in their record. Reference photos
        are used separately for attendance face matching.
      </p>
      {error && (
        <p role="alert" className="error">
          {error}
        </p>
      )}
      {notice && <p role="status">{notice}</p>}
      {!state ? (
        <p>Loading photos…</p>
      ) : (
        <>
          <p className="table-help">
            {state.verificationConfigured
              ? "Private face verification is configured. Run Check face on each reference before matching."
              : "Face verification is not configured yet. Profile photos and consent can be managed; reference checks and matching require the private engine."}
          </p>
          <div className="student-photo-grid">
            {state.photos.map((p) => (
              <article className="student-photo-card" key={p.id}>
                <img
                  src={`${apiBase}${base}/photos/${p.id}`}
                  alt={
                    p.purpose === "profile"
                      ? "Student profile"
                      : "Attendance face reference"
                  }
                />
                <strong>
                  {p.purpose === "profile"
                    ? "Profile photo"
                    : "Attendance reference"}
                </strong>
                <span
                  className={`status-badge ${p.checked ? "status-active" : "status-neutral"}`}
                >
                  {p.purpose === "profile"
                    ? "Profile photo"
                    : p.checked
                      ? "Face checked"
                      : "Not face-checked"}
                </span>
                <small>
                  {p.width} × {p.height} px
                </small>
                {manage && (
                  <div className="actions">
                    {!archived && p.purpose === "reference" && (
                      <button
                        disabled={busy || !state.verificationConfigured}
                        onClick={() =>
                          void act(
                            () =>
                              api(
                                `${base}/photos/${p.id}/check`,
                                "POST",
                                {},
                                20000,
                              ),
                            "Face check passed.",
                          )
                        }
                      >
                        Check face
                      </button>
                    )}
                    <details>
                      <summary>Remove photo</summary>
                      <p>
                        This deletes the stored photo. Attendance records
                        remain.
                      </p>
                      <button
                        disabled={busy}
                        onClick={() =>
                          void act(
                            () =>
                              api(`${base}/photos/${p.id}/remove`, "POST", {}),
                            "Photo removed.",
                          )
                        }
                      >
                        Confirm removal
                      </button>
                    </details>
                  </div>
                )}
              </article>
            ))}
          </div>
          {!state.photos.length && <p>No photos uploaded.</p>}
          <label>
            Photo purpose
            <select
              disabled={busy}
              value={purpose}
              onChange={(e) => {
                setPurpose(e.target.value as Purpose);
                setPrepared(null);
                setNotice("");
                setError("");
              }}
            >
              <option value="profile">Profile photo</option>
              <option value="reference">Attendance face references</option>
            </select>
          </label>
          <p>
            <span
              className={`status-badge ${consent?.granted ? "status-active" : "status-neutral"}`}
            >
              {consent?.granted
                ? "Consent recorded"
                : "Consent not recorded / withdrawn"}
            </span>
            {consent && (
              <small> · {new Date(consent.recorded_at).toLocaleString()}</small>
            )}
          </p>
          {manage && (
            <form
              key={`${purpose}-${consent?.version || 0}`}
              onSubmit={(e) => {
                e.preventDefault();
                const f = new FormData(e.currentTarget);
                void act(
                  () =>
                    api(base + "/photo-consent", "POST", {
                      purpose,
                      version: consent?.version || 0,
                      granted: !consent?.granted,
                      attested: f.get("attested") === "on",
                    }),
                  consent?.granted
                    ? "Consent withdrawn and photos deleted."
                    : "Consent recorded.",
                );
              }}
            >
              <label className="check">
                <input
                  name="attested"
                  type="checkbox"
                  required
                  disabled={busy}
                />
                {consent?.granted
                  ? "I confirm the request to withdraw consent and delete photos for this purpose."
                  : `I have recorded permission from the student or authorised guardian for ${purpose === "profile" ? "storing the profile photo" : "using face reference photos for attendance verification"}.`}
              </label>
              <button disabled={busy}>
                {consent?.granted
                  ? "Withdraw consent and delete photos"
                  : "Record consent"}
              </button>
            </form>
          )}
          {manage && !archived && consent?.granted && (
            <div className="editor-panel">
              {demo && purpose === "reference" ? (
                <p>
                  Dummy records cannot identify real people. Register a separate
                  consented test student to test face matching.
                </p>
              ) : (
                <>
                  <label>
                    Choose {purpose === "profile" ? "profile" : "reference"}{" "}
                    photo
                    <input
                      key={purpose}
                      type="file"
                      accept="image/jpeg,image/png,image/webp"
                      disabled={busy}
                      onChange={(e) => {
                        const file = e.target.files?.[0];
                        setPrepared(null);
                        setError("");
                        if (file) {
                          setBusy(true);
                          void prepare(file)
                            .then(setPrepared)
                            .catch((e) => setError(e.message))
                            .finally(() => setBusy(false));
                        }
                      }}
                    />
                  </label>
                  <p className="table-help">
                    One person, facing the camera, with even lighting. Reference
                    photos should show the face clearly from slightly different
                    angles. Images are resized and re-encoded before upload.
                  </p>
                  {prepared && (
                    <>
                      <img
                        className="photo-preview"
                        src={prepared.url}
                        alt="Photo to upload"
                      />
                      {prepared.warning && (
                        <p className="error">{prepared.warning}</p>
                      )}
                      <button
                        disabled={busy}
                        onClick={() =>
                          void act(async () => {
                            await api(base + "/photos", "POST", {
                              purpose,
                              photo: prepared.url.split(",")[1],
                              consentVersion: consent.version,
                            });
                            setPrepared(null);
                          }, "Photo saved.")
                        }
                      >
                        {purpose === "profile"
                          ? "Save / replace profile photo"
                          : "Save reference photo"}
                      </button>
                    </>
                  )}
                </>
              )}
            </div>
          )}
        </>
      )}
    </section>
  );
}
