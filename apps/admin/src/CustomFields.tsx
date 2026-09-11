import { useEffect, useState } from "react";
import { api } from "./api";
type Definition = {
  id: string;
  key: string;
  label: string;
  kind: string;
  required: boolean;
  archived: boolean;
  options: string[];
};
type RecordChoice = { id: string; name: string };
const modules = [
  "organisation",
  "centres",
  "groups",
  "staff",
  "learners",
  "attendance",
  "fln",
  "exams",
];
export function CustomFields({
  org,
  permissions,
  records,
}: {
  org: string;
  permissions: string[];
  records: Record<string, RecordChoice[]>;
}) {
  const can = (p: string) => permissions.includes(p);
  const allowed = modules.filter(
    (m) =>
      can("fields.view") ||
      can("fields.manage") ||
      can(
        (
          { organisation: "organisation", staff: "members" } as Record<
            string,
            string
          >
        )[m] + ".view",
      ) ||
      can(m + ".view"),
  );
  const [module, setModule] = useState(allowed[0] || "organisation"),
    [defs, setDefs] = useState<Definition[]>([]),
    [edit, setEdit] = useState<Definition | null>(null),
    [record, setRecord] = useState(""),
    [values, setValues] = useState<Record<string, unknown>>({}),
    [version, setVersion] = useState(0),
    [error, setError] = useState(""),
    [notice, setNotice] = useState(""),
    [busy, setBusy] = useState(false),
    [revision, setRevision] = useState(0);
  const base = `/organisations/${org}`;
  useEffect(() => {
    let active = true;
    setDefs([]);
    setEdit(null);
    setRecord("");
    setError("");
    api<Definition[]>(`${base}/fields/${module}`)
      .then((d) => {
        if (active) setDefs(d);
      })
      .catch((e) => {
        if (active) setError(e.message);
      });
    return () => {
      active = false;
    };
  }, [org, module, revision]);
  useEffect(() => {
    let active = true;
    setValues({});
    setVersion(0);
    if (record)
      api<{ values: Record<string, unknown>; version: number }>(
        `${base}/field-values/${module}/${record}`,
      )
        .then((d) => {
          if (active) {
            setValues(d.values);
            setVersion(d.version);
          }
        })
        .catch((e) => {
          if (active) setError(e.message);
        });
    return () => {
      active = false;
    };
  }, [record, module, org]);
  async function act(work: () => Promise<unknown>, reload = true) {
    setBusy(true);
    setError("");
    setNotice("");
    try {
      await work();
      setNotice("Saved.");
      if (reload) setRevision((v) => v + 1);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Unable to save.");
    } finally {
      setBusy(false);
    }
  }
  const write = can(
    (
      {
        organisation: "organisation.edit",
        centres: "centres.edit",
        groups: "groups.edit",
        staff: "members.manage",
      } as Record<string, string>
    )[module],
  );
  return (
    <div className="setup-stack">
      <h3>Custom fields across modules</h3>
      <p>
        Each module has its own fields. Stable keys and types preserve earlier
        records; archive a field when it is no longer needed.
      </p>
      <label>
        Module
        <select value={module} onChange={(e) => setModule(e.target.value)}>
          {allowed.map((m) => (
            <option key={m}>{m}</option>
          ))}
        </select>
      </label>
      {error && (
        <p role="alert" className="error">
          {error}
        </p>
      )}
      {notice && <p role="status">{notice}</p>}
      {["attendance", "fln", "exams"].includes(module) && (
        <p>
          These are draft field definitions for a planned module. No assessment,
          attendance or ExamElite record is created here.
        </p>
      )}
      {defs.map((f) => (
        <div key={f.id} className="record-row">
          <strong>{f.label}</strong>
          <p>
            {f.key} · {f.kind}
            {f.required ? " · Required" : ""}
            {f.archived ? " · Archived" : ""}
          </p>
          {can("fields.manage") && (
            <button className="secondary" onClick={() => setEdit(f)}>
              Edit field
            </button>
          )}
        </div>
      ))}
      {can("fields.manage") && (
        <form
          key={`${module}-${edit?.id || "new"}-${revision}`}
          onSubmit={(e) => {
            e.preventDefault();
            const f = new FormData(e.currentTarget);
            const b = {
              key: f.get("key"),
              label: f.get("label"),
              kind: f.get("kind"),
              required: f.has("required"),
              archived: f.has("archived"),
              options: String(f.get("options") || "")
                .split("\n")
                .map((v) => v.trim())
                .filter(Boolean),
            };
            void act(() =>
              api(
                `${base}/fields/${module}${edit ? "/" + edit.id : ""}`,
                edit ? "PATCH" : "POST",
                b,
              ),
            );
          }}
        >
          <fieldset disabled={busy}>
            <h4>{edit ? "Edit field" : "Add field"}</h4>
            <label>
              Label
              <input
                name="label"
                required
                maxLength={80}
                defaultValue={edit?.label}
              />
            </label>
            <label>
              Stable key
              <input
                name="key"
                required
                maxLength={40}
                pattern="[a-z][a-z0-9_]*"
                readOnly={!!edit}
                defaultValue={edit?.key}
              />
            </label>
            <label>
              Type
              <select name="kind" defaultValue={edit?.kind || "text"}>
                {["text", "number", "date", "choice", "boolean"].map((k) => (
                  <option key={k}>{k}</option>
                ))}
              </select>
            </label>
            <label>
              Choices (one per line)
              <textarea
                name="options"
                defaultValue={edit?.options.join("\n")}
              />
            </label>
            <label className="check">
              <input
                name="required"
                type="checkbox"
                defaultChecked={edit?.required}
              />
              Required when saving these details
            </label>
            <label className="check">
              <input
                name="archived"
                type="checkbox"
                defaultChecked={edit?.archived}
              />
              Archived
            </label>
            <button>Save field</button>
            <button
              type="button"
              className="secondary"
              onClick={() => setEdit(null)}
            >
              New field
            </button>
          </fieldset>
        </form>
      )}
      {module === "learners" ? (
        <p>
          Learner field values are entered in Learners, including during
          imports.
        </p>
      ) : (
        records[module] && (
          <section className="subpanel">
            <h4>Additional record details</h4>
            <p>
              Choose an existing record. Required fields are checked when saving
              these additional details.
            </p>
            <label>
              Record
              <select
                value={record}
                onChange={(e) => setRecord(e.target.value)}
              >
                <option value="">Choose record</option>
                {records[module].map((r) => (
                  <option value={r.id} key={r.id}>
                    {r.name}
                  </option>
                ))}
              </select>
            </label>
            {record && (
              <form
                onSubmit={(e) => {
                  e.preventDefault();
                  void act(async () => {
                    const result = await api<{
                      values: Record<string, unknown>;
                      version: number;
                    }>(`${base}/field-values/${module}/${record}`, "PATCH", {
                      values,
                      version,
                    });
                    setValues(result.values);
                    setVersion(result.version);
                  }, false);
                }}
              >
                <fieldset disabled={busy || !write}>
                  {defs.map((f) => (
                    <label key={f.id}>
                      {f.label}
                      {f.required ? " *" : ""}
                      {f.archived ? (
                        <input readOnly value={String(values[f.key] ?? "")} />
                      ) : f.kind === "choice" || f.kind === "boolean" ? (
                        <select
                          required={f.required}
                          value={String(values[f.key] ?? "")}
                          onChange={(e) =>
                            setValues({
                              ...values,
                              [f.key]:
                                f.kind === "boolean" && e.target.value !== ""
                                  ? e.target.value === "true"
                                  : e.target.value,
                            })
                          }
                        >
                          <option value="">Not set</option>
                          {(f.kind === "boolean"
                            ? ["true", "false"]
                            : f.options
                          ).map((v) => (
                            <option key={v}>{v}</option>
                          ))}
                        </select>
                      ) : (
                        <input
                          required={f.required}
                          type={
                            f.kind === "number"
                              ? "number"
                              : f.kind === "date"
                                ? "date"
                                : "text"
                          }
                          step="any"
                          maxLength={500}
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
                  <button>Save additional details</button>
                </fieldset>
              </form>
            )}
          </section>
        )
      )}
    </div>
  );
}
