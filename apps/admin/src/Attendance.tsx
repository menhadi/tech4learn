import {DirectoryTable,emptyTableQuery,type TableQuery} from "./DirectoryTable";
import { DraftForm } from "./DraftForm";
import { SmartTable } from "./DirectoryTable";
import { useEffect, useRef, useState } from "react";
import { api, apiBase } from "./api";
import { PhotoAnalysis } from "./PhotoAnalysis";
import { FaceMatching } from "./FaceMatching";
import { SectionSelect } from "./SectionSelect";
import type { AcademicGroup } from "./AcademicStructure";

type Group = AcademicGroup;
type Field = {
  key: string;
  label: string;
  kind: string;
  required: boolean;
  options: string[];
};
type Policy = {
  accuracy_limit: number;
  timezone: string;
  self_review: boolean;
  version: number;
};
type Snapshot = {
  centre: { radius: number };
  test_run?: boolean;
  group_name: string;
  centre_name: string;
  roster: { id: string; name: string; code: string }[];
  fields: Field[];
  policy: Policy;
};
type Intent = { id: string; created_at: string; snapshot: Snapshot };
type Reading = {
  latitude: number;
  longitude: number;
  accuracy: number;
  timestamp: string;
};
type Capture = {
  photo: string;
  captured_at: string;
  location: Reading | null;
  custom_values: Record<string, unknown>;
};
type Entry = {
  test_run?: boolean;
  attendance_date:string;
  id: string;
  status: string;
  group_name: string;
  centre_name: string;
  location_status: string;
  marks: Record<string, string>;
};
type ExtraPhoto = {
  id: string;
  received_at: string;
  evidence: Detail["evidence"];
};
type Detail = {
  extraPhotos: ExtraPhoto[];
  group_id: string;
  id: string;
  status: string;
  version: number;
  snapshot: Snapshot;
  marks: Record<string, string>;
  received_at: string;
  attendance_date: string;
  evidence: {
    captured_at: string;
    location_status: string;
    distance: number | null;
    location: Reading | null;
    warnings: string[];
    custom_values: Record<string, unknown>;
  };
  reviews: {
    id: string;
    actor_name: string;
    created_at: string;
    decision: string;
    reason: string;
    marks: Record<string, string>;
  }[];
};
type Listing = {
  rows: Entry[];
  total:number;filtered:number;
  counts: { status: string; n: string }[];
  totals: { mark: string; n: string }[];
};
const message = (e: unknown) =>
  e instanceof Error ? e.message : "Unable to connect. Please retry.";
const today = (tz = "Asia/Kolkata") =>
  new Intl.DateTimeFormat("en-CA", {
    timeZone: tz,
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  }).format(new Date());

export function Attendance({
  org,
  permissions,
  groups,
  mode = "all",
  initialGroup = "",
}: {
  org: string;
  permissions: string[];
  groups: Group[];
  mode?: "all" | "daily" | "capture";
  initialGroup?: string;
}) {
  const base = `/organisations/${org}/attendance`,
    can = (p: string) => permissions.includes(`attendance.${p}`);
  const [group, setGroup] = useState(initialGroup),
    [addingTo, setAddingTo] = useState<string | null>(null),
    [extraPhotos, setExtraPhotos] = useState<ExtraPhoto[]>([]),
    [intent, setIntent] = useState<Intent | null>(null),
    [capture, setCapture] = useState<Capture | null>(null),
    [values, setValues] = useState<Record<string, unknown>>({});
  const [policy, setPolicy] = useState<Policy | null>(null),
    [date, setDate] = useState(today()),
    [tableQuery,setTableQuery]=useState<TableQuery>({...emptyTableQuery,sort:"attendance_date",direction:"desc"}),
    [listLoading,setListLoading]=useState(false),
    [listing, setListing] = useState<Listing | null>(null),
    [detail, setDetail] = useState<Detail | null>(null),
    [marks, setMarks] = useState<Record<string, string>>({}),
    [reason, setReason] = useState(""),
    [ack, setAck] = useState(false),
    [showPhoto, setShowPhoto] = useState(false);
  const [busy, setBusy] = useState(false),
    [error, setError] = useState(""),
    [notice, setNotice] = useState(""),
    [revision, setRevision] = useState(0),
    [live, setLive] = useState(false);
  const capturePanel = useRef<HTMLElement>(null);
  useEffect(() => {
    if (intent)
      capturePanel.current?.scrollIntoView({
        block: "start",
        behavior: "smooth",
      });
  }, [intent]);
  const video = useRef<HTMLVideoElement>(null),
    stream = useRef<MediaStream | null>(null),
    generation = useRef(0),
    mounted = useRef(true),
    operation = useRef(false);
  function stop() {
    stream.current?.getTracks().forEach((t) => t.stop());
    stream.current = null;
    setLive(false);
  }
  function reset() {
    generation.current++;
    stop();
    setIntent(null);
    setCapture(null);
    setValues({});
  }
  useEffect(() => {
    mounted.current = true;
    return () => {
      mounted.current = false;
      generation.current++;
      stream.current?.getTracks().forEach((t) => t.stop());
    };
  }, []);
  useEffect(() => {
    let active = true;
    api<Policy>(`${base}/policy`)
      .then((p) => {
        if (active) {
          setPolicy(p);
          setDate(today(p.timezone));
        }
      })
      .catch((e) => active && setError(message(e)));
    return () => {
      active = false;
    };
  }, [org]);
  useEffect(() => {
    let active = true;
    setListLoading(true);
    const timer=setTimeout(()=>api<Listing>(`${base}?date=${date}&query=${encodeURIComponent(JSON.stringify(tableQuery))}`)
      .then((d) => active && setListing(d))
      .catch((e) => active && setError(message(e))).finally(()=>{if(active)setListLoading(false);}),250);
    return () => {
      active = false;clearTimeout(timer);
    };
  }, [org, date, tableQuery, revision]);
  useEffect(() => {
    if (live && video.current && stream.current) {
      video.current.srcObject = stream.current;
      void video.current
        .play()
        .catch(() => setError("Tap the video to start its preview."));
    }
  }, [live]);
  async function act(work: () => Promise<void>) {
    if (operation.current) return;
    operation.current = true;
    setBusy(true);
    setError("");
    setNotice("");
    try {
      await work();
    } catch (e) {
      if (mounted.current) setError(message(e));
    } finally {
      operation.current = false;
      if (mounted.current) setBusy(false);
    }
  }
  async function open(id: string, preserveMarks = false) {
    const d = await api<Detail>(`${base}/${id}`);
    if (!mounted.current) return;
    setDetail(d);
    if (!preserveMarks) setMarks(d.marks);
    setExtraPhotos(d.extraPhotos);
    if (!preserveMarks) setReason("");
    setAck(false);
    setShowPhoto(false);
  }
  async function start(parentId?: string) {
    reset();
    const gen = generation.current;
    setAddingTo(parentId || null);
    if (!parentId) {
      setDetail(null);
      setExtraPhotos([]);
    }
    const response = await api<Intent | {existingId:string;status:string}>(
      parentId ? `${base}/${parentId}/photos/captures` : `${base}/captures`,
      "POST",
      {
        group_id: group,
        resume_existing: true,
      },
    );
    if ("existingId" in response) {
      await open(response.existingId);
      if (response.status === "pending") {
        setNotice("Today's attendance is open. Take another photo and submit it to this record.");
        await start(response.existingId);
      } else {
        setNotice("Today's attendance is already confirmed. Open its review to correct marks; no duplicate was created.");
      }
      return;
    }
    const i = response;
    if (!navigator.mediaDevices?.getUserMedia)
      throw new Error(
        "Camera capture needs HTTPS and a browser with camera support.",
      );
    const media = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: { ideal: "environment" }, width: { ideal: 1280 } },
      audio: false,
    });
    if (!mounted.current || gen !== generation.current) {
      media.getTracks().forEach((t) => t.stop());
      return;
    }
    stream.current = media;
    setIntent(i);
    if (i.snapshot.test_run) setNotice("Test mode: you can submit this class again today. This run is excluded from daily totals.");
    setLive(true);
  }
  async function take() {
    const v = video.current;
    if (!v?.videoWidth)
      throw new Error("Wait for the camera preview before capturing.");
    const canvas = document.createElement("canvas"),
      scale = Math.min(1, 1280 / Math.max(v.videoWidth, v.videoHeight));
    canvas.width = Math.round(v.videoWidth * scale);
    canvas.height = Math.round(v.videoHeight * scale);
    const context = canvas.getContext("2d");
    if (!context) throw new Error("Camera capture is unavailable.");
    context.drawImage(v, 0, 0, canvas.width, canvas.height);
    const captured_at = new Date().toISOString();
    let photo = "";
    for (const quality of [0.8, 0.65, 0.5, 0.35]) {
      photo = canvas.toDataURL("image/jpeg", quality).split(",")[1];
      if (photo.length <= 349524) break;
    }
    if (photo.length > 349524)
      throw new Error(
        "Photo is too large. Try a simpler scene or move closer.",
      );
    stop();
    const gen = generation.current;
    const location = await new Promise<Reading | null>((resolve) => {
      if (!navigator.geolocation) return resolve(null);
      navigator.geolocation.getCurrentPosition(
        (p) =>
          resolve({
            latitude: p.coords.latitude,
            longitude: p.coords.longitude,
            accuracy: p.coords.accuracy,
            timestamp: new Date(p.timestamp).toISOString(),
          }),
        () => resolve(null),
        { enableHighAccuracy: true, maximumAge: 0, timeout: 10000 },
      );
    });
    if (mounted.current && gen === generation.current)
      setCapture({ photo, captured_at, location, custom_values: {} });
  }
  return (
    <div className="attendance-workspace">
      <h3>Photo attendance</h3>
      <p>
        Capture evidence, then confirm each learner. A photo never marks
        learners present automatically.
      </p>
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      {notice && <p role="status">{notice}</p>}
      {can("capture") && (mode !== "daily" || addingTo) && (
        <section className="panel" ref={capturePanel}>
          <h4>
            {addingTo
              ? "Add another photo to this attendance"
              : "Start class attendance"}
          </h4>
          <p>
            Choose a group and use its live camera preview. Location and capture
            time are collected automatically. Submit within ten minutes.
          </p>
          <SectionSelect
            groups={groups.filter((g) => !g.archived)}
            value={group}
            initialValue={initialGroup}
            disabled={busy || !!intent || !!addingTo}
            onChange={(id) => {
              reset();
              setGroup(id);
            }}
          />
          {!intent && (
            <button
              disabled={busy || !group}
              onClick={() => void act(() => start(addingTo || undefined))}
            >
              Open camera
            </button>
          )}
          {busy && <p role="status">Working…</p>}
          {live && (
            <>
              <video
                className="attendance-photo"
                ref={video}
                autoPlay
                muted
                playsInline
                controls
                aria-label="Live attendance camera preview"
              />
              <button disabled={busy} onClick={() => void act(take)}>
                Take photo and get location
              </button>
            </>
          )}
          {capture && intent && (
            <DraftForm title="Classroom photo details" draftKey={`capture:${intent?.id}`}
              onSubmit={(e) => {
                e.preventDefault();
                void act(async () => {
                  const result = await api<{ id: string }>(
                    addingTo
                      ? `${base}/${addingTo}/photos/${intent.id}/submit`
                      : `${base}/captures/${intent.id}/submit`,
                    "POST",
                    { ...capture, custom_values: values },
                  );
                  const wasAdding = !!addingTo;
                  reset();
                  setAddingTo(null);
                  setRevision((x) => x + 1);
                  await open(result.id, wasAdding);
                  setNotice(
                    "Submitted for review. No learner marks have been confirmed yet.",
                  );
                });
              }}
            >
              <img
                className="attendance-photo"
                src={`data:image/jpeg;base64,${capture.photo}`}
                alt="Your captured attendance evidence"
              />
              <p>
                Captured {new Date(capture.captured_at).toLocaleString()}.{" "}
                {capture.location
                  ? `Location accuracy: ±${Math.round(capture.location.accuracy)} m.`
                  : "Location unavailable: this will require a review reason."}
              </p>
              <p>
                {intent.snapshot.roster.length} students in this section. Save
                this photo, then add another before reviewing attendance.
              </p>
              {!addingTo &&
                intent.snapshot.fields.map((f) => (
                  <label key={f.key}>
                    {f.label}
                    {f.required ? " *" : ""}
                    {f.kind === "choice" || f.kind === "boolean" ? (
                      <select
                        required={f.required}
                        value={String(values[f.key] ?? "")}
                        onChange={(e) =>
                          setValues({
                            ...values,
                            [f.key]:
                              e.target.value === ""
                                ? ""
                                : f.kind === "boolean"
                                  ? e.target.value === "true"
                                  : e.target.value,
                          })
                        }
                      >
                        <option value="">Choose</option>
                        {(f.kind === "boolean"
                          ? ["true", "false"]
                          : f.options
                        ).map((o) => (
                          <option key={o} value={o}>
                            {f.kind === "boolean"
                              ? o === "true"
                                ? "Yes"
                                : "No"
                              : o}
                          </option>
                        ))}
                      </select>
                    ) : (
                      <input
                        required={f.required}
                        maxLength={500}
                        type={
                          f.kind === "number"
                            ? "number"
                            : f.kind === "date"
                              ? "date"
                              : "text"
                        }
                        step={f.kind === "number" ? "any" : undefined}
                        value={String(values[f.key] ?? "")}
                        onChange={(e) =>
                          setValues({
                            ...values,
                            [f.key]:
                              f.kind === "number" && e.target.value !== ""
                                ? Number(e.target.value)
                                : e.target.value,
                          })
                        }
                      />
                    )}
                  </label>
                ))}
              <button disabled={busy}>Submit for review</button>
            </DraftForm>
          )}
          {intent && (
            <button
              className="secondary"
              disabled={busy}
              onClick={() => {
                reset();
                setAddingTo(null);
              }}
            >
              Cancel / retake
            </button>
          )}
          <p className="hint">
            Browser location and camera evidence help review; they cannot prove
            device authenticity. Face matching requires a configured engine;
            offline capture is planned.
          </p>
        </section>
      )}
      {mode !== "capture" && (
        <section className="panel">
          <h4>Daily attendance</h4>
          <label>
            Attendance date ({policy?.timezone || "Asia/Kolkata"})
            <input
              type="date"
              value={date}
              onChange={(e) => {
                setDate(e.target.value);
                setTableQuery(q=>({...q,offset:0}));
              }}
            />
          </label>
          <button
            className="secondary"
            disabled={busy}
            onClick={() => setRevision((x) => x + 1)}
          >
            Refresh
          </button>
          {listing ? (
            <>
              <p>
                {listing.counts.map((c) => `${c.n} ${c.status}`).join(" · ") ||
                  "No captures for this date."}
              </p>
              <p>
                Confirmed learner marks:{" "}
                {listing.totals.map((c) => `${c.n} ${c.mark}`).join(" · ") ||
                  "None yet"}
                . Missing captures are not counted as absences.
              </p>
              <DirectoryTable title="Daily attendance" columns={["Class / section","Centre","Date","Status","Location","Actions"]} columnKeys={["group_name","centre_name","attendance_date","status","location_status",""]} remote={{query:tableQuery,onChange:q=>setTableQuery({...q,sort:q.sort||"attendance_date"}),total:listing.total,filtered:listing.filtered,loading:listLoading}}>
                {listing.rows.map(r=><tr key={r.id}><th scope="row">{r.group_name}</th><td>{r.centre_name}</td><td>{r.attendance_date}</td><td>{r.status}{r.test_run && " - Test run"}</td><td>{r.test_run ? "Accepted for testing" : r.location_status}</td><td><button type="button" disabled={busy||!!intent} onClick={()=>void act(()=>open(r.id))}>Open attendance</button></td></tr>)}
              </DirectoryTable>
            </>
          ) : (
            <p role="status">Loading attendance…</p>
          )}
        </section>
      )}
      {detail && (
        <section className="panel" key={detail.id}>
          <h4>
            {detail.snapshot.test_run && "Test run (excluded from daily totals): "}{detail.snapshot.group_name} — {detail.attendance_date}
          </h4>
          <p>
            {detail.snapshot.centre_name} · {detail.status}
          </p>
          <p>
            Captured: {new Date(detail.evidence.captured_at).toLocaleString()} ·
            Received: {new Date(detail.received_at).toLocaleString()}
          </p>
          <p>
            {detail.snapshot.test_run ? "Test capture saved" : detail.evidence.location_status} · Distance:{" "}
            {detail.evidence.distance === null
              ? "unavailable"
              : `${detail.evidence.distance} m`}{" "}
            · Accuracy:{" "}
            {detail.evidence.location
              ? `±${Math.round(detail.evidence.location.accuracy)} m`
              : "unavailable"}
          </p>
          {detail.snapshot.test_run && <p role="status">Testing mode: location is accepted for this test. No GPS policy changes or location approval are needed. Review the student marks below and confirm attendance.</p>}
          {!detail.snapshot.test_run && <p>Limits used for this capture: GPS uncertainty {detail.snapshot.policy.accuracy_limit} m; centre radius {detail.snapshot.centre.radius} m.</p>}
          {!detail.snapshot.test_run && <p className="muted">Centre radius controls distance from the centre. GPS uncertainty controls how precise your device location must be. Changing one does not change the other.</p>}
          {!detail.snapshot.test_run && policy && policy.accuracy_limit !== detail.snapshot.policy.accuracy_limit && <p>The current GPS limit is {policy.accuracy_limit} m. Start a new capture to use it; this record retains its original checks.</p>}
          {!detail.snapshot.test_run && can("policy") && <button type="button" className="secondary" onClick={e => {
            const panel = e.currentTarget.closest(".attendance-workspace")?.querySelector<HTMLDetailsElement>("[data-attendance-policy]");
            if (panel) { panel.open = true; panel.scrollIntoView({behavior:"smooth",block:"start"}); panel.querySelector<HTMLInputElement>("input")?.focus({preventScroll:true}); }
          }}>Change GPS accuracy limit</button>}
          {!detail.snapshot.test_run && detail.evidence.warnings.length > 0 && (
            <div className="error">
              <strong>Review required</strong>
              <ul>
                {detail.evidence.warnings.map((w) => (
                  <li key={w}>{w}</li>
                ))}
              </ul>
            </div>
          )}
          {can("photos") &&
            (showPhoto ? (
              <img
                className="attendance-photo"
                src={`${apiBase}${base}/${detail.id}/photo`}
                alt="Original attendance evidence"
                onError={() =>
                  setError(
                    "Photo could not be loaded. Check your access and retry.",
                  )
                }
              />
            ) : (
              <button className="secondary" onClick={() => setShowPhoto(true)}>
                View private photo
              </button>
            ))}
          {extraPhotos.map((p, i) => (
            <details key={p.id} className="subpanel">
              <summary>
                Photo {i + 2} · {p.evidence.location_status}
              </summary>
              {can("photos") && (
                <img
                  className="attendance-photo"
                  src={`${apiBase}${base}/${detail.id}/photos/${p.id}`}
                  alt={`Additional class photo ${i + 2}`}
                />
              )}
              <p>
                Captured {new Date(p.evidence.captured_at).toLocaleString()} ·
                Received {new Date(p.received_at).toLocaleString()}
              </p>
              {!detail.snapshot.test_run && p.evidence.warnings.map((w) => (
                <p className="error" key={w}>
                  {w}
                </p>
              ))}
            </details>
          ))}
          {can("capture") && detail.status === "pending" && (
            <button
              disabled={busy || !!intent || extraPhotos.length >= 4}
              onClick={() =>
                void act(async () => {
                  setGroup(detail.group_id);
                  await start(detail.id);
                })
              }
            >
              Add another class photo ({extraPhotos.length + 1}/5 saved)
            </button>
          )}
          {detail.snapshot.fields.map((f) => (
            <p key={f.key}>
              <strong>{f.label}:</strong>{" "}
              {String(detail.evidence.custom_values[f.key] ?? "—")}
            </p>
          ))}
          {can("photos") && (
            <details className="subpanel">
              <summary>
                Optional: analyse the first photo or read a register
              </summary>
              <PhotoAnalysis
                org={org}
                id={detail.id}
                permissions={permissions}
                onSuggestions={(suggestions) => {
                  setMarks((old) => ({
                    ...suggestions,
                    ...Object.fromEntries(
                      Object.entries(old).filter(([, mark]) => !!mark),
                    ),
                  }));
                  setNotice(
                    "AI draft marks loaded. Check every learner and the register date, then confirm separately.",
                  );
                }}
              />
            </details>
          )}
          {can("match") &&
            can("photos") &&
            permissions.includes("learners.photos") && (
              <FaceMatching
                key={`${detail.id}-${detail.version}`}
                org={org}
                id={detail.id}
                roster={detail.snapshot.roster}
                onSuggestions={(suggestions) => {
                  setMarks((old) => ({
                    ...suggestions,
                    ...Object.fromEntries(
                      Object.entries(old).filter(([, mark]) => !!mark),
                    ),
                  }));
                  setNotice(
                    "Face suggestions loaded into the draft. Review every student, then confirm attendance separately.",
                  );
                }}
              />
            )}
          <DraftForm title="Review attendance" draftKey={`attendance:${detail?.id}`} draftState={{marks,reason}} restoreState={v=>{setMarks(Object.fromEntries(detail.snapshot.roster.map(l=>[l.id,v.marks?.[l.id]||""])));setReason(v.reason||"");setAck(false);}}
            onSubmit={(e) => {
              e.preventDefault();
              void act(async () => {
                await api(`${base}/${detail.id}/review`, "POST", {
                  version: detail.version,
                  decision: "confirmed",
                  marks,
                  reason,
                  acknowledge_warnings: ack,
                });
                await open(detail.id);
                setRevision((x) => x + 1);
                setNotice(
                  "Attendance confirmed. The review is recorded in its history.",
                );
              });
            }}
          >
            <div className="table-container">
              <SmartTable>
                <caption>Review attendance — mark every student</caption>
                <thead>
                  <tr>
                    <th>Student</th>
                    <th>Code</th>
                    <th>Attendance</th>
                  </tr>
                </thead>
                <tbody>
                  {detail.snapshot.roster.map((l) => (
                    <tr key={l.id}>
                      <th scope="row">{l.name}</th>
                      <td>{l.code}</td>
                      <td>
                        <select
                          name={`mark:${l.id}`} aria-label={`Attendance for ${l.name}`}
                          required
                          disabled={
                            !can("review") ||
                            detail.status === "rejected" ||
                            busy
                          }
                          value={marks[l.id] || ""}
                          onChange={(e) =>
                            setMarks({ ...marks, [l.id]: e.target.value })
                          }
                        >
                          <option value="">Choose a mark</option>
                          <option value="present">Present</option>
                          <option value="absent">Absent</option>
                          <option value="excused">Excused</option>
                        </select>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </SmartTable>
            </div>
            {can("review") && detail.status !== "rejected" && (
              <>
                <label>
                  {detail.snapshot.test_run && detail.status === "pending" ? "Note (optional for confirming this test)" : "Review / correction reason"}
                  <textarea
                    maxLength={1000}
                    value={reason}
                    onChange={(e) => setReason(e.target.value)}
                  />
                </label>
                {!detail.snapshot.test_run && (detail.evidence.warnings.length > 0 ||
                  extraPhotos.some((p) => p.evidence.warnings.length > 0)) && (
                  <label>
                    <input
                      type="checkbox"
                      checked={ack}
                      onChange={(e) => setAck(e.target.checked)}
                    />{" "}
                    I have reviewed the location warnings for every photo.
                  </label>
                )}
                <button disabled={busy}>
                  {detail.status === "confirmed"
                    ? "Save correction"
                    : "Confirm attendance"}
                </button>
                {detail.status === "pending" && (
                  <button
                    type="button"
                    className="secondary"
                    disabled={busy}
                    onClick={() =>
                      void act(async () => {
                        await api(`${base}/${detail.id}/review`, "POST", {
                          version: detail.version,
                          decision: "rejected",
                          reason,
                          acknowledge_warnings: ack,
                        });
                        await open(detail.id);
                        setRevision((x) => x + 1);
                        setNotice(
                          "Capture rejected. A fresh capture can now be submitted for this day.",
                        );
                      })
                    }
                  >
                    Reject capture
                  </button>
                )}
              </>
            )}
          </DraftForm>
          <details>
            <summary>
              Review and correction history ({detail.reviews.length})
            </summary>
            {detail.reviews.map((r) => (
              <div className="record-row" key={r.id}>
                <strong>
                  {r.actor_name} · {r.decision}
                </strong>
                <p>
                  {new Date(r.created_at).toLocaleString()} ·{" "}
                  {r.reason || "No location warning"}
                </p>
                {detail.snapshot.roster.map((l) => (
                  <p key={l.id}>
                    {l.name}: {r.marks[l.id] || "Not marked"}
                  </p>
                ))}
              </div>
            ))}
          </details>
        </section>
      )}
      {policy && can("policy") && (
        <details className="panel" data-attendance-policy>
          <summary>Attendance policy</summary>
          <DraftForm title="Attendance policy" draftKey={"attendance-policy"} draftState={policy} restoreState={v=>setPolicy({...v,version:policy?.version})}
            onSubmit={(e) => {
              e.preventDefault();
              void act(async () => {
                setPolicy(await api<Policy>(`${base}/policy`, "PATCH", policy));
                setNotice(
                  `GPS uncertainty limit saved: ${policy.accuracy_limit} metres. Start a new capture to use it. Existing records keep their original checks.`,
                );
              });
            }}
          >
            <label>
              Maximum GPS uncertainty (metres; separate from centre radius)
              <input
                type="number"
                min={5}
                max={1000}
                required
                value={policy.accuracy_limit}
                onChange={(e) =>
                  setPolicy({
                    ...policy,
                    accuracy_limit: Number(e.target.value),
                  })
                }
              />
            </label>
            <label>
              Time zone
              <input
                required
                value={policy.timezone}
                onChange={(e) =>
                  setPolicy({ ...policy, timezone: e.target.value })
                }
              />
            </label>
            <label>
              <input
                type="checkbox"
                checked={policy.self_review}
                onChange={(e) =>
                  setPolicy({ ...policy, self_review: e.target.checked })
                }
              />{" "}
              Allow authorised staff to review their own capture
            </label>
            <p>
              Centre radius and approved coordinates are managed in Centres.
              Location readings older than one minute and submissions delayed
              over five minutes require review.
            </p>
            <button disabled={busy}>Save attendance policy</button>
          </DraftForm>
        </details>
      )}
    </div>
  );
}
