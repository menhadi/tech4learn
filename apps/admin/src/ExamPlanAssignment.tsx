import { useState } from "react";
import { api, ApiError } from "./api";
import { DraftForm } from "./DraftForm";

type Plan = { id: number; name: string; selected: boolean; revision: string };
type Page = { items: Plan[]; next: string | null; assignment_revision: string };
type Assignment = {
  request_id: string;
  plan_id: number;
  assignment_revision: string;
  plan_revision: string;
};
export function ExamPlanAssignment({
  org,
  onSaved,
}: {
  org: string;
  onSaved: () => void;
}) {
  const base = `/organisations/${org}/exam-workspace`;
  const [page, setPage] = useState<Page | null>(null);
  const [selected, setSelected] = useState("");
  const [pending, setPending] = useState<Assignment | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  async function load(more = false) {
    setBusy(true);
    setError("");
    setNotice("");
    const previous = more ? page : null;
    if (!more) {
      setPage(null);
      setSelected("");
    }
    try {
      const result = await api<Page>(
        `${base}/plans${previous?.next ? `?after=${previous.next}` : ""}`,
      );
      if (
        previous &&
        previous.assignment_revision !== result.assignment_revision
      ) {
        setPage(null);
        setSelected("");
        throw Error("The organisation plan changed. Reload plan choices.");
      }
      setPage({
        ...result,
        items: [...(previous?.items ?? []), ...result.items],
      });
    } catch (cause) {
      setError(
        cause instanceof Error ? cause.message : "Unable to load plans.",
      );
    } finally {
      setBusy(false);
    }
  }
  async function save(event: React.FormEvent) {
    event.preventDefault();
    const choice = page?.items.find((plan) => String(plan.id) === selected);
    if (!pending && (!choice || !page)) return;
    const body = pending ?? {
      request_id: crypto.randomUUID(),
      plan_id: choice!.id,
      assignment_revision: page!.assignment_revision,
      plan_revision: choice!.revision,
    };
    setPending(body);
    setBusy(true);
    setError("");
    setNotice("");
    try {
      await api(`${base}/plan`, "POST", body);
      setPending(null);
      setPage(null);
      setSelected("");
      setNotice(
        "Exam plan assigned. Reload choices and plan permissions to see the current access.",
      );
      onSaved();
    } catch (cause) {
      if (
        cause instanceof ApiError &&
        [400, 403, 404, 409].includes(cause.status)
      ) {
        setPending(null);
        setPage(null);
        setSelected("");
      }
      setError(
        cause instanceof Error
          ? cause.message
          : "Assignment was not confirmed. Retry the same assignment.",
      );
    } finally {
      setBusy(false);
    }
  }
  return (
    <section aria-label="Assign exam plan">
      <h4>Organisation exam plan</h4>
      <p>
        Choose an existing exam plan for this organisation. Its permissions
        apply alongside the saved module restrictions. Existing subscription
        dates are preserved.
      </p>
      <button
        type="button"
        disabled={busy || !!pending}
        onClick={() => void load()}
      >
        Load plan choices
      </button>
      {page?.next && (
        <button
          type="button"
          disabled={busy || !!pending}
          onClick={() => void load(true)}
        >
          Load more plans
        </button>
      )}
      {page && (
        <p>
          {page.items.find((plan) => plan.selected)
            ? `Current plan: ${page.items.find((plan) => plan.selected)!.name}`
            : page.next
              ? "Current plan has not appeared in these choices yet."
              : "No current active plan appears in these choices."}
        </p>
      )}
      <DraftForm
        draftKey={`exam-plan-${org}`}
        title="Organisation exam plan"
        onSubmit={save}
        draftState={{ selected, pending }}
        restoreState={(state) => {
          if (busy || pending) return;
          setSelected(
            typeof state?.selected === "string" ? state.selected : "",
          );
          const p = state?.pending;
          if (
            p &&
            typeof p.request_id === "string" &&
            /^[a-f0-9-]{36}$/.test(p.request_id) &&
            Number.isSafeInteger(p.plan_id) &&
            p.plan_id > 0 &&
            /^[a-f0-9]{64}$/.test(p.assignment_revision) &&
            /^[a-f0-9]{64}$/.test(p.plan_revision)
          )
            setPending({
              request_id: p.request_id,
              plan_id: p.plan_id,
              assignment_revision: p.assignment_revision,
              plan_revision: p.plan_revision,
            });
        }}
      >
        <fieldset disabled={busy || !!pending || !page}>
          <legend>Plan selection</legend>
          <label>
            Exam plan
            <select
              name="plan"
              value={selected}
              onChange={(event) => setSelected(event.target.value)}
            >
              <option value="">Choose a plan</option>
              {page?.items.map((plan) => (
                <option key={plan.id} value={plan.id}>
                  {plan.name}
                  {plan.selected ? " (current)" : ""}
                </option>
              ))}
            </select>
          </label>
        </fieldset>
        {pending && (
          <p role="status">
            Plan assignment is not yet confirmed. Retry the same assignment
            before choosing another plan.
          </p>
        )}
        <button
          type="submit"
          disabled={
            busy ||
            (!pending &&
              !page?.items.some((plan) => String(plan.id) === selected))
          }
        >
          {busy
            ? "Working…"
            : pending
              ? "Retry plan assignment"
              : "Assign plan"}
        </button>
      </DraftForm>
      {error && <p role="alert">{error}</p>}
      {notice && <p role="status">{notice}</p>}
    </section>
  );
}
