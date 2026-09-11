import { useEffect, useState, type FormEvent } from "react";
import { api } from "./api";
import { readLearnerFile } from "./learner-import";
type Definition = {
  id: string;
  key: string;
  label: string;
  kind: string;
  required: boolean;
  options: string[];
  archived: boolean;
};
type Group = {
  id: string;
  name: string;
  centre_name: string;
  archived: boolean;
};
type Learner = {
  id: string;
  code: string;
  name: string;
  age: number | null;
  class_label: string;
  guardian_name?: string;
  guardian_phone?: string;
  group_id: string;
  custom_values: Record<string, unknown>;
  archived: boolean;
  demo: boolean;
  version: number;
  group_name?: string;
  history?: {
    id: string;
    group_name: string;
    centre_name: string;
    started_at: string;
    ended_at: string | null;
    reason: string;
  }[];
};
type Preview = {
  id: string | null;
  results: {
    row: number;
    error: string | null;
    warning: string | null;
    value?: { code: string; name: string };
  }[];
};
const fields = (e: FormEvent<HTMLFormElement>) => {
  e.preventDefault();
  return new FormData(e.currentTarget);
};
export function Learners({
  org,
  permissions,
  groups,
}: {
  org: string;
  permissions: string[];
  groups: Group[];
}) {
  const base = `/organisations/${org}`,
    can = (p: string) => permissions.includes(p);
  const [items, setItems] = useState<Learner[]>([]),
    [defs, setDefs] = useState<Definition[]>([]),
    [selected, setSelected] = useState<Learner | null>(null),
    [creating, setCreating] = useState(false),
    [fieldEdit, setFieldEdit] = useState<Definition | null>(null),
    [search, setSearch] = useState(""),
    [offset, setOffset] = useState(0),
    [more, setMore] = useState(false),
    [error, setError] = useState(""),
    [notice, setNotice] = useState(""),
    [busy, setBusy] = useState(false),
    [preview, setPreview] = useState<Preview | null>(null),
    [duplicates, setDuplicates] = useState(false),
    [importGroup, setImportGroup] = useState(""),
    [file, setFile] = useState<File | null>(null),
    [revision, setRevision] = useState(0);
  async function load() {
    const [list, definitions] = await Promise.all([
      api<{ items: Learner[]; hasMore: boolean }>(
        `${base}/learners?search=${encodeURIComponent(search)}&offset=${offset}`,
      ),
      api<Definition[]>(base + "/learner-fields"),
    ]);
    setItems(list.items);
    setMore(list.hasMore);
    setDefs(definitions);
  }
  useEffect(() => {
    let active = true;
    Promise.all([
      api<{ items: Learner[]; hasMore: boolean }>(
        `${base}/learners?search=${encodeURIComponent(search)}&offset=${offset}`,
      ),
      api<Definition[]>(base + "/learner-fields"),
    ])
      .then(([l, d]) => {
        if (active) {
          setItems(l.items);
          setMore(l.hasMore);
          setDefs(d);
        }
      })
      .catch((e) => {
        if (active) setError(e.message);
      });
    return () => {
      active = false;
    };
  }, [org, search, offset]);
  async function act(work: () => Promise<unknown>, message = "Saved.") {
    setBusy(true);
    setError("");
    setNotice("");
    try {
      await work();
      await load();
      setNotice(message);
      setRevision((r) => r + 1);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Unable to connect.");
    } finally {
      setBusy(false);
    }
  }
  function value(f: Definition, raw: unknown) {
    if (raw === "" || raw === null || raw === undefined) return "";
    if (f.kind === "number") {
      const n = Number(raw);
      if (!Number.isFinite(n)) throw new Error(`${f.label} must be a number.`);
      return n;
    }
    if (f.kind === "boolean") {
      if (raw === true || raw === "true") return true;
      if (raw === false || raw === "false") return false;
      throw new Error(`${f.label}: use true or false.`);
    }
    return String(raw);
  }
  const current = selected;
  const editable = current
    ? can("learners.edit") && !current.archived
    : can("learners.create");
  return (
    <div className="learner-workspace">
      <h3>Learners</h3>
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      {notice && <p role="status">{notice}</p>}
      <label>
        Search name or learner code
        <input
          value={search}
          onChange={(e) => {
            setSearch(e.target.value);
            setOffset(0);
          }}
          maxLength={120}
        />
      </label>
      {can("learners.create") && (
        <button
          className="secondary"
          onClick={() => {
            setCreating(true);
            setSelected(null);
          }}
        >
          Add learner
        </button>
      )}
      {!items.length && <p>No learners found in your scope.</p>}
      {items.map((l) => (
        <div className="record" key={l.id}>
          <strong>{l.name}</strong>
          <p>
            {l.code} · {l.group_name}
            {l.demo ? " · DEMO" : ""}
            {l.archived ? " · Archived" : ""}
          </p>
          <button
            className="secondary"
            disabled={busy}
            onClick={() =>
              void act(async () => {
                setSelected(await api<Learner>(`${base}/learners/${l.id}`));
                setCreating(false);
              }, "Profile opened.")
            }
          >
            Open profile
          </button>
        </div>
      ))}
      <div className="actions">
        <button
          disabled={!offset || busy}
          onClick={() => setOffset(Math.max(0, offset - 50))}
        >
          Previous
        </button>
        <button disabled={!more || busy} onClick={() => setOffset(offset + 50)}>
          Next
        </button>
      </div>
      {(creating || current) && (
        <section className="record">
          <h3>{current ? current.name : "New learner"}</h3>
          {current?.demo && <p>Clearly labelled synthetic demo learner.</p>}
          <form
            key={(current?.id || "new") + "-" + revision}
            onSubmit={(e) => {
              const f = fields(e);
              const custom_values = Object.fromEntries(
                defs
                  .filter((d) => !d.archived)
                  .map((d) => [d.key, value(d, f.get(`custom_${d.key}`))]),
              );
              const body = {
                code: f.get("code"),
                name: f.get("name"),
                age: f.get("age"),
                class_label: f.get("class_label"),
                group_id: current?.group_id || f.get("group_id"),
                custom_values,
                ...(can("learners.contacts")
                  ? {
                      guardian_name: f.get("guardian_name"),
                      guardian_phone: f.get("guardian_phone"),
                    }
                  : {}),
                version: current?.version,
                confirmDuplicate: f.get("duplicate") === "on",
              };
              void act(async () => {
                const r = await api<{ id: string }>(
                  `${base}/learners${current ? "/" + current.id : ""}`,
                  current ? "PATCH" : "POST",
                  body,
                );
                setSelected(await api<Learner>(`${base}/learners/${r.id}`));
                setCreating(false);
              });
            }}
          >
            <fieldset disabled={busy || !editable}>
              <label>
                Learner code
                <input
                  name="code"
                  required
                  maxLength={40}
                  defaultValue={
                    current?.code ||
                    `L-${crypto.randomUUID().slice(0, 8).toUpperCase()}`
                  }
                />
              </label>
              <label>
                Name
                <input
                  name="name"
                  required
                  maxLength={120}
                  defaultValue={current?.name}
                />
              </label>
              <div className="form-grid">
                <label>
                  Age (optional)
                  <input
                    name="age"
                    type="number"
                    min={0}
                    max={120}
                    step={1}
                    defaultValue={current?.age ?? ""}
                  />
                </label>
                <label>
                  Class / level
                  <input
                    name="class_label"
                    maxLength={80}
                    defaultValue={current?.class_label}
                  />
                </label>
              </div>
              {!current && (
                <label>
                  Group
                  <select name="group_id" required defaultValue="">
                    <option value="">Choose group</option>
                    {groups
                      .filter((g) => !g.archived)
                      .map((g) => (
                        <option key={g.id} value={g.id}>
                          {g.centre_name} / {g.name}
                        </option>
                      ))}
                  </select>
                </label>
              )}
              {can("learners.contacts") && (
                <div className="form-grid">
                  <label>
                    Guardian name (optional)
                    <input
                      name="guardian_name"
                      defaultValue={current?.guardian_name}
                      maxLength={120}
                    />
                  </label>
                  <label>
                    Guardian phone (optional)
                    <input
                      name="guardian_phone"
                      type="tel"
                      defaultValue={current?.guardian_phone}
                      maxLength={40}
                    />
                  </label>
                </div>
              )}
              {defs
                .filter((d) => !d.archived)
                .map((d) => (
                  <label key={d.id}>
                    {d.label}
                    {d.required ? " *" : ""}
                    {d.kind === "choice" || d.kind === "boolean" ? (
                      <select
                        name={`custom_${d.key}`}
                        required={d.required}
                        defaultValue={String(
                          current?.custom_values[d.key] ?? "",
                        )}
                      >
                        <option value="">Not set</option>
                        {(d.kind === "boolean"
                          ? ["true", "false"]
                          : d.options
                        ).map((v) => (
                          <option key={v}>{v}</option>
                        ))}
                      </select>
                    ) : (
                      <input
                        name={`custom_${d.key}`}
                        type={
                          d.kind === "number"
                            ? "number"
                            : d.kind === "date"
                              ? "date"
                              : "text"
                        }
                        step={d.kind === "number" ? "any" : undefined}
                        maxLength={500}
                        required={d.required}
                        defaultValue={String(
                          current?.custom_values[d.key] ?? "",
                        )}
                      />
                    )}
                  </label>
                ))}
              {editable && (
                <>
                  <label className="check">
                    <input type="checkbox" name="duplicate" />I reviewed any
                    duplicate-name warning and confirm this is a separate
                    learner.
                  </label>
                  <button>Save learner</button>
                </>
              )}
            </fieldset>
          </form>
          {current &&
            defs
              .filter(
                (d) => d.archived && current.custom_values[d.key] !== undefined,
              )
              .map((d) => (
                <p key={d.id}>
                  {d.label} (archived field):{" "}
                  {String(current.custom_values[d.key])}
                </p>
              ))}
          {current && !current.archived && can("learners.transfer") && (
            <form
              onSubmit={(e) => {
                const f = fields(e);
                void act(async () => {
                  await api(`${base}/learners/${current.id}/transfer`, "POST", {
                    group_id: f.get("group_id"),
                    reason: f.get("reason"),
                    version: current.version,
                  });
                  setSelected(
                    await api<Learner>(`${base}/learners/${current.id}`),
                  );
                }, "Enrolment transferred.");
              }}
            >
              <h4>Transfer enrolment</h4>
              <fieldset disabled={busy}>
                <label>
                  New group
                  <select name="group_id" required defaultValue="">
                    <option value="">Choose group</option>
                    {groups
                      .filter((g) => !g.archived && g.id !== current.group_id)
                      .map((g) => (
                        <option key={g.id} value={g.id}>
                          {g.centre_name} / {g.name}
                        </option>
                      ))}
                  </select>
                </label>
                <label>
                  Reason
                  <input name="reason" required maxLength={200} />
                </label>
                <button>Transfer enrolment</button>
              </fieldset>
            </form>
          )}
          {current && (
            <>
              <h4>Enrolment history in your scope</h4>
              {current.history?.map((h) => (
                <p key={h.id}>
                  {h.centre_name} / {h.group_name}
                  <br />
                  {new Date(h.started_at).toLocaleString()} —{" "}
                  {h.ended_at
                    ? new Date(h.ended_at).toLocaleString()
                    : "Current"}{" "}
                  {h.reason}
                </p>
              ))}
            </>
          )}
          {current && !current.archived && can("learners.archive") && (
            <details>
              <summary>Archive learner</summary>
              <p>
                Closes the active enrolment and retains the profile/history.
              </p>
              <button
                disabled={busy}
                onClick={() =>
                  void act(async () => {
                    await api(
                      `${base}/learners/${current.id}/archive`,
                      "POST",
                      { version: current.version },
                    );
                    setSelected(null);
                  }, "Learner archived.")
                }
              >
                Confirm archive
              </button>
            </details>
          )}
          <button
            className="secondary"
            onClick={() => {
              setSelected(null);
              setCreating(false);
            }}
          >
            Close profile
          </button>
        </section>
      )}
      {can("learners.import") && can("learners.create") && (
        <section className="record">
          <h3>Import learners</h3>
          <p>
            CSV or .xlsx, first sheet, up to 100 rows and 1 MB. Required
            headers: code, name. Optional: age, class_label
            {can("learners.contacts") ? ", guardian_name, guardian_phone" : ""}
            {defs
              .filter((d) => !d.archived)
              .map((d) => `, custom_${d.key}`)
              .join("")}
            . Use YYYY-MM-DD dates and true/false for boolean fields. All rows
            go into the group selected below.
          </p>
          <button
            className="secondary"
            onClick={() => {
              const headers = [
                "code",
                "name",
                "age",
                "class_label",
                ...(can("learners.contacts")
                  ? ["guardian_name", "guardian_phone"]
                  : []),
                ...defs
                  .filter((d) => !d.archived)
                  .map((d) => `custom_${d.key}`),
              ];
              const url = URL.createObjectURL(
                new Blob([headers.join(",") + "\r\n"], { type: "text/csv" }),
              );
              const link = document.createElement("a");
              link.href = url;
              link.download = "learner-import-template.csv";
              link.click();
              setTimeout(() => URL.revokeObjectURL(url), 1000);
            }}
          >
            Download CSV template
          </button>
          <label>
            Import group
            <select
              value={importGroup}
              onChange={(e) => {
                setImportGroup(e.target.value);
                setPreview(null);
              }}
            >
              <option value="">Choose group</option>
              {groups
                .filter((g) => !g.archived)
                .map((g) => (
                  <option key={g.id} value={g.id}>
                    {g.centre_name} / {g.name}
                  </option>
                ))}
            </select>
          </label>
          <label>
            File
            <input
              type="file"
              accept=".csv,.xlsx"
              onChange={(e) => {
                setFile(e.target.files?.[0] || null);
                setPreview(null);
                setDuplicates(false);
              }}
            />
          </label>
          <button
            disabled={busy || !file || !importGroup}
            onClick={() =>
              void act(async () => {
                setPreview(null);
                setDuplicates(false);
                const raw = await readLearnerFile(file!);
                const allowed = [
                  "code",
                  "name",
                  "age",
                  "class_label",
                  ...(can("learners.contacts")
                    ? ["guardian_name", "guardian_phone"]
                    : []),
                  ...defs
                    .filter((d) => !d.archived)
                    .map((d) => `custom_${d.key}`),
                ];
                if (Object.keys(raw[0]).some((k) => !allowed.includes(k)))
                  throw new Error("Unknown column. Use the template headers.");
                const rows = raw.map((r) => ({
                  code: String(r.code ?? ""),
                  name: String(r.name ?? ""),
                  age: r.age ?? null,
                  class_label: String(r.class_label ?? ""),
                  ...(can("learners.contacts")
                    ? {
                        guardian_name: String(r.guardian_name ?? ""),
                        guardian_phone: String(r.guardian_phone ?? ""),
                      }
                    : {}),
                  group_id: importGroup,
                  custom_values: Object.fromEntries(
                    defs
                      .filter((d) => !d.archived)
                      .map((d) => [d.key, value(d, r[`custom_${d.key}`])]),
                  ),
                }));
                setPreview(
                  await api<Preview>(
                    base + "/learner-imports/preview",
                    "POST",
                    { rows },
                  ),
                );
              }, "Preview ready. Nothing has been imported yet.")
            }
          >
            Preview import
          </button>
          {preview && (
            <>
              <div className="table-scroll">
                <table>
                  <thead>
                    <tr>
                      <th>Row</th>
                      <th>Code / name</th>
                      <th>Review</th>
                    </tr>
                  </thead>
                  <tbody>
                    {preview.results.map((r) => (
                      <tr key={r.row}>
                        <td>{r.row}</td>
                        <td>
                          {r.value?.code} {r.value?.name}
                        </td>
                        <td>{r.error || r.warning || "Ready"}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              {preview.results.some((r) => r.warning) && (
                <label className="check">
                  <input
                    type="checkbox"
                    checked={duplicates}
                    onChange={(e) => setDuplicates(e.target.checked)}
                  />
                  I reviewed all possible duplicates.
                </label>
              )}
              <button
                disabled={
                  busy ||
                  !preview.id ||
                  preview.results.some((r) => r.error) ||
                  (preview.results.some((r) => r.warning) && !duplicates)
                }
                onClick={() =>
                  void act(async () => {
                    const r = await api<{ count: number }>(
                      `${base}/learner-imports/${preview.id}/commit`,
                      "POST",
                      { confirmDuplicates: duplicates },
                    );
                    setPreview(null);
                    setNotice(`${r.count} learners imported.`);
                  }, "Import completed.")
                }
              >
                Confirm import
              </button>
            </>
          )}
        </section>
      )}
      {can("fields.manage") && (
        <section className="record">
          <h3>Custom learner fields</h3>
          <p>
            Keys and types stay fixed. Existing choices cannot be removed;
            archived fields preserve earlier values.
          </p>
          {defs.map((d) => (
            <p key={d.id}>
              {d.label} · {d.key} · {d.kind}
              {d.archived ? " · Archived" : ""}{" "}
              <button className="secondary" onClick={() => setFieldEdit(d)}>
                Edit field
              </button>
            </p>
          ))}
          <form
            key={(fieldEdit?.id || "field") + revision}
            onSubmit={(e) => {
              const f = fields(e);
              void act(async () => {
                await api(
                  `${base}/learner-fields${fieldEdit ? "/" + fieldEdit.id : ""}`,
                  fieldEdit ? "PATCH" : "POST",
                  {
                    key: f.get("key"),
                    label: f.get("label"),
                    kind: f.get("kind"),
                    required: f.get("required") === "on",
                    archived: f.get("archived") === "on",
                    options: String(f.get("options") || "")
                      .split("\n")
                      .map((s) => s.trim())
                      .filter(Boolean),
                  },
                );
                setFieldEdit(null);
              });
            }}
          >
            <fieldset disabled={busy}>
              <label>
                Label
                <input
                  name="label"
                  required
                  maxLength={80}
                  defaultValue={fieldEdit?.label}
                />
              </label>
              <label>
                Stable key
                <input
                  name="key"
                  required
                  maxLength={40}
                  readOnly={!!fieldEdit}
                  defaultValue={fieldEdit?.key}
                />
              </label>
              <label>
                Type
                <select name="kind" defaultValue={fieldEdit?.kind || "text"}>
                  {["text", "number", "date", "choice", "boolean"].map((v) => (
                    <option key={v}>{v}</option>
                  ))}
                </select>
              </label>
              <label>
                Choices (one per line)
                <textarea
                  name="options"
                  defaultValue={fieldEdit?.options.join("\n")}
                />
              </label>
              <label className="check">
                <input
                  name="required"
                  type="checkbox"
                  defaultChecked={fieldEdit?.required}
                />
                Required for new and edited profiles
              </label>
              <label className="check">
                <input
                  name="archived"
                  type="checkbox"
                  defaultChecked={fieldEdit?.archived}
                />
                Archived
              </label>
              <button>Save field</button>
              <button
                type="button"
                className="secondary"
                onClick={() => setFieldEdit(null)}
              >
                New field
              </button>
            </fieldset>
          </form>
        </section>
      )}
    </div>
  );
}
