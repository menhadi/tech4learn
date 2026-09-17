import { useState } from "react";
import { api } from "./api";
import { DirectoryTable } from "./DirectoryTable";

export function ExamCapabilities({ org }: { org: string }) {
  const [rows, setRows] = useState<{ key: string; enabled: boolean }[] | null>(
    null,
  );
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  return (
    <section aria-label="ExamElite plan permissions">
      <h4>ExamElite plan permissions</h4>
      <p>
        These permissions show what the connected ExamElite plan allows. They do
        not mean every feature is available in Tech4Learn. Use the feature
        coverage above to check available tools.
      </p>
      <button
        type="button"
        disabled={busy}
        onClick={async () => {
          setBusy(true);
          setError("");
          setRows(null);
          try {
            const result = await api<{
              native_features: { key: string; enabled: boolean }[];
            }>(`/organisations/${org}/exam-workspace/capabilities`);
            setRows(result.native_features);
          } catch (cause) {
            setError(
              cause instanceof Error
                ? cause.message
                : "Unable to load plan permissions.",
            );
          } finally {
            setBusy(false);
          }
        }}
      >
        {busy ? "Loading plan permissions…" : "Load plan permissions"}
      </button>
      {error && <p role="alert">{error}</p>}
      {rows && (
        <DirectoryTable
          title="Connected plan permissions"
          columns={["Feature", "Plan permission"]}
        >
          {rows.map((row) => (
            <tr key={row.key}>
              <td>{row.key.replaceAll("_", " ").replace(/^ai /, "AI ")}</td>
              <td>{row.enabled ? "Allowed" : "Not allowed"}</td>
            </tr>
          ))}
        </DirectoryTable>
      )}
    </section>
  );
}
