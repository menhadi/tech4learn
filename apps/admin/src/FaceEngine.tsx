import { SmartTable } from "./DirectoryTable";
import { useEffect, useState, useRef } from "react";
import { api } from "./api";
type Status = {
  available: boolean;
  message?: string;
  paused: boolean;
  running?: boolean;
  ready?: boolean;
  state?: string;
  oomKilled?: boolean;
  cpus?: number;
  memoryGiB?: number;
  services?: { name: string; state: string }[];
  host?: {
    cpus: number;
    memoryGiB: number;
    availableGiB: number;
    load: number[];
  };
};
export function FaceEngine() {
  const confirmation = useRef<HTMLElement>(null);
  const [data, setData] = useState<Status>();
  const [cpus, setCpus] = useState(2),
    [memory, setMemory] = useState(4);
  const [busy, setBusy] = useState(false),
    [error, setError] = useState(""),
    [notice, setNotice] = useState("");
  const [action, setAction] = useState("");
  useEffect(() => {
    if (action)
      confirmation.current?.scrollIntoView({
        behavior: "smooth",
        block: "center",
      });
  }, [action]);
  async function refresh() {
    setBusy(true);
    setError("");
    try {
      const result = await api<Status>(
        "/platform/face-engine",
        "GET",
        undefined,
        70000,
      );
      setData(result);
      if (result.available) {
        setCpus(result.cpus || 2);
        setMemory(result.memoryGiB || 4);
      }
    } catch (e) {
      setError(
        e instanceof Error ? e.message : "Could not load engine status.",
      );
    } finally {
      setBusy(false);
    }
  }
  useEffect(() => {
    void refresh();
  }, []);
  async function apply() {
    setBusy(true);
    setError("");
    setNotice("");
    try {
      const result = await api<Status>(
        "/platform/face-engine",
        "POST",
        {
          action,
          confirm: true,
          ...(action === "limits" ? { cpus, memoryGiB: memory } : {}),
        },
        70000,
      );
      setData(result);
      setAction("");
      setNotice(
        result.paused
          ? "Comparisons are paused. After starting the engine, wait for all services to run, then select Start / resume."
          : "Engine ready. Queued comparisons can resume.",
      );
    } catch (e) {
      setAction("");
      setError(
        e instanceof Error
          ? e.message
          : "Operation failed. Refresh status before retrying.",
      );
    } finally {
      setBusy(false);
    }
  }
  const host = data?.host;
  return (
    <div className="face-engine">
      <section className="panel">
        <div className="engine-heading">
          <div>
            <p className="eyebrow">Platform infrastructure</p>
            <h2>Attendance face engine</h2>
            <p className="muted">
              Manage this server’s Tech4Learn engine. Organisation
              administrators cannot access these controls.
            </p>
          </div>
          <button
            className="secondary"
            disabled={busy}
            onClick={() => void refresh()}
          >
            Refresh status
          </button>
        </div>
        {error && (
          <p role="alert" className="error">
            {error}
          </p>
        )}
        {notice && <p role="status">{notice}</p>}
        {!data ? (
          <p>Loading controller status…</p>
        ) : !data.available ? (
          <p role="status">{data.message}</p>
        ) : (
          <>
            <div className="engine-stats">
              <div>
                <small>Engine</small>
                <strong>
                  {data.ready
                    ? "Services running"
                    : data.running
                      ? "Starting / needs attention"
                      : data.state}
                </strong>
              </div>
              <div>
                <small>Attendance queue</small>
                <strong>{data.paused ? "Paused" : "Enabled"}</strong>
              </div>
              <div>
                <small>CPU limit</small>
                <strong>{data.cpus} cores</strong>
              </div>
              <div>
                <small>RAM limit</small>
                <strong>{data.memoryGiB} GiB</strong>
              </div>
            </div>
            {data.oomKilled && (
              <p role="alert">
                The engine was stopped by its memory limit. Review available
                host capacity before starting it again.
              </p>
            )}
            <p className="muted">
              Service status does not verify recognition accuracy or API-key
              configuration. Reference checks and a reviewed class-photo pilot
              are still required.
            </p>
            <div className="engine-actions">
              {[
                ["start", "Start / resume"],
                ["stop", "Stop"],
                ["restart", "Restart"],
              ].map(([id, label]) => (
                <button
                  key={id}
                  className="secondary"
                  disabled={busy}
                  onClick={() => setAction(id)}
                >
                  {label}
                </button>
              ))}
            </div>
          </>
        )}
      </section>
      {data?.available && host && (
        <>
          <section className="panel">
            <h2>Capacity and limits</h2>
            <p>
              Host: {host.cpus} CPUs · {host.memoryGiB} GiB RAM ·{" "}
              {host.availableGiB} GiB currently available. Load averages:{" "}
              {host.load.map((n) => n.toFixed(2)).join(" / ")}.
            </p>
            <p className="muted">
              After upgrading your server, refresh this page to detect its new
              capacity. Suggestions leave at least half the host for other
              applications; they are starting points, not tested capacity
              guarantees.
            </p>
            <div className="table-scroll">
              <SmartTable className="engine-table">
                <thead>
                  <tr>
                    <th>Suggested profile</th>
                    <th>CPU</th>
                    <th>RAM</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  {[
                    ["Shared-server pilot", 2, 4],
                    ["Larger pilot", 4, 8],
                    ["Higher capacity", 6, 12],
                  ].map(([label, c, m]) => {
                    const fits =
                      Number(c) <= Math.floor(host.cpus / 2) &&
                      Number(m) <= Math.floor(host.memoryGiB / 2);
                    return (
                      <tr key={label}>
                        <td>{label}</td>
                        <td>{c} cores</td>
                        <td>{m} GiB</td>
                        <td>
                          <button
                            className="secondary"
                            disabled={busy || !fits}
                            onClick={() => {
                              setCpus(Number(c));
                              setMemory(Number(m));
                            }}
                          >
                            {fits ? "Use profile" : "Needs larger host"}
                          </button>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </SmartTable>
            </div>
            <div className="engine-fields">
              <label>
                CPU cores
                <input
                  type="number"
                  min="0.5"
                  step="0.5"
                  max={Math.max(0.5, Math.floor(host.cpus / 2))}
                  value={cpus}
                  onChange={(e) => setCpus(Number(e.target.value))}
                />
              </label>
              <label>
                RAM (GiB)
                <input
                  type="number"
                  min="2"
                  step="1"
                  max={Math.floor(host.memoryGiB / 2)}
                  value={memory}
                  onChange={(e) => setMemory(Number(e.target.value))}
                />
              </label>
            </div>
            <p>
              Applying limits stops the engine safely and preserves its data.
              Select Start / resume afterwards. Extra RAM increases the
              container ceiling; model workers, Java heaps and attendance
              concurrency remain unchanged.
            </p>
            <button
              disabled={
                busy || !Number.isFinite(cpus) || !Number.isFinite(memory)
              }
              onClick={() => setAction("limits")}
            >
              Review new limits
            </button>
          </section>
          <section className="panel">
            <h2>Engine services</h2>
            <div className="table-scroll">
              <SmartTable className="engine-table">
                <thead>
                  <tr>
                    <th>Service</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  {data.services?.map((s) => (
                    <tr key={s.name}>
                      <td>{s.name}</td>
                      <td>{s.state}</td>
                    </tr>
                  ))}
                </tbody>
              </SmartTable>
            </div>
            {!data.services?.length && (
              <p>No running service details are available.</p>
            )}
          </section>
        </>
      )}
      {action && (
        <section
          ref={confirmation}
          className="panel engine-confirm"
          role="region"
          aria-label="Confirm engine action"
        >
          <h2>Confirm {action === "limits" ? "resource limits" : action}</h2>
          <p>
            {action === "limits"
              ? `Stop the face engine and set ${cpus} CPU cores / ${memory} GiB RAM.`
              : action === "stop"
                ? "Stop the face engine and pause queued comparisons."
                : action === "restart"
                  ? "Restart the face engine. Comparisons remain paused while it warms up."
                  : "Start the engine if stopped, and resume comparisons only when all services are running."}{" "}
            Active attendance comparisons block this action.
          </p>
          <div className="engine-actions">
            <button disabled={busy} onClick={() => void apply()}>
              {busy ? "Working…" : "Confirm action"}
            </button>
            <button
              className="secondary"
              disabled={busy}
              onClick={() => setAction("")}
            >
              Cancel
            </button>
          </div>
        </section>
      )}
    </div>
  );
}
