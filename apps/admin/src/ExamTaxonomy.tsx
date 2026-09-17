import { useEffect, useState } from "react";
import { api, apiBase } from "./api";
import { QuestionImageUpload } from "./QuestionImageUpload";
import { DraftForm } from "./DraftForm";
import { SmartTable } from "./DirectoryTable";
import {
  QuestionChoiceField,
  type QuestionChoice,
} from "./QuestionChoiceField";
const labels = {
  packages: "Exam packages",
  languages: "Languages",
  categories: "Categories",
  subcategories: "Subcategories",
  groups: "Exam groups",
  subjects: "Subjects",
  topics: "Topics",
  subtopics: "Subtopics",
  sections: "Question sections",
};
type Kind = keyof typeof labels;
const centralKinds: Kind[] = [
  "packages",
  "categories",
  "subcategories",
  "groups",
  "subjects",
  "topics",
  "subtopics",
  "sections",
];
type RecordData = {
  id: number;
  revision: string;
  fields: Record<string, any>;
  photo_asset?: string | null;
};
function PackagePhoto({
  org,
  central = false,
  id,
  asset,
}: {
  org: string;
  central?: boolean;
  id: number;
  asset: string;
}) {
  const [failed, setFailed] = useState(false);
  if (!/^[a-f0-9]{64}$/.test(asset)) return null;
  return (
    <section aria-label="Current package image">
      {failed ? (
        <div>
          <p role="alert">
            The package image could not be loaded. Retry, or reload the package
            to check for a changed image.
          </p>
          <button type="button" onClick={() => setFailed(false)}>
            Retry package image
          </button>
        </div>
      ) : (
        <img
          src={`${apiBase}${central ? `/platform/exam-content/${org}/central` : `/organisations/${org}/exam-content`}/packages/${id}/media/${asset}`}
          alt="Current package image"
          style={{ maxWidth: "100%", maxHeight: 240, objectFit: "contain" }}
          onError={() => setFailed(true)}
        />
      )}
    </section>
  );
}
function TaxonomyEditor({
  org,
  central = false,
  kind,
  id,
  onClose,
}: {
  org: string;
  central?: boolean;
  kind: Kind;
  id: number | "new";
  onClose: () => void;
}) {
  const [activeId, setActiveId] = useState<number | "new">(id);
  const [record, setRecord] = useState<RecordData | null>(null),
    [changes, setChanges] = useState<Record<string, any>>({}),
    [busy, setBusy] = useState(false),
    [error, setError] = useState(""),
    [request, setRequest] = useState<string | null>(null),
    [pendingDisable, setPendingDisable] = useState<{
      fields: Record<string, never>;
      revision: string;
      request_id: string;
    } | null>(null),
    [notice, setNotice] = useState(""),
    [newTag, setNewTag] = useState(""),
    [imagePending, setImagePending] = useState(false),
    [imageVersion, setImageVersion] = useState(0);
  const [confirmDelete, setConfirmDelete] = useState(false);
  const [pendingDelete, setPendingDelete] = useState<{
    revision: string;
    request_id: string;
  } | null>(null);
  const base = `${central ? `/platform/exam-content/${org}/central` : `/organisations/${org}/exam-content`}/taxonomy/${kind}`;
  async function load() {
    setBusy(true);
    setError("");
    setNotice("");
    setPendingDelete(null);
    setConfirmDelete(false);
    const recordId = activeId;
    setRecord(null);
    try {
      setRecord(await api<RecordData>(`${base}/${recordId}`));
      setChanges({});
      setNewTag("");
      setRequest(null);
      setPendingDisable(null);
      setImagePending(false);
      setImageVersion((v) => v + 1);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Unable to load record.");
    } finally {
      setBusy(false);
    }
  }
  useEffect(() => {
    void load();
  }, [org, central, kind, id]);
  const set = (key: string, value: any) => {
    if (pendingDisable || pendingDelete || imagePending) return;
    setChanges((old) => ({ ...old, [key]: value }));
    setRequest(null);
    setNotice("");
  };
  const values = { ...record?.fields, ...changes };
  const name =
    kind === "groups"
      ? "group_name"
      : kind === "subjects"
        ? "subject_name"
        : kind === "categories" || kind === "subcategories"
          ? "title"
          : "name";
  return (
    <section className="panel">
      <h3>
        {kind === "languages"
          ? record?.id
            ? "Edit language labels"
            : "Enable language"
          : `${activeId !== "new" ? "Edit" : "Create"} ${labels[kind].toLowerCase()}`}
      </h3>
      <button
        className="secondary"
        disabled={
          busy ||
          Boolean(pendingDisable) ||
          Boolean(pendingDelete) ||
          imagePending
        }
        onClick={onClose}
      >
        Back to classification
      </button>
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      {notice && <p role="status">{notice}</p>}
      {!record && !busy && error && (
        <button type="button" onClick={() => void load()}>
          Retry loading classification
        </button>
      )}
      {record && (
        <DraftForm
          key={`${central}-${org}-${kind}-${record.id || "new"}`}
          draftKey={`exam-taxonomy-${central ? `central-${org}-` : ""}${kind}-${record.id || "new"}`}
          title="Details"
          draftState={{ revision: record.revision, changes, request, newTag }}
          restoreState={(s) => {
            if (pendingDisable || pendingDelete || imagePending) return;
            if (
              s?.revision === record.revision &&
              s.changes &&
              typeof s.changes === "object" &&
              !Array.isArray(s.changes)
            ) {
              setChanges(s.changes);
              setNewTag(
                kind === "packages" && typeof s.newTag === "string"
                  ? s.newTag
                  : "",
              );
              setRequest(typeof s.request === "string" ? s.request : null);
            } else
              setError(
                "This draft belongs to an older version. Reload the record before editing.",
              );
          }}
          onSubmit={async (e) => {
            e.preventDefault();
            if (pendingDisable || pendingDelete || imagePending)
              throw new Error("Resolve the pending action before editing.");
            if (kind === "packages" && newTag.trim()) {
              setError(
                "Add the new tag to the selection, or clear its name before saving.",
              );
              return;
            }
            setBusy(true);
            setError("");
            const requestId = request ?? crypto.randomUUID();
            setRequest(requestId);
            try {
              const saved = await api<RecordData>(
                `${base}/${record.id || "new"}`,
                "POST",
                {
                  fields: record.id ? changes : values,
                  revision: record.revision,
                  request_id: requestId,
                },
              );
              setRecord(saved);
              setActiveId(saved.id);
              setChanges({});
              setRequest(null);
              setNotice(
                central
                  ? "Shared original classification saved."
                  : "Classification saved in your organisation.",
              );
            } catch (e) {
              setError(e instanceof Error ? e.message : "Unable to save.");
              throw e;
            } finally {
              setBusy(false);
            }
          }}
        >
          <fieldset
            data-no-draft="true"
            disabled={
              busy ||
              Boolean(pendingDisable) ||
              Boolean(pendingDelete) ||
              imagePending
            }
            aria-label="Classification fields"
          >
            {kind !== "languages" && (
              <label>
                Name
                <input
                  required
                  maxLength={kind === "sections" ? 191 : 255}
                  value={values[name] ?? ""}
                  disabled={busy}
                  onChange={(e) => set(name, e.target.value)}
                />
              </label>
            )}
            {kind === "languages" && !record.id && (
              <QuestionChoiceField
                central={central}
                org={org}
                kind="platform-languages"
                label="Central language to enable"
                required
                value={values.master_language_id ?? null}
                disabled={busy}
                onChange={(v) => set("master_language_id", v)}
              />
            )}
            {kind === "languages" && Boolean(record.id) && (
              <>
                <p>
                  {values.name} ({values.code})
                </p>
                {[
                  ["value1", "True label"],
                  ["value2", "False label"],
                ].map(([key, label]) => (
                  <label key={key}>
                    {label}
                    <input
                      value={values[key] ?? ""}
                      disabled={busy}
                      onChange={(e) => set(key, e.target.value)}
                    />
                  </label>
                ))}
              </>
            )}
            {kind === "languages" && (
              <p>
                Language names and codes are managed centrally. Enabling a
                language preserves its existing questions and translations.
              </p>
            )}
            {kind !== "groups" &&
              kind !== "subcategories" &&
              kind !== "languages" && (
                <QuestionChoiceField
                  central={central}
                  org={org}
                  kind="groups"
                  label="Exam group"
                  required={kind !== "categories"}
                  multiple={[
                    "subjects",
                    "sections",
                    "categories",
                    "packages",
                  ].includes(kind)}
                  value={
                    ["subjects", "sections", "categories", "packages"].includes(
                      kind,
                    )
                      ? (values.group_ids ?? [])
                      : (values.group_id ?? null)
                  }
                  disabled={busy}
                  onChange={(v) =>
                    set(
                      [
                        "subjects",
                        "sections",
                        "categories",
                        "packages",
                      ].includes(kind)
                        ? "group_ids"
                        : "group_id",
                      v,
                    )
                  }
                />
              )}
            {["topics", "subtopics"].includes(kind) && (
              <QuestionChoiceField
                central={central}
                org={org}
                kind="subjects"
                label="Subject"
                required
                value={values.subject_id ?? null}
                disabled={busy}
                onChange={(v) => set("subject_id", v)}
              />
            )}
            {kind === "subtopics" && (
              <QuestionChoiceField
                central={central}
                org={org}
                kind="topics"
                label="Topic"
                required
                value={values.topic_id ?? null}
                disabled={busy}
                onChange={(v) => set("topic_id", v)}
              />
            )}
            {kind !== "subjects" && kind !== "languages" && (
              <label>
                Display order
                <input
                  type="number"
                  min="0"
                  step="1"
                  value={values.display_order ?? 0}
                  disabled={busy}
                  onChange={(e) => set("display_order", Number(e.target.value))}
                />
              </label>
            )}
            {kind === "subcategories" && (
              <QuestionChoiceField
                central={central}
                org={org}
                kind="categories"
                label="Parent category"
                required
                value={values.parent_id ?? null}
                disabled={busy}
                onChange={(v) => set("parent_id", v)}
              />
            )}
            {kind === "subcategories" && (
              <p>
                Subcategories inherit their parent category’s exam groups.
                Saving follows the configured ExamElite subcategory setting.
              </p>
            )}
            {(kind === "categories" ||
              kind === "subcategories" ||
              kind === "packages") && (
              <label>
                Description
                <textarea
                  value={values.description ?? ""}
                  disabled={busy}
                  maxLength={10000}
                  onChange={(e) => set("description", e.target.value)}
                />
              </label>
            )}
            {kind === "categories" && (
              <p>
                No selected groups means this category is available to every
                exam group in this catalogue. Existing header and search
                metadata are preserved.
              </p>
            )}
            {kind === "packages" && (
              <>
                {record.id > 0 && typeof record.photo_asset === "string" && (
                  <PackagePhoto
                    central={central}
                    key={record.photo_asset}
                    org={org}
                    id={record.id}
                    asset={record.photo_asset}
                  />
                )}
                <p>
                  Package type: {values.package_type}.{" "}
                  {central
                    ? "Paid package editing is still being integrated."
                    : "Paid packages are managed centrally. Assign exams from the exam editor."}{" "}
                  Existing exam links and ordering are preserved here.
                </p>
                <QuestionChoiceField
                  central={central}
                  org={org}
                  kind="categories"
                  label="Category"
                  value={values.category_level_1 ?? null}
                  disabled={busy}
                  onChange={(v) => {
                    set("category_level_1", v);
                    set("category_level_2", null);
                  }}
                />
                <QuestionChoiceField
                  central={central}
                  org={org}
                  kind="subcategories"
                  label="Subcategory"
                  value={values.category_level_2 ?? null}
                  parentId={
                    values.category_level_1
                      ? Number(values.category_level_1)
                      : null
                  }
                  disabled={busy || !values.category_level_1}
                  onChange={(v) => set("category_level_2", v)}
                />
                <QuestionChoiceField
                  central={central}
                  org={org}
                  kind="package-tags"
                  label="Package tags"
                  multiple
                  value={(values.tag_ids ?? [])
                    .filter((tag: string) => /^\d+$/.test(String(tag)))
                    .map(Number)}
                  disabled={busy}
                  onChange={(v: number[]) =>
                    set("tag_ids", [
                      ...v.map(String),
                      ...(values.tag_ids ?? []).filter(
                        (tag: string) => !/^\d+$/.test(String(tag)),
                      ),
                    ])
                  }
                />
                <label>
                  New package tag
                  <input
                    maxLength={60}
                    value={newTag}
                    disabled={busy}
                    onChange={(event) => {
                      setNewTag(event.target.value);
                      setRequest(null);
                    }}
                  />
                </label>
                <button
                  type="button"
                  disabled={busy || !newTag.trim()}
                  onClick={() => {
                    const tag = newTag.trim();
                    if (!Number.isNaN(Number(tag)) || /[<>]/.test(tag)) {
                      setError(
                        "Use a tag name containing words, without HTML.",
                      );
                      return;
                    }
                    set("tag_ids", [
                      ...new Set([...(values.tag_ids ?? []), tag]),
                    ]);
                    setNewTag("");
                    setError("");
                  }}
                >
                  Add tag to selection
                </button>
                <p>
                  New tags are created in this catalogue when you save the
                  package.
                </p>
                {(values.tag_ids ?? [])
                  .filter((tag: string) => !/^\d+$/.test(String(tag)))
                  .map((tag: string) => (
                    <p key={tag}>
                      {tag}{" "}
                      <button
                        type="button"
                        disabled={busy}
                        onClick={() =>
                          set(
                            "tag_ids",
                            values.tag_ids.filter(
                              (item: string) => item !== tag,
                            ),
                          )
                        }
                      >
                        Remove new tag {tag}
                      </button>
                    </p>
                  ))}
                <label>
                  Access duration (days; blank means no expiry)
                  <input
                    type="number"
                    min="1"
                    step="1"
                    value={values.expiry_days ?? ""}
                    disabled={busy}
                    onChange={(e) =>
                      set(
                        "expiry_days",
                        e.target.value === "" ? null : Number(e.target.value),
                      )
                    }
                  />
                </label>
                <details>
                  <summary>Paper and solution PDF settings</summary>
                  <p>
                    These settings apply when ExamElite generates this package's
                    documents. Downloading them inside Tech4Learn is still being
                    integrated.
                  </p>
                  {[
                    ["pdf", "Question paper", "show_pdf_download"],
                    ["solution_pdf", "Solutions", "show_solution_pdf_download"],
                  ].map(([prefix, title, flag]) => (
                    <fieldset key={prefix}>
                      <legend>{title}</legend>
                      <label>
                        <input
                          type="checkbox"
                          checked={Boolean(values[flag])}
                          onChange={(e) => set(flag, e.target.checked)}
                        />
                        Allow {title.toLowerCase()} PDF download
                      </label>
                      {["title", "header", "footer", "watermark"].map(
                        (part) => {
                          const field = `${prefix}_${part}_text`;
                          return (
                            <label key={field}>
                              {title} {part}
                              <input
                                type="text"
                                maxLength={part === "footer" ? 500 : 255}
                                value={values[field] ?? ""}
                                onChange={(e) => set(field, e.target.value)}
                              />
                            </label>
                          );
                        },
                      )}
                    </fieldset>
                  ))}
                </details>
              </>
            )}
            {["sections", "categories", "subcategories", "packages"].includes(
              kind,
            ) && (
              <label>
                <input
                  type="checkbox"
                  checked={Boolean(values.status)}
                  disabled={busy}
                  onChange={(e) => set("status", e.target.checked)}
                />
                Active{" "}
                {kind === "categories"
                  ? "category"
                  : kind === "subcategories"
                    ? "subcategory"
                    : kind === "packages"
                      ? "package"
                      : "section"}
              </label>
            )}
            <button
              disabled={busy || (record.id > 0 && !Object.keys(changes).length)}
            >
              {kind === "languages"
                ? record.id
                  ? "Save language labels"
                  : "Enable language"
                : "Save classification"}
            </button>
          </fieldset>
          <button
            className="secondary"
            type="button"
            disabled={busy}
            onClick={() => void load()}
          >
            Reload saved classification
          </button>
        </DraftForm>
      )}
      {kind === "packages" &&
        record &&
        record.id > 0 &&
        record.fields.package_type === "free" && (
          <QuestionImageUpload
            key={`${record.revision}-${imageVersion}`}
            kind="package"
            central={central}
            base={`${central ? `/platform/exam-content/${org}/central` : `/organisations/${org}/exam-content`}/packages/${record.id}`}
            record={record}
            disabled={
              busy ||
              Object.keys(changes).length > 0 ||
              Boolean(request) ||
              Boolean(newTag.trim())
            }
            onPending={setImagePending}
            onReload={() => void load()}
            onSaved={(saved) => {
              setRecord(saved);
              setImagePending(false);
              setNotice("Package image saved.");
            }}
          />
        )}
      {kind === "languages" && Boolean(record?.id) && (
        <div>
          <p>
            {record?.fields.is_enabled
              ? "Disabling removes this language from new selection lists. Existing language records, questions and translations are retained."
              : "This language is disabled. Use Enable language from the classification list to enable it again."}
          </p>
          {record?.fields.is_enabled && (
            <button
              type="button"
              className="secondary"
              disabled={
                busy || Boolean(request) || Object.keys(changes).length > 0
              }
              onClick={async () => {
                if (!record) return;
                const body = pendingDisable ?? {
                  fields: {},
                  revision: record.revision,
                  request_id: crypto.randomUUID(),
                };
                setPendingDisable(body);
                setBusy(true);
                setError("");
                setNotice("");
                try {
                  const saved = await api<RecordData>(
                    `${base}/${record.id}/disable`,
                    "POST",
                    body,
                  );
                  setRecord(saved);
                  setPendingDisable(null);
                  setNotice("Language disabled. Existing content is retained.");
                } catch (e) {
                  setError(
                    e instanceof Error
                      ? e.message
                      : "Unable to disable language. Retry or reload its saved state.",
                  );
                } finally {
                  setBusy(false);
                }
              }}
            >
              {pendingDisable ? "Retry disabling language" : "Disable language"}
            </button>
          )}
        </div>
      )}
      {["categories", "subcategories"].includes(kind) &&
        record &&
        record.id > 0 && (
          <section aria-label="Delete classification" data-no-draft="true">
            <p>
              Delete this {kind === "categories" ? "category" : "subcategory"}{" "}
              only if it is no longer needed. ExamElite prevents deletion while
              child categories, exams, packages or flashcards use it.
            </p>
            <label>
              <input
                type="checkbox"
                checked={confirmDelete}
                disabled={busy || Boolean(pendingDelete)}
                onChange={(event) => setConfirmDelete(event.target.checked)}
              />
              Delete “{record.fields.title}” permanently
            </label>
            <button
              type="button"
              className="secondary"
              disabled={
                busy ||
                Boolean(request) ||
                Object.keys(changes).length > 0 ||
                (!pendingDelete && !confirmDelete)
              }
              onClick={async () => {
                const payload = pendingDelete ?? {
                  revision: record.revision,
                  request_id: crypto.randomUUID(),
                };
                setPendingDelete(payload);
                setBusy(true);
                setError("");
                setNotice("");
                try {
                  const result = await api<{ id: number; deleted: boolean }>(
                    `${base}/${record.id}/delete`,
                    "POST",
                    payload,
                  );
                  if (result.id !== record.id || result.deleted !== true)
                    throw new Error(
                      "Deletion could not be confirmed. Retry or reload.",
                    );
                  setRecord(null);
                  setPendingDelete(null);
                  setConfirmDelete(false);
                  setNotice(
                    "Classification deleted. Return to the list to load the remaining records.",
                  );
                } catch (e) {
                  setError(
                    e instanceof Error
                      ? e.message
                      : "Unable to confirm deletion. Retry or reload.",
                  );
                } finally {
                  setBusy(false);
                }
              }}
            >
              {pendingDelete ? "Retry deletion" : "Delete classification"}
            </button>
            {Object.keys(changes).length > 0 && (
              <p>
                Save your edits or reload the saved classification before
                deleting.
              </p>
            )}
          </section>
        )}
    </section>
  );
}
export function ExamTaxonomy({
  org,
  central = false,
}: {
  org: string;
  central?: boolean;
}) {
  const base = central
    ? `/platform/exam-content/${org}/central`
    : `/organisations/${org}/exam-content`;
  const [kind, setKind] = useState<Kind>("groups"),
    [editing, setEditing] = useState<number | "new" | null>(null),
    [items, setItems] = useState<QuestionChoice[]>([]),
    [search, setSearch] = useState(""),
    [loaded, setLoaded] = useState(""),
    [next, setNext] = useState<number | null>(null),
    [busy, setBusy] = useState(false),
    [error, setError] = useState("");
  async function load(after = 0) {
    setBusy(true);
    setError("");
    try {
      if (!after) await api(`${base}/taxonomy/${kind}/new`);
      const term = after ? loaded : search;
      const r = await api<{ items: QuestionChoice[]; next: number | null }>(
        `${base}/choices/${kind}?search=${encodeURIComponent(term)}&after=${after}`,
      );
      setItems((old) => (after ? [...old, ...r.items] : r.items));
      setNext(r.next);
      setLoaded(term);
    } catch (e) {
      setError(
        e instanceof Error ? e.message : "Unable to load classification.",
      );
    } finally {
      setBusy(false);
    }
  }
  if (editing !== null)
    return (
      <TaxonomyEditor
        key={`${central}-${org}-${kind}-${editing}`}
        org={org}
        central={central}
        kind={kind}
        id={editing}
        onClose={() => {
          setEditing(null);
          setItems([]);
          setNext(null);
        }}
      />
    );
  return (
    <section className="panel">
      <h3>
        {central
          ? "Shared classification originals"
          : "Subjects, topics and sections"}
      </h3>
      {central && (
        <p>
          Manage central free packages, categories, exam groups, subjects,
          topics, subtopics and question sections. Organisation-owned copies
          keep their own versions.
        </p>
      )}
      <label>
        Classification
        <select
          value={kind}
          disabled={busy}
          onChange={(e) => {
            setKind(e.target.value as Kind);
            setItems([]);
            setNext(null);
            setError("");
          }}
        >
          {Object.entries(labels)
            .filter(
              ([value]) => !central || centralKinds.includes(value as Kind),
            )
            .map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
        </select>
      </label>
      <button disabled={busy} onClick={() => setEditing("new")}>
        {kind === "languages"
          ? "Enable language"
          : `Create ${labels[kind].toLowerCase()}`}
      </button>
      <form
        onSubmit={(e) => {
          e.preventDefault();
          void load();
        }}
      >
        <label>
          Search names
          <input
            value={search}
            maxLength={120}
            disabled={busy}
            onChange={(e) => setSearch(e.target.value)}
          />
        </label>
        <button disabled={busy}>Load classification</button>
      </form>
      {error && (
        <p role="alert" className="error">
          {error}
        </p>
      )}
      <SmartTable>
        <caption>
          {labels[kind]} — {items.length} loaded
        </caption>
        <thead>
          <tr>
            <th>Name</th>
            <th>ID</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          {items.map((i) => (
            <tr key={i.id}>
              <td>{i.label}</td>
              <td>{i.id}</td>
              <td>
                <button disabled={busy} onClick={() => setEditing(i.id)}>
                  Edit
                </button>
              </td>
            </tr>
          ))}
        </tbody>
      </SmartTable>
      {next !== null && (
        <button
          className="secondary"
          disabled={busy}
          onClick={() => void load(next)}
        >
          Load next 100
        </button>
      )}
    </section>
  );
}
