import { useState } from "react";
import { api } from "./api";
import { DirectoryTable } from "./DirectoryTable";
import { ExamPlanCreate, type PlanDetail } from "./ExamPlanCreate";

type Plan = {
  id: number;
  name: string;
  active: boolean;
  is_default: boolean;
  revision: string;
};
export function ExamPlanEdit({
  org,
  onSaved,
}: {
  org: string;
  onSaved?: () => void;
}) {
  const [plans, setPlans] = useState<Plan[] | null>(null);
  const [next, setNext] = useState<string | null>(null);
  const [selected, setSelected] = useState<PlanDetail | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const base = `/organisations/${org}/exam-workspace/central-plans`;
  async function load(after = "0") {
    setBusy(true);
    setError("");
    try {
      const result = await api<{ items: Plan[]; next: string | null }>(
        `${base}?after=${after}`,
      );
      setPlans((previous) =>
        after === "0" ? result.items : [...(previous ?? []), ...result.items],
      );
      setNext(result.next);
    } catch (cause) {
      setPlans(null);
      setNext(null);
      setError(
        cause instanceof Error ? cause.message : "Unable to load plans.",
      );
    } finally {
      setBusy(false);
    }
  }
  async function edit(id: number) {
    setBusy(true);
    setError("");
    setNotice("");
    try {
      setSelected(await api<PlanDetail>(`${base}/${id}`));
    } catch (cause) {
      setSelected(null);
      setError(cause instanceof Error ? cause.message : "Unable to load plan.");
    } finally {
      setBusy(false);
    }
  }
  return (
    <section aria-label="Manage exam plans">
      <h4>Existing exam plans</h4>
      <p>
        Superadmin manages shared plan settings here, including inactive plans.
      </p>
      {selected ? (
        <ExamPlanCreate
          key={selected.plan_id}
          org={org}
          plan={selected}
          onClose={() => setSelected(null)}
          onSaved={() => {
            setSelected(null);
            setPlans(null);
            setNext(null);
            setNotice(
              "Plan saved. Reload plans or assignment choices to see current settings.",
            );
            onSaved?.();
          }}
        />
      ) : (
        <>
          <button type="button" disabled={busy} onClick={() => void load()}>
            {busy ? "Loading…" : "Load all plans"}
          </button>
          {plans && (
            <DirectoryTable
              title={`Exam plans — ${plans.length} loaded`}
              columns={["Name", "Status", "Default", "Actions"]}
            >
              {plans.map((plan) => (
                <tr key={plan.id}>
                  <td>{plan.name}</td>
                  <td>{plan.active ? "Active" : "Inactive"}</td>
                  <td>{plan.is_default ? "Yes" : "No"}</td>
                  <td>
                    <button
                      type="button"
                      disabled={busy}
                      onClick={() => void edit(plan.id)}
                    >
                      Edit {plan.name}
                    </button>
                  </td>
                </tr>
              ))}
            </DirectoryTable>
          )}
          {next && (
            <button
              type="button"
              disabled={busy}
              onClick={() => void load(next)}
            >
              Load more plans
            </button>
          )}
        </>
      )}
      {error && <p role="alert">{error}</p>}
      {notice && <p role="status">{notice}</p>}
    </section>
  );
}
