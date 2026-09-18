import { useState } from "react";
import { api, ApiError } from "./api";
import { DraftForm } from "./DraftForm";

type Schema = { features: string[]; limits: string[] };
type Values = Record<string, string | boolean>;
type Creation = {
  request_id: string;
  fields: Record<string, string | boolean | number | null>;
};
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

export function ExamPlanCreate({ org }: { org: string }) {
  const [schema, setSchema] = useState<Schema | null>(null);
  const [values, setValues] = useState<Values>({});
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
    if (!schema || busy) return;
    const fields: Creation["fields"] = {};
    for (const [key, value] of Object.entries(values))
      fields[key] = key.startsWith("limit_")
        ? value === ""
          ? null
          : Number(value)
        : key === "name"
          ? String(value).trim()
          : value;
    const body = pending ?? { request_id: crypto.randomUUID(), fields };
    setPending(body);
    setBusy(true);
    setError("");
    setNotice("");
    try {
      const result = await api<{ name: string }>(`${base}/plans`, "POST", body);
      setPending(null);
      setValues(defaults(schema));
      setNotice(
        body.fields.status === false
          ? `Inactive plan “${result.name}” created. It will not appear in active plan choices. Existing assignments are unchanged.`
          : `Plan “${result.name}” created. Load plan choices below to assign it. Existing assignments are unchanged.`,
      );
    } catch (cause) {
      if (
        cause instanceof ApiError &&
        [400, 403, 404, 409].includes(cause.status)
      )
        setPending(null);
      setError(
        cause instanceof Error
          ? cause.message
          : "Creation was not confirmed. Retry the same request.",
      );
    } finally {
      setBusy(false);
    }
  }
  const change = (key: string, value: string | boolean) =>
    setValues((previous) => ({ ...previous, [key]: value }));
  return (
    <section aria-label="Create ExamElite plan">
      <h4>Create exam plan</h4>
      <p>
        Plans are shared across the central exam service. All native
        capabilities start enabled; choose restrictions and limits here. A plan
        permission does not enable an unfinished Tech4Learn tool.
      </p>
      {!schema && (
        <button type="button" disabled={busy} onClick={() => void open()}>
          {busy ? "Loading…" : "New exam plan"}
        </button>
      )}
      {schema && (
        <DraftForm
          draftKey={`exam-plan-create-${org}`}
          title="New exam plan"
          onSubmit={save}
          draftState={{ values, pending }}
          restoreState={(state) => {
            if (busy || pending) return;
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
              JSON.stringify(p.fields).length <= 16384
            )
              setPending({ request_id: p.request_id, fields: p.fields });
          }}
        >
          <fieldset disabled={busy || !!pending}>
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
              Price and billing cycle are plan settings. Creating this plan does
              not charge anyone or assign it to an organisation.
            </p>
          </fieldset>
          <fieldset disabled={busy || !!pending}>
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
          <fieldset disabled={busy || !!pending}>
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
              Creation is not yet confirmed. Retry the same request before
              creating another plan.
            </p>
          )}
          <button type="submit" disabled={busy}>
            {busy
              ? "Creating…"
              : pending
                ? "Retry plan creation"
                : "Create plan"}
          </button>
        </DraftForm>
      )}
      {error && <p role="alert">{error}</p>}
      {notice && <p role="status">{notice}</p>}
    </section>
  );
}
