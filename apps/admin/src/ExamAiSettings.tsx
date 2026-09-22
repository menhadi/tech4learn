import { useState } from "react";
import { api } from "./api";
import { SmartTable } from "./DirectoryTable";

type Settings = {
  providers: { code: string; credential_saved: boolean; configured_model: string | null; configured_vision_model: string | null }[];
  priority: string[];
  task_priorities: Record<string, string[]>;
};
const labels: Record<string, string> = { google: "Google", openai: "OpenAI", deepseek: "DeepSeek", anthropic: "Anthropic" };
export function ExamAiSettings({ org }: { org: string }) {
  const [settings, setSettings] = useState<Settings | null>(null);
  const [busy, setBusy] = useState(false), [error, setError] = useState("");
  async function load() {
    setBusy(true); setError(""); setSettings(null);
    try { setSettings(await api<Settings>(`/organisations/${org}/exam-workspace/central-ai-settings`)); }
    catch (failure) { setError(failure instanceof Error ? failure.message : "Could not load AI settings."); }
    finally { setBusy(false); }
  }
  const order = (codes: string[]) => codes.map(code => labels[code] ?? code).join(" → ");
  return <section className="panel">
    <h3>Central exam AI providers</h3>
    <p>Review the platform’s central provider configuration. Organisation access is controlled by its plan. Editing these settings in Tech4Learn is not available yet.</p>
    <button type="button" className="secondary" disabled={busy} onClick={() => void load()}>{busy ? "Loading…" : "Load central AI settings"}</button>
    {error && <p role="alert">{error}</p>}
    {settings && <>
      <SmartTable><caption>Configured central providers</caption><thead><tr><th>Provider</th><th>Credential</th><th>Configured model</th><th>Configured vision model</th></tr></thead>
        <tbody>{settings.providers.map(provider => <tr key={provider.code}><th>{labels[provider.code]}</th><td>{provider.credential_saved ? "Saved" : "Not saved"}</td><td>{provider.configured_model ?? "Native default"}</td><td>{provider.code === "deepseek" ? provider.configured_vision_model ?? "Native default" : "Uses configured model"}</td></tr>)}</tbody>
      </SmartTable>
      <p>A saved credential has not been tested here. Model defaults are managed centrally.</p>
      <p>Default priority: {order(settings.priority)}</p>
      <SmartTable><caption>AI task priorities</caption><thead><tr><th>Task</th><th>Provider order</th></tr></thead>
        <tbody>{Object.entries(settings.task_priorities).map(([task, codes]) => <tr key={task}><th>{task.replaceAll("_", " ")}</th><td>{order(codes)}</td></tr>)}</tbody>
      </SmartTable>
    </>}
  </section>;
}
