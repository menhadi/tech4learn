import { SmartTable } from "./DirectoryTable";
import { useEffect, useState } from "react";
import { api } from "./api";
type Provider = {
  id: string;
  label: string;
  model: string | null;
  configured: boolean;
};
type Result = {
  visible_people: number | null;
  quality: string;
  warnings: string[];
  entries: { name: string; code: string; mark: string }[];
  suggestions: Record<string, string>;
  notice: string;
};
type Run = {
  id: string;
  provider: string;
  model: string;
  mode: string;
  status: string;
  created_at: string;
  result: Result | null;
};
export function PhotoAnalysis({
  org,
  id,
  permissions,
  onSuggestions,
}: {
  org: string;
  id: string;
  permissions: string[];
  onSuggestions: (marks: Record<string, string>) => void;
}) {
  const [providers, setProviders] = useState<Provider[]>([]),
    [provider, setProvider] = useState(""),
    [mode, setMode] = useState("scene"),
    [runs, setRuns] = useState<Run[]>([]),
    [error, setError] = useState(""),
    [busy, setBusy] = useState(false);
  const base = `/organisations/${org}/attendance`;
  async function refresh() {
    setRuns(await api<Run[]>(`${base}/${id}/analyses`));
  }
  useEffect(() => {
    let active = true;
    setRuns([]);
    setError("");
    Promise.all([
      api<Provider[]>(`${base}/ai/providers`),
      api<Run[]>(`${base}/${id}/analyses`),
    ])
      .then(([p, r]) => {
        if (active) {
          setProviders(p);
          setProvider(p.find((p) => p.configured)?.id || "");
          setRuns(r);
        }
      })
      .catch((e) => active && setError(e.message));
    return () => {
      active = false;
    };
  }, [org, id]);
  return (
    <section className="ai-analysis subpanel">
      <h4>AI photo analysis</h4>
      <p>
        Group photos: approximate count and image quality only. Registers: read
        visible names/codes and marks for staff review. These providers do not
        perform learner face matching.
      </p>
      {error && (
        <p role="alert" className="error">
          {error}
        </p>
      )}
      {permissions.includes("attendance.analyse") && (
        <>
          <div className="form-grid">
            <label>
              AI provider
              <select
                disabled={busy}
                value={provider}
                onChange={(e) => setProvider(e.target.value)}
              >
                <option value="">Choose a configured provider</option>
                {providers.map((p) => (
                  <option key={p.id} value={p.id} disabled={!p.configured}>
                    {p.label}
                    {p.configured ? "" : " — not configured"}
                  </option>
                ))}
              </select>
            </label>
            <label>
              Photo type
              <select
                disabled={busy}
                value={mode}
                onChange={(e) => setMode(e.target.value)}
              >
                <option value="scene">Group / classroom photo</option>
                <option value="register">Physical attendance register</option>
              </select>
            </label>
          </div>
          <p>
            The saved photo will be sent to the selected provider. Its output is
            a draft; your attendance marks stay unchanged.
          </p>
          <button
            type="button"
            disabled={busy || !provider}
            onClick={async () => {
              setBusy(true);
              setError("");
              try {
                await api(
                  `${base}/${id}/analyse`,
                  "POST",
                  { provider, mode },
                  60000,
                );
                await refresh();
              } catch (e) {
                setError(e instanceof Error ? e.message : "Analysis failed.");
                await refresh().catch(() => {});
              } finally {
                setBusy(false);
              }
            }}
          >
            {busy ? "Analysing photo…" : "Analyse saved photo"}
          </button>
        </>
      )}
      {!providers.some((p) => p.configured) && (
        <p>
          No provider is connected yet. The platform administrator must
          configure a server API key and an image-capable model.
        </p>
      )}
      <button
        type="button"
        className="secondary"
        disabled={busy}
        onClick={() => void refresh().catch((e) => setError(e.message))}
      >
        Refresh analysis history
      </button>
      {runs.map((r) => (
        <article className="record" key={r.id}>
          <strong>
            {providers.find((p) => p.id === r.provider)?.label || r.provider} ·{" "}
            {r.mode === "register" ? "Register reading" : "Photo quality"} ·{" "}
            {r.status}
          </strong>
          <small>
            {new Date(r.created_at).toLocaleString()} · {r.model}
          </small>
          {r.result && (
            <>
              <p>{r.result.quality}</p>
              {r.result.visible_people !== null && (
                <p>
                  Approximate visible people: {r.result.visible_people}. This is
                  not a learner attendance count.
                </p>
              )}
              <ul>
                {r.result.warnings.map((w, i) => (
                  <li key={i}>{w}</li>
                ))}
              </ul>
              <p>{r.result.notice}</p>
              {r.result.entries.length > 0 && (
                <div className="permission-table-scroll">
                  <SmartTable>
                    <thead>
                      <tr>
                        <th>Read name</th>
                        <th>Read code</th>
                        <th>Read mark</th>
                      </tr>
                    </thead>
                    <tbody>
                      {r.result.entries.map((e, i) => (
                        <tr key={i}>
                          <td>{e.name}</td>
                          <td>{e.code}</td>
                          <td>{e.mark}</td>
                        </tr>
                      ))}
                    </tbody>
                  </SmartTable>
                </div>
              )}
              {permissions.includes("attendance.review") &&
                Object.values(r.result.suggestions).some(
                  (v) => v !== "unknown",
                ) && (
                  <button
                    type="button"
                    className="secondary"
                    onClick={() =>
                      onSuggestions(
                        Object.fromEntries(
                          Object.entries(r.result!.suggestions).filter(
                            ([, v]) => v !== "unknown",
                          ),
                        ),
                      )
                    }
                  >
                    Use matched register entries as draft marks
                  </button>
                )}
            </>
          )}
        </article>
      ))}
    </section>
  );
}
