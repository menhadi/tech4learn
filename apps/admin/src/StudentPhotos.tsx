import { useEffect, useRef, useState } from "react";
import { api, apiBase } from "./api";
type Purpose = "profile" | "reference";
type Photo = {
  id: string;
  purpose: Purpose;
  name: string;
  content_hash: string;
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
  const [state, setState] = useState<State | null>(null);
  const [busy, setBusy] = useState(false),
    [error, setError] = useState(""),
    [notice, setNotice] = useState("");
  const [prepared, setPrepared] = useState<{
    url: string;
    warning: string;
  } | null>(null);
  const [name, setName] = useState("Front view"),
    [profile, setProfile] = useState(true),
    [reference, setReference] = useState(!demo);
  const [attested, setAttested] = useState(false),
    [live, setLive] = useState(false);
  const [facing, setFacing] = useState<"user" | "environment">("environment");
  const video = useRef<HTMLVideoElement>(null),
    stream = useRef<MediaStream | null>(null),
    generation = useRef(0);
  const mounted = useRef(true);
  const base = `/organisations/${org}/learners/${id}`;
  const manage = permissions.includes("learners.photo_manage") && !archived;
  function stopCamera() {
    generation.current++;
    stream.current?.getTracks().forEach((t) => t.stop());
    stream.current = null;
    setLive(false);
  }
  useEffect(() => {
    mounted.current = true;
    api<State>(base + "/photos")
      .then((s) => {
        if (mounted.current) setState(s);
      })
      .catch((e) => {
        if (mounted.current) setError(e.message);
      });
    return () => {
      mounted.current = false;
      generation.current++;
      stream.current?.getTracks().forEach((t) => t.stop());
      stream.current = null;
    };
  }, [base]);
  useEffect(() => {
    if (live && video.current) {
      video.current.srcObject = stream.current;
      void video.current
        .play()
        .catch(() =>
          setError(
            "Camera preview could not start. Close the camera and try again.",
          ),
        );
    }
  }, [live]);
  async function run(work: () => Promise<void>) {
    setBusy(true);
    setError("");
    setNotice("");
    try {
      await work();
    } catch (e) {
      if (mounted.current)
        setError(
          e instanceof Error ? e.message : "Photo action failed. Please retry.",
        );
    } finally {
      if (mounted.current) setBusy(false);
    }
  }
  async function refresh() {
    const s = await api<State>(base + "/photos");
    if (mounted.current) setState(s);
  }
  async function openCamera() {
    stopCamera();
    const token = generation.current;
    if (!navigator.mediaDevices?.getUserMedia)
      throw new Error(
        "Camera access needs HTTPS and a supported browser. You can also upload a photo.",
      );
    let media: MediaStream;
    try {
      media = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: facing }, width: { ideal: 1280 } },
        audio: false,
      });
    } catch {
      throw new Error(
        "Cannot open the camera. Allow camera access in your browser, check that the camera is connected, or use Upload photo.",
      );
    }
    if (!mounted.current || generation.current !== token) {
      media.getTracks().forEach((t) => t.stop());
      return;
    }
    stream.current = media;
    setLive(true);
    setPrepared(null);
  }
  async function capture() {
    const token = generation.current;
    const v = video.current;
    if (!v?.videoWidth)
      throw new Error("Wait for the camera picture before taking the photo.");
    const canvas = document.createElement("canvas");
    const scale = Math.min(1, 1280 / Math.max(v.videoWidth, v.videoHeight));
    canvas.width = Math.round(v.videoWidth * scale);
    canvas.height = Math.round(v.videoHeight * scale);
    const ctx = canvas.getContext("2d");
    if (!ctx) throw new Error("Camera capture is unavailable.");
    ctx.drawImage(v, 0, 0, canvas.width, canvas.height);
    const blob = await new Promise<Blob>((resolve, reject) =>
      canvas.toBlob(
        (b) =>
          b ? resolve(b) : reject(new Error("Could not capture the photo.")),
        "image/jpeg",
        0.9,
      ),
    );
    const result = await prepare(
      new File([blob], "portrait.jpg", { type: "image/jpeg" }),
    );
    if (!mounted.current || generation.current !== token) return;
    stopCamera();
    setPrepared(result);
  }
  async function save() {
    if (!prepared || !state) return;
    const result = await api<{ photos: { id: string; purpose: Purpose }[] }>(
      base + "/photo-setup",
      "POST",
      {
        name,
        profile,
        reference,
        attested,
        photo: prepared.url.split(",")[1],
        consentVersions: Object.fromEntries(
          (["profile", "reference"] as Purpose[]).map((p) => [
            p,
            state.consents.find((c) => c.purpose === p)?.version || 0,
          ]),
        ),
      },
    );
    setPrepared(null);
    setAttested(false);
    await refresh();
    const ref = result.photos.find((p) => p.purpose === "reference");
    if (ref && state.verificationConfigured) {
      try {
        await api(`${base}/photos/${ref.id}/check`, "POST", {}, 20000);
        await refresh();
        setNotice(
          "Photo saved. Attendance face check passed. You can now use this reference when reviewing classroom attendance.",
        );
      } catch (e) {
        setNotice(
          "Photo saved successfully. The attendance face check has not passed yet.",
        );
        setError(
          e instanceof Error
            ? e.message
            : "Face check unavailable. Retry Check face below.",
        );
      }
    } else
      setNotice(
        ref
          ? "Photo saved for your selected uses. Face checking will be available when your administrator enables the engine."
          : "Profile picture saved.",
      );
  }
  const groups = Object.values(
    (state?.photos || []).reduce<Record<string, Photo[]>>((all, p) => {
      (all[p.content_hash || p.id] ??= []).push(p);
      return all;
    }, {}),
  );
  return (
    <section className="subpanel student-photos">
      <h3>Add a student photo</h3>
      <p>
        Take or upload one portrait, name it, and use it for the profile
        picture, attendance, or both. You do not need to upload the same photo
        twice.
      </p>
      {error && (
        <p role="alert" className="error">
          {error}
        </p>
      )}
      {notice && (
        <p role="status" className="photo-success">
          {notice}
        </p>
      )}
      {busy && (
        <p role="status">
          Please wait… Saving or checking a face may take a few seconds.
        </p>
      )}
      {!state ? (
        <p>Loading student photos…</p>
      ) : (
        <>
          {manage ? (
            <div className="editor-panel photo-upload-panel">
              <h4>1. Take or choose a photo</h4>
              <p>
                Photograph this student alone, with the face clearly visible and
                evenly lit. Classroom group photos are captured separately in
                Attendance.
              </p>
              <div className="actions">
                <label>
                  Camera
                  <select
                    value={facing}
                    disabled={busy || live}
                    onChange={(e) =>
                      setFacing(e.target.value as "user" | "environment")
                    }
                  >
                    <option value="environment">Rear / outward-facing</option>
                    <option value="user">Front / selfie</option>
                  </select>
                </label>
                <button
                  type="button"
                  disabled={busy || live}
                  onClick={() => void run(openCamera)}
                >
                  Open camera
                </button>
                <label className="photo-file-choice">
                  Upload photo
                  <input
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    disabled={busy || live}
                    onChange={(e) => {
                      const file = e.target.files?.[0];
                      e.target.value = "";
                      if (file)
                        void run(async () => {
                          const next = await prepare(file);
                          if (mounted.current) setPrepared(next);
                        });
                    }}
                  />
                </label>
              </div>
              {live && (
                <div className="portrait-camera">
                  <video
                    ref={video}
                    autoPlay
                    muted
                    playsInline
                    aria-label="Live student camera preview"
                  />
                  <div className="actions">
                    <button
                      type="button"
                      disabled={busy}
                      onClick={() => void run(capture)}
                    >
                      Take photo
                    </button>
                    <button
                      type="button"
                      className="secondary"
                      onClick={stopCamera}
                    >
                      Close camera
                    </button>
                  </div>
                </div>
              )}
              {prepared && (
                <div className="portrait-preview">
                  <img
                    className="photo-preview"
                    src={prepared.url}
                    alt="Student portrait preview before saving"
                  />
                  <div>
                    <strong>Preview — not saved yet</strong>
                    {prepared.warning && <p>{prepared.warning}</p>}
                    <button
                      type="button"
                      className="secondary"
                      disabled={busy}
                      onClick={() => setPrepared(null)}
                    >
                      Discard / retake
                    </button>
                  </div>
                </div>
              )}
              <h4>2. Name the photo and choose its uses</h4>
              <label>
                Photo name
                <input
                  value={name}
                  maxLength={80}
                  disabled={busy}
                  onChange={(e) => setName(e.target.value)}
                  placeholder="e.g. Front view, Left angle"
                />
              </label>
              <div className="photo-use-options">
                <label className="check">
                  <input
                    type="checkbox"
                    checked={profile}
                    disabled={busy}
                    onChange={(e) => {
                      setProfile(e.target.checked);
                      setAttested(false);
                    }}
                  />
                  Use as profile picture{" "}
                  <small>Replaces the current profile picture.</small>
                </label>
                <label className="check">
                  <input
                    type="checkbox"
                    checked={reference}
                    disabled={busy || demo}
                    onChange={(e) => {
                      setReference(e.target.checked);
                      setAttested(false);
                    }}
                  />
                  Use for attendance face matching{" "}
                  <small>Up to 3 individual portraits.</small>
                </label>
              </div>
              {demo && (
                <p>
                  Demo records support a profile picture only. Register a
                  separate consented test student to try face matching.
                </p>
              )}
              {reference && (
                <p>
                  {state.photos.filter((p) => p.purpose === "reference").length}{" "}
                  / 3 attendance photos saved.{" "}
                  {state.verificationConfigured
                    ? "The face will be checked after saving."
                    : "Face checking is not enabled for this organisation yet."}
                </p>
              )}
              <label className="check">
                <input
                  type="checkbox"
                  checked={attested}
                  disabled={busy || (!profile && !reference)}
                  onChange={(e) => setAttested(e.target.checked)}
                />
                I have recorded permission from the student or authorised
                guardian to store this photo
                {profile ? " as the profile picture" : ""}
                {profile && reference ? " and" : ""}
                {reference ? " for attendance face matching" : ""}.
              </label>
              <button
                type="button"
                disabled={
                  busy ||
                  !prepared ||
                  !name.trim() ||
                  !attested ||
                  (!profile && !reference)
                }
                onClick={() => void run(save)}
              >
                {reference && state.verificationConfigured
                  ? "Save photo & check face"
                  : "Save photo"}
              </button>
              {!prepared && (
                <p className="table-help">
                  Take or choose a photo above to enable saving.
                </p>
              )}
            </div>
          ) : (
            <p>
              {archived
                ? "This student is archived. Restore the student before adding photos."
                : "You can view photos. Ask your administrator for permission to manage them."}
            </p>
          )}
          <h4>Saved student photos</h4>
          {!groups.length && (
            <p className="empty-state">
              No photos saved yet. Start with Open camera or Upload photo above.
            </p>
          )}
          <div className="student-photo-grid">
            {groups.map((photos) => {
              const first = photos[0],
                ref = photos.find((p) => p.purpose === "reference");
              return (
                <article
                  className="student-photo-card"
                  key={first.content_hash || first.id}
                >
                  <img
                    src={`${apiBase}${base}/photos/${first.id}`}
                    alt={first.name}
                  />
                  <strong>{first.name}</strong>
                  {photos.some((p) => p.purpose === "profile") && (
                    <span className="status-badge status-neutral">
                      Profile picture
                    </span>
                  )}
                  {ref && (
                    <span
                      className={`status-badge ${ref.checked ? "status-active" : "status-neutral"}`}
                    >
                      {ref.checked
                        ? "Attendance · face checked"
                        : "Attendance · needs face check"}
                    </span>
                  )}
                  {manage && ref && (
                    <button
                      type="button"
                      disabled={busy || !state.verificationConfigured}
                      onClick={() =>
                        void run(async () => {
                          await api(
                            `${base}/photos/${ref.id}/check`,
                            "POST",
                            {},
                            20000,
                          );
                          await refresh();
                          setNotice(
                            "Face check passed. Attendance still requires review of classroom photos.",
                          );
                        })
                      }
                    >
                      {ref.checked ? "Recheck face" : "Check face"}
                    </button>
                  )}
                  {permissions.includes("learners.photo_manage") && (
                    <details>
                      <summary>Remove a photo use</summary>
                      {photos.map((p) => (
                        <button
                          key={p.id}
                          type="button"
                          className="secondary"
                          disabled={busy}
                          onClick={() =>
                            void run(async () => {
                              await api(
                                `${base}/photos/${p.id}/remove`,
                                "POST",
                                {},
                              );
                              await refresh();
                              setNotice("Photo removed for the selected use.");
                            })
                          }
                        >
                          Remove{" "}
                          {p.purpose === "profile"
                            ? "profile picture"
                            : "attendance reference"}
                        </button>
                      ))}
                    </details>
                  )}
                </article>
              );
            })}
          </div>
          {permissions.includes("learners.photo_manage") && (
            <details className="consent-control">
              <summary>Manage permissions and remove photos</summary>
              <p>
                Withdrawing permission deletes all saved photos for that use.
                Existing attendance records remain.
              </p>
              {state.consents
                .filter((c) => c.granted)
                .map((c) => (
                  <form
                    key={c.purpose + String(c.version)}
                    onSubmit={(e) => {
                      e.preventDefault();
                      stopCamera();
                      void run(async () => {
                        await api(base + "/photo-consent", "POST", {
                          purpose: c.purpose,
                          version: c.version,
                          granted: false,
                          attested: true,
                        });
                        setPrepared(null);
                        setAttested(false);
                        await refresh();
                        setNotice(
                          "Permission withdrawn and photos for that use deleted.",
                        );
                      });
                    }}
                  >
                    <label className="check">
                      <input type="checkbox" required disabled={busy} />
                      Confirm withdrawal of{" "}
                      {c.purpose === "profile"
                        ? "profile picture"
                        : "attendance face matching"}{" "}
                      permission.
                    </label>
                    <button disabled={busy}>
                      Withdraw permission & delete{" "}
                      {c.purpose === "profile"
                        ? "profile picture"
                        : "attendance photos"}
                    </button>
                  </form>
                ))}
            </details>
          )}
        </>
      )}
    </section>
  );
}
