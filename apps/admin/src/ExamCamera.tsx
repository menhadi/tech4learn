import { useEffect, useRef, useState } from "react";
import { api } from "./api";

// Mounted only for an active camera-enabled attempt. No frames enter draft storage.
export function ExamCamera({
  base,
  attemptId,
  onReady,
}: {
  base: string;
  attemptId: number;
  onReady: (ready: boolean) => void;
}) {
  const video = useRef<HTMLVideoElement>(null);
  const stream = useRef<MediaStream | null>(null);
  const pending = useRef<{
    request_id: string;
    attempt_id: number;
    image: string;
  } | null>(null);
  const alive = useRef(true);
  const running = useRef(false);
  const generation = useRef(0);
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const readyCallback = useRef(onReady);
  readyCallback.current = onReady;
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [connected, setConnected] = useState(false);
  const [saved, setSaved] = useState("");

  function stop() {
    if (timer.current) clearTimeout(timer.current);
    timer.current = null;
    for (const track of stream.current?.getTracks() ?? []) {
      track.onended = null;
      track.onmute = null;
      track.stop();
    }
    stream.current = null;
    if (video.current) video.current.srcObject = null;
  }
  useEffect(() => {
    alive.current = true;
    readyCallback.current(false);
    return () => {
      alive.current = false;
      generation.current++;
      stop();
      pending.current = null;
      readyCallback.current(false);
    };
  }, []);

  function lost() {
    stop();
    readyCallback.current(false);
    setConnected(false);
    setError(
      "Camera stopped. Reconnect it to continue saving answers. The exam timer continues.",
    );
  }
  async function capture() {
    if (running.current || !alive.current) return;
    running.current = true;
    setBusy(true);
    setError("");
    readyCallback.current(false);
    const current = generation.current;
    try {
      if (!pending.current) {
        const element = video.current;
        if (
          !element ||
          element.readyState < 2 ||
          !element.videoWidth ||
          !stream.current
            ?.getVideoTracks()
            .some((track) => track.readyState === "live" && !track.muted)
        )
          throw new Error("Camera is not ready. Reconnect it and retry.");
        const scale = Math.min(
          1,
          640 / element.videoWidth,
          480 / element.videoHeight,
        );
        const canvas = document.createElement("canvas");
        canvas.width = Math.max(1, Math.floor(element.videoWidth * scale));
        canvas.height = Math.max(1, Math.floor(element.videoHeight * scale));
        const context = canvas.getContext("2d");
        if (!context)
          throw new Error("This browser cannot capture a camera frame.");
        context.drawImage(element, 0, 0, canvas.width, canvas.height);
        const url = canvas.toDataURL("image/jpeg", 0.7);
        if (!url.startsWith("data:image/jpeg;base64,") || url.length > 349551)
          throw new Error(
            "Camera frame could not be prepared. Reconnect and retry.",
          );
        pending.current = {
          request_id: crypto.randomUUID(),
          attempt_id: attemptId,
          image: url.slice(23),
        };
      }
      const receipt = await api<{
        saved: boolean;
        capture_id: string;
        attempt_id: number;
        received_at: string;
      }>(`${base}/attempt/proctor`, "POST", pending.current);
      if (!alive.current || current !== generation.current) return;
      if (
        receipt.saved !== true ||
        receipt.capture_id !== pending.current?.request_id ||
        receipt.attempt_id !== attemptId
      )
        throw new Error(
          "Camera save was not confirmed. Retry the same capture.",
        );
      pending.current = null;
      setSaved(receipt.received_at);
      const available = !!stream.current
        ?.getVideoTracks()
        .some((track) => track.readyState === "live" && !track.muted);
      readyCallback.current(available);
      if (available) timer.current = setTimeout(() => void capture(), 30000);
    } catch (cause) {
      if (alive.current && current === generation.current)
        setError(
          cause instanceof Error
            ? cause.message
            : "Camera capture failed. Retry to continue.",
        );
    } finally {
      if (alive.current && current === generation.current) {
        running.current = false;
        setBusy(false);
      }
    }
  }
  async function connect() {
    if (running.current) return;
    running.current = true;
    setBusy(true);
    setError("");
    readyCallback.current(false);
    stop();
    const current = generation.current;
    try {
      const next = await navigator.mediaDevices.getUserMedia({
        video: { width: { ideal: 640 }, height: { ideal: 480 } },
        audio: false,
      });
      if (!alive.current || current !== generation.current) {
        next.getTracks().forEach((track) => track.stop());
        return;
      }
      stream.current = next;
      for (const track of next.getVideoTracks()) {
        track.onended = lost;
        track.onmute = lost;
      }
      video.current!.srcObject = next;
      await video.current!.play();
      if (!alive.current || current !== generation.current) return;
      if (
        !next
          .getVideoTracks()
          .some((track) => track.readyState === "live" && !track.muted)
      )
        throw new Error("Camera stopped while connecting.");
      setConnected(true);
      running.current = false;
      await capture();
    } catch {
      if (alive.current && current === generation.current) {
        stop();
        setConnected(false);
        setError(
          "Camera access was not available. Allow camera access in your browser, then reconnect. No microphone is requested.",
        );
      }
    } finally {
      if (alive.current && current === generation.current) {
        running.current = false;
        setBusy(false);
      }
    }
  }
  return (
    <section aria-label="Exam camera">
      <h3>Exam camera</h3>
      <p>
        This exam requires a camera image about every 30 seconds for authorised
        exam review. Images are private and expire after 30 days. No audio is
        recorded. The exam timer continues during camera or connection problems.
      </p>
      <video
        ref={video}
        muted
        playsInline
        aria-label="Your camera preview"
        style={{ width: "100%", maxWidth: 240 }}
      />
      {saved && (
        <p role="status">
          Last camera image saved: {new Date(saved).toLocaleTimeString()}
        </p>
      )}
      {error && (
        <p role="alert" className="error">
          {error}
        </p>
      )}
      {!connected && (
        <button disabled={busy} onClick={() => void connect()}>
          Allow or reconnect camera
        </button>
      )}
      {connected && error && (
        <button disabled={busy} onClick={() => void capture()}>
          Retry camera capture
        </button>
      )}
      {connected && (
        <button className="secondary" disabled={busy} onClick={lost}>
          Stop camera
        </button>
      )}
      {busy && <p role="status">Connecting or saving camera image…</p>}
    </section>
  );
}
