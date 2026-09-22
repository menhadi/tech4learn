import { useState } from "react";
import { api, ApiError } from "./api";
import { DraftForm } from "./DraftForm";

type Schema = { features: string[]; limits: string[] };
type Values = Record<string, string | boolean>;
type Creation = {
  request_id: string;
  revision?: string;
  fields: Record<string, string | boolean | number | null>;
};
export type PlanDetail = {
  plan_id: number;
  revision: string;
  fields: Creation["fields"];
  is_default: boolean;
  assigned_organisations: number;
};
const planValues = (plan: PlanDetail): Values =>
  Object.fromEntries(
    Object.entries(plan.fields).map(([key, value]) => [
      key,
      key.startsWith("limit_")
        ? value === null
          ? ""
          : String(value)
        : (value as string | boolean),
    ]),
  );
const label = (key: string) =>
  key
    .replaceAll("_", " ")
    .replace(/\bai\b/g, "AI")
    .replace(/^./, (c) => c.toUpperCase());
const defaults = (schema: Schema): Values => ({
  name: "",
  price: "0.00",
  billing_cycle: "monthly",
  status: true,
  ...Object.fromEntries(schema.features.map((key) => [`feature_${key}`, true])),
  ...Object.fromEntries(schema.limits.map((key) => [`limit_${key}`, ""])),
});

export function ExamPlanCreate({
  org,
  plan,
  onClose,
  onSaved,
}: {
  org: string;
  plan?: PlanDetail;
  onClose?: () => void;
  onSaved?: () => void;
}) {
  const [schema, setSchema] = useState<Schema | null>(
    plan
      ? {
          features: Object.keys(plan.fields)
            .filter((key) => key.startsWith("feature_"))
            .map((key) => key.slice(8)),
          limits: Object.keys(plan.fields)
            .filter((key) => key.startsWith("limit_"))
            .map((key) => key.slice(6)),
        }
      : null,
  );
  const [values, setValues] = useState<Values>(plan ? planValues(plan) : {});
  const [revision, setRevision] = useState(plan?.revision);
  const [reloadRequired, setReloadRequired] = useState(false);
  const [pending, setPending] = useState<Creation | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const base = `/organisations/${org}/exam-workspace`;
  async function open() {
    setBusy(true);
    setError("");
    try {
      const result = await api<Schema>(`${base}/plan-fields`);
      setSchema(result);
      setValues(defaults(result));
    } catch (cause) {
      setError(
        cause instanceof Error ? cause.message : "Unable to load plan fields.",
      );
    } finally {
      setBusy(false);
    }
  }
  async function save(event: React.FormEvent) {
    event.preventDefault();
    if (!schema || busy || reloadRequired) return;
    const fields: Creation["fields"] = {};
    for (const [key, value] of Object.entries(values))
      fields[key] = key.startsWith("limit_")
        ? value === ""
          ? null
          : Number(value)
        : key === "name"
          ? String(value).trim()
          : value;
    const body = pending ?? {
      request_id: crypto.randomUUID(),
      fields,
      ...(plan ? { revision } : {}),
    };
    setPending(body);
    setBusy(true);
    setError("");
    setNotice("");
    try {
      const result = await api<{ name: string }>(
        plan ? `${base}/central-plans/${plan.plan_id}` : `${base}/plans`,
        "POST",
        body,
      );
      setPending(null);
      if (plan) {
        onSaved?.();
        return;
      }
      setValues(defaults(schema));
      setNotice(
        body.fields.status === false
          ? `Inactive plan “${result.name}” created. It will not appear in active plan choices. Existing assignments are unchanged.`
          : `Plan “${result.name}” created. Load plan choices below to assign it. Existing assignments are unchanged.`,
      );
    } catch (cause) {
      if (
        plan &&
        cause instanceof ApiError &&
        [403, 404, 409].includes(cause.status)
      )
        setReloadRequired(true);
      if (
        cause instanceof ApiError &&
        [400, 403, 404, 409].includes(cause.status)
      )
        setPending(null);
      setError(
        cause instanceof Error
          ? cause.message
          : "Save was not confirmed. Retry the same request.",
      );
    } finally {
      setBusy(false);
    }
  }
  const change = (key: string, value: string | boolean) =>
    setValues((previous) => ({ ...previous, [key]: value }));
  return (
    <section
      aria-label={plan ? "Edit exam plan" : "Create exam plan"}
    >
      <h4>{plan ? "Edit exam plan" : "Create exam plan"}</h4>
      {plan ? (
        <p>
          This shared plan is assigned to {plan.assigned_organisations}{" "}
          organisations. Changes apply to all its assigned organisations.{" "}
          {plan.is_default ? "This is the default plan." : ""}
        </p>
      ) : (
        <p>
          Plans are shared across the central exam service. All native
          capabilities start enabled; choose restrictions and limits here. A
          plan permission does not enable an unfinished Tech4Learn tool.
        </p>
      )}
      {!schema && (
        <button type="button" disabled={busy} onClick={() => void open()}>
          {busy ? "Loading…" : "New exam plan"}
        </button>
      )}
      {schema && (
        <DraftForm
          draftKey={
            plan
              ? `exam-plan-edit-${org}-${plan.plan_id}`
              : `exam-plan-create-${org}`
          }
          title={plan ? "Edit exam plan" : "New exam plan"}
          onSubmit={save}
          draftState={{ values, pending, ...(plan ? { revision } : {}) }}
          restoreState={(state) => {
            if (busy || pending) return;
            if (
              plan &&
              (typeof state?.revision !== "string" ||
                !/^[a-f0-9]{64}$/.test(state.revision))
            )
              return;
            if (plan) setRevision(state.revision);
            const initial = defaults(schema);
            for (const key of Object.keys(initial))
              if (typeof state?.values?.[key] === typeof initial[key])
                initial[key] = state.values[key];
            setValues(initial);
            const p = state?.pending;
            if (
              p &&
              typeof p.request_id === "string" &&
              /^[a-f0-9-]{36}$/.test(p.request_id) &&
              p.fields &&
              typeof p.fields === "object" &&
              !Array.isArray(p.fields) &&
              Object.keys(p.fields).every((key) => key in initial) &&
              JSON.stringify(p.fields).length <= 16384 &&
              (!plan || p.revision === state.revision)
            )
              setPending({
                request_id: p.request_id,
                fields: p.fields,
                ...(plan ? { revision: p.revision } : {}),
              });
          }}
        >
          <fieldset disabled={busy || !!pending || reloadRequired}>
            <legend>Plan details</legend>
            <label>
              Plan name
              <input
                name="name"
                required
                maxLength={255}
                value={String(values.name)}
                onChange={(e) => change("name", e.target.value)}
              />
            </label>
            <label>
              Price
              <input
                name="price"
                inputMode="decimal"
                required
                pattern="(0|[1-9][0-9]{0,7})(\.[0-9]{1,2})?"
                value={String(values.price)}
                onChange={(e) => change("price", e.target.value)}
              />
            </label>
            <label>
              Billing cycle
              <select
                name="billing_cycle"
                value={String(values.billing_cycle)}
                onChange={(e) => change("billing_cycle", e.target.value)}
              >
                <option value="monthly">Monthly</option>
                <option value="yearly">Yearly</option>
                <option value="lifetime">Lifetime</option>
              </select>
            </label>
            <label>
              <input
                name="status"
                type="checkbox"
                checked={values.status === true}
                onChange={(e) => change("status", e.target.checked)}
              />
              Active plan
            </label>
            <p>
              Price and billing cycle are plan settings. Saving does not charge
              anyone or change organisation assignments.
            </p>
          </fieldset>
          <fieldset disabled={busy || !!pending || reloadRequired}>
            <legend>Native capabilities</legend>
            {schema.features.map((key) => (
              <label key={key}>
                <input
                  type="checkbox"
                  name={`feature_${key}`}
                  checked={values[`feature_${key}`] === true}
                  onChange={(e) => change(`feature_${key}`, e.target.checked)}
                />
                {label(key)}
              </label>
            ))}
          </fieldset>
          <fieldset disabled={busy || !!pending || reloadRequired}>
            <legend>Usage limits</legend>
            <p>
              Leave a limit blank for no plan limit; enter zero to allow none.
            </p>
            {schema.limits.map((key) => (
              <label key={key}>
                {label(key)}
                <input
                  name={`limit_${key}`}
                  type="number"
                  min="0"
                  max="1000000000"
                  step="1"
                  value={String(values[`limit_${key}`])}
                  onChange={(e) => change(`limit_${key}`, e.target.value)}
                />
              </label>
            ))}
          </fieldset>
          {pending && (
            <p role="status">
              {plan
                ? "Update is not yet confirmed. Retry the same request."
                : "Creation is not yet confirmed. Retry the same request before creating another plan."}
            </p>
          )}
          {reloadRequired && (
            <p role="status">
              Close this editor and reopen the plan to load current settings.
            </p>
          )}
          <button type="submit" disabled={busy || reloadRequired}>
            {busy
              ? plan
                ? "Saving…"
                : "Creating…"
              : pending
                ? plan
                  ? "Retry plan update"
                  : "Retry plan creation"
                : plan
                  ? "Save plan changes"
                  : "Create plan"}
          </button>
          {plan && (
            <button
              type="button"
              disabled={busy || !!pending}
              onClick={onClose}
            >
              Close editor
            </button>
          )}
        </DraftForm>
      )}
      {error && <p role="alert">{error}</p>}
      {notice && <p role="status">{notice}</p>}
    </section>
  );
}
