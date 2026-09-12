import { DraftForm, useDraftKey } from "./DraftForm";
import { removeDraft } from "./form-drafts";
import { SmartTable } from "./DirectoryTable";
import {
  DirectoryTable,
  RecordStatus,
  emptyTableQuery,
  type TableQuery,
} from "./DirectoryTable";
import { StudentPhotos } from "./StudentPhotos";
import { SectionSelect } from "./SectionSelect";
import type { AcademicGroup } from "./AcademicStructure";
import { useEffect, useState, useRef, useMemo, type FormEvent } from "react";
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
type Group = AcademicGroup;
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
  centre_name?: string;
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
  initialGroup = "",
}: {
  org: string;
  permissions: string[];
  groups: Group[];
  initialGroup?: string;
}) {
  const base = `/organisations/${org}`,
    can = (p: string) => permissions.includes(p);
  const [items, setItems] = useState<Learner[]>([]),
    [defs, setDefs] = useState<Definition[]>([]),
    [selected, setSelected] = useState<Learner | null>(null),
    [creating, setCreating] = useState(false),
    [editorKey, setEditorKey] = useState(0),
    [fieldEdit, setFieldEdit] = useState<Definition | null>(null),
    [tableQuery, setTableQuery] = useState<TableQuery>(emptyTableQuery),
    [counts, setCounts] = useState({ total: 0, filtered: 0 }),
    [listLoading, setListLoading] = useState(false),
    [groupFilter, setGroupFilter] = useState(initialGroup),
    [error, setError] = useState(""),
    [notice, setNotice] = useState(""),
    [busy, setBusy] = useState(false),
    [preview, setPreview] = useState<Preview | null>(null),
    [duplicates, setDuplicates] = useState(false),
    [importGroup, setImportGroup] = useState(initialGroup),
    [file, setFile] = useState<File | null>(null),
    [revision, setRevision] = useState(0);
  const listUrl = `${base}/learners?search=${encodeURIComponent(tableQuery.search)}&offset=${tableQuery.offset}&limit=${tableQuery.limit}&sort=${encodeURIComponent(tableQuery.sort)}&direction=${tableQuery.direction}&filters=${encodeURIComponent(JSON.stringify(tableQuery.filters))}&group_id=${encodeURIComponent(groupFilter)}`;
  async function load() {
    const [list, definitions] = await Promise.all([
      api<{
        items: Learner[];
        hasMore: boolean;
        total: number;
        filtered: number;
      }>(listUrl),
      api<Definition[]>(base + "/learner-fields"),
    ]);
    setItems(list.items);
    setCounts({ total: list.total, filtered: list.filtered });
    setDefs(definitions);
  }
  useEffect(() => {
    let active = true;
    setListLoading(true);
    const timer = setTimeout(
      () =>
        Promise.all([
          api<{
            items: Learner[];
            hasMore: boolean;
            total: number;
            filtered: number;
          }>(listUrl),
          api<Definition[]>(base + "/learner-fields"),
        ])
          .then(([l, d]) => {
            if (active) {
              setItems(l.items);
              setCounts({ total: l.total, filtered: l.filtered });
              setDefs(d);
            }
          })
          .catch((e) => {
            if (active) setError(e.message);
          })
          .finally(() => {
            if (active) setListLoading(false);
          }),
      250,
    );
    return () => {
      active = false;
      clearTimeout(timer);
    };
  }, [listUrl]);
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
  const newCode = useMemo(
    () => `L-${crypto.randomUUID().slice(0, 8).toUpperCase()}`,
    [editorKey],
  );
  const enrolmentForm = useRef<HTMLFormElement>(null),
    photoSave = useRef<{
      save: (studentId?: string) => Promise<void>;
      validate: () => void;
    }>(null);
  const recordDraftKey = useDraftKey(`learner:${current?.id || "new"}`);
  async function saveDetails() {
    const form = enrolmentForm.current;
    if (!form || !form.reportValidity())
      throw new Error("Complete the required student fields first.");
    const f = new FormData(form);
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
    const r = await api<{ id: string }>(
      `${base}/learners${current ? "/" + current.id : ""}`,
      current ? "PATCH" : "POST",
      body,
    );
    if (recordDraftKey) removeDraft(recordDraftKey);
    setSelected(await api<Learner>(`${base}/learners/${r.id}`));
    setCreating(false);
    return r.id;
  }
  const editable = current
    ? can("learners.edit") && !current.archived
    : can("learners.create");
  return (
    <div className="learner-workspace">
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      {notice && <p role="status">{notice}</p>}

      {busy && <p role="status">Saving enrolment… Please wait.</p>}
      <div hidden={creating || !!current}>
        <h3>Students / learners</h3>
        <details open={!!initialGroup}>
          <summary>Filter students by section</summary>
          <p>
            Use centre, year and class to find a section, then select it to
            filter the student list.
          </p>
          <SectionSelect
            groups={groups}
            value={groupFilter}
            initialValue={initialGroup}
            required={false}
            onChange={(id) => {
              setGroupFilter(id);
              setTableQuery((q) => ({ ...q, offset: 0 }));
            }}
          />
        </details>
        {can("learners.create") && (
          <button
            className="secondary"
            onClick={() => {
              setEditorKey((k) => k + 1);
              setCreating(true);
              setSelected(null);
            }}
          >
            Add learner
          </button>
        )}
        {can("learners.create") && (
          <details className="record">
            <summary>Test with a dummy student</summary>
            <p>
              Create or reopen DEMO — Test Student in a selected group. Required
              custom fields receive labelled sample values. No real photograph
              or contact details are added. You can archive the student after
              testing.
            </p>
            <DraftForm
              title="Demo student"
              draftKey={"demo-student"}
              onSubmit={(e) => {
                const f = fields(e);
                void act(async () => {
                  const r = await api<{ id: string }>(
                    `${base}/demo-student`,
                    "POST",
                    { group_id: f.get("group_id") },
                  );
                  setEditorKey((k) => k + 1);
                  setSelected(await api<Learner>(`${base}/learners/${r.id}`));
                  setCreating(false);
                });
              }}
            >
              <SectionSelect
                groups={groups.filter((g) => !g.archived)}
                initialValue={groupFilter}
              />
              <button disabled={busy}>Create / open dummy student</button>
            </DraftForm>
          </details>
        )}
        {!items.length && <p>No learners found in your scope.</p>}
        <DirectoryTable
          title="Student directory"
          columnKeys={[
            "name",
            "code",
            "centre_name",
            "group_name",
            "age",
            "class_label",
            "status",
            ...(can("learners.contacts")
              ? ["guardian_name", "guardian_phone"]
              : []),
            ...defs.map((d) => `custom_${d.key}`),
            "",
          ]}
          onReset={() => setGroupFilter("")}
          remote={{
            query: tableQuery,
            onChange: setTableQuery,
            total: counts.total,
            filtered: counts.filtered,
            loading: listLoading,
          }}
          columns={[
            "Student",
            "Student code",
            "Centre",
            "Class / section",
            "Age",
            "Class / level",
            "Status",
            ...(can("learners.contacts") ? ["Guardian", "Guardian phone"] : []),
            ...defs.map((d) => d.label),
            "Actions",
          ]}
        >
          {items.map((l) => (
            <tr key={l.id}>
              <th scope="row">
                <strong>{l.name}</strong>
                {l.demo && <span className="demo-label">Demo</span>}
              </th>
              <td className="code-cell">{l.code}</td>
              <td>{l.centre_name}</td>
              <td>{l.group_name || "Unassigned"}</td>
              <td>{l.age ?? "—"}</td>
              <td>{l.class_label || "—"}</td>
              <td>
                <RecordStatus archived={l.archived} />
              </td>
              {can("learners.contacts") && (
                <>
                  <td>{l.guardian_name || "—"}</td>
                  <td>{l.guardian_phone || "—"}</td>
                </>
              )}
              {defs.map((d) => (
                <td key={d.id}>{String(l.custom_values?.[d.key] ?? "—")}</td>
              ))}
              <td className="row-actions">
                <button
                  className="secondary"
                  disabled={busy}
                  onClick={() =>
                    void act(async () => {
                      setEditorKey((k) => k + 1);
                      setSelected(
                        await api<Learner>(`${base}/learners/${l.id}`),
                      );
                      setCreating(false);
                    }, "Profile opened.")
                  }
                >
                  Open profile
                </button>
                {can("learners.photos") && (
                  <button
                    className="secondary"
                    disabled={busy}
                    onClick={() =>
                      void act(async () => {
                        setEditorKey((k) => k + 1);
                        setSelected(
                          await api<Learner>(`${base}/learners/${l.id}`),
                        );
                        setCreating(false);
                      }, "Student photo setup opened.")
                    }
                  >
                    Photos & attendance
                  </button>
                )}
              </td>
            </tr>
          ))}
        </DirectoryTable>
      </div>
      {(creating || current) && (
        <section className="record">
          <h3>{current ? current.name : "New learner"}</h3>
          {current?.demo && <p>Clearly labelled synthetic demo learner.</p>}
          <div>
            <DraftForm
              title="Learner enrolment"
              draftKey={`learner:${current?.id || "new"}`}
              key={editorKey}
              formRef={enrolmentForm}
              onSubmit={(e) => {
                e.preventDefault();
                void act(async () => {
                  photoSave.current?.validate();
                  const id = await saveDetails();
                  await photoSave.current?.save(id);
                }, "Enrolment saved. Check the photo section for face-check results.");
              }}
            >
              <fieldset disabled={busy || !editable}>
                <legend>1. Student details</legend>
                <label>
                  Learner code
                  <input
                    name="code"
                    pattern={"[A-Za-z0-9_\\-]+"}
                    title="Use letters, numbers, underscores or hyphens. No spaces."
                    required
                    maxLength={40}
                    defaultValue={current?.code || newCode}
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
                    Class / level (unassigned groups)
                    <input
                      name="class_label"
                      maxLength={80}
                      defaultValue={current?.class_label}
                    />
                    <small>
                      For a linked section, the class is set from its academic
                      structure when saved.
                    </small>
                  </label>
                </div>
                <h4>2. Centre, class and section</h4>
                {current && <p>{current.group_name}</p>}
                {!current && (
                  <SectionSelect
                    groups={groups.filter((g) => !g.archived)}
                    initialValue={groupFilter}
                  />
                )}
                <h4>3. Guardian and additional details</h4>
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
                  </>
                )}
              </fieldset>
              {can("learners.photos") && (
                <fieldset disabled={busy} className="photo-enrolment-section">
                  <StudentPhotos
                    org={org}
                    id={current?.id || ""}
                    permissions={permissions}
                    archived={current?.archived || false}
                    demo={current?.demo || false}
                    ensureStudent={async () => {
                      setBusy(true);
                      try {
                        return await saveDetails();
                      } finally {
                        setBusy(false);
                      }
                    }}
                    saveRef={photoSave}
                  />
                </fieldset>
              )}
              {editable && (
                <div className="form-save-bar">
                  <button disabled={busy}>Save enrolment</button>
                  <span>
                    Student details, section, custom fields and any prepared
                    photo.
                  </span>
                </div>
              )}
            </DraftForm>
            {current &&
              defs
                .filter(
                  (d) =>
                    d.archived && current.custom_values[d.key] !== undefined,
                )
                .map((d) => (
                  <p key={d.id}>
                    {d.label} (archived field):{" "}
                    {String(current.custom_values[d.key])}
                  </p>
                ))}
          </div>
          <div>
            {current && !current.archived && can("learners.transfer") && (
              <DraftForm
                title="Change enrolment"
                draftKey={`transfer:${current?.id}`}
                onSubmit={(e) => {
                  const f = fields(e);
                  void act(async () => {
                    await api(
                      `${base}/learners/${current.id}/transfer`,
                      "POST",
                      {
                        group_id: f.get("group_id"),
                        reason: f.get("reason"),
                        version: current.version,
                      },
                    );
                    setSelected(
                      await api<Learner>(`${base}/learners/${current.id}`),
                    );
                  }, "Enrolment transferred.");
                }}
              >
                <h4>Transfer enrolment</h4>
                <fieldset disabled={busy}>
                  <SectionSelect
                    groups={groups.filter(
                      (g) => !g.archived && g.id !== current.group_id,
                    )}
                  />
                  <label>
                    Reason
                    <input name="reason" required maxLength={200} />
                  </label>
                  <button>Transfer enrolment</button>
                </fieldset>
              </DraftForm>
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
          </div>
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
      {!current &&
        !creating &&
        can("learners.import") &&
        can("learners.create") && (
          <details className="record">
            <summary>Import students from a spreadsheet</summary>
            <p>
              CSV or .xlsx, first sheet, up to 100 rows and 1 MB. Required
              headers: code, name. Optional: age, class_label
              {can("learners.contacts")
                ? ", guardian_name, guardian_phone"
                : ""}
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
            <SectionSelect
              groups={groups.filter((g) => !g.archived)}
              value={importGroup}
              initialValue={initialGroup}
              onChange={(id) => {
                setImportGroup(id);
                setPreview(null);
              }}
            />
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
                    throw new Error(
                      "Unknown column. Use the template headers.",
                    );
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
                  <SmartTable>
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
                  </SmartTable>
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
          </details>
        )}
      {can("fields.manage") && (
        <details className="record" hidden={!!current || creating}>
          <summary>Configure custom student fields</summary>
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
          <DraftForm
            title="Custom learner field"
            draftKey={`learner-field:${fieldEdit?.id || "new"}`}
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
          </DraftForm>
        </details>
      )}
    </div>
  );
}
