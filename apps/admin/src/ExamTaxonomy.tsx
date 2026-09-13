import { useEffect, useState } from "react";
import { api } from "./api";
import { DraftForm } from "./DraftForm";
import { SmartTable } from "./DirectoryTable";
import {
  QuestionChoiceField,
  type QuestionChoice,
} from "./QuestionChoiceField";
const labels = {
  groups: "Exam groups",
  subjects: "Subjects",
  topics: "Topics",
  subtopics: "Subtopics",
  sections: "Question sections",
};
type Kind = keyof typeof labels;
type RecordData = { id: number; revision: string; fields: Record<string, any> };
function TaxonomyEditor({
  org,
  kind,
  id,
  onClose,
}: {
  org: string;
  kind: Kind;
  id: number | "new";
  onClose: () => void;
}) {
  const [record, setRecord] = useState<RecordData | null>(null),
    [changes, setChanges] = useState<Record<string, any>>({}),
    [busy, setBusy] = useState(false),
    [error, setError] = useState(""),
    [request, setRequest] = useState<string | null>(null),
    [notice, setNotice] = useState("");
  const base = `/organisations/${org}/exam-content/taxonomy/${kind}`;
  async function load() {
    setBusy(true);
    setError("");
    try {
      setRecord(await api<RecordData>(`${base}/${record?.id || id}`));
      setChanges({});
      setRequest(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Unable to load record.");
    } finally {
      setBusy(false);
    }
  }
  useEffect(() => {
    void load();
  }, [org, kind, id]);
  const set = (key: string, value: any) => {
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
        : "name";
  return (
    <section className="panel">
      <h3>
        {record?.id ? "Edit" : "Create"} {labels[kind].toLowerCase()}
      </h3>
      <button className="secondary" disabled={busy} onClick={onClose}>
        Back to classification
      </button>
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      {notice && <p role="status">{notice}</p>}
      {record && (
        <DraftForm
          key={`${kind}-${record.id || "new"}`}
          draftKey={`exam-taxonomy-${kind}-${record.id || "new"}`}
          title="Details"
          draftState={{ revision: record.revision, changes, request }}
          restoreState={(s) => {
            if (
              s?.revision === record.revision &&
              s.changes &&
              typeof s.changes === "object" &&
              !Array.isArray(s.changes)
            ) {
              setChanges(s.changes);
              setRequest(typeof s.request === "string" ? s.request : null);
            } else
              setError(
                "This draft belongs to an older version. Reload the record before editing.",
              );
          }}
          onSubmit={async (e) => {
            e.preventDefault();
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
              setChanges({});
              setRequest(null);
              setNotice("Classification saved in your organisation.");
            } catch (e) {
              setError(e instanceof Error ? e.message : "Unable to save.");
              throw e;
            } finally {
              setBusy(false);
            }
          }}
        >
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
          {kind !== "groups" && (
            <QuestionChoiceField
              org={org}
              kind="groups"
              label="Exam group"
              required
              multiple={kind === "subjects" || kind === "sections"}
              value={
                kind === "subjects" || kind === "sections"
                  ? (values.group_ids ?? [])
                  : (values.group_id ?? null)
              }
              disabled={busy}
              onChange={(v) =>
                set(
                  kind === "subjects" || kind === "sections"
                    ? "group_ids"
                    : "group_id",
                  v,
                )
              }
            />
          )}
          {["topics", "subtopics"].includes(kind) && (
            <QuestionChoiceField
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
              org={org}
              kind="topics"
              label="Topic"
              required
              value={values.topic_id ?? null}
              disabled={busy}
              onChange={(v) => set("topic_id", v)}
            />
          )}
          {kind !== "subjects" && (
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
          {kind === "sections" && (
            <label>
              <input
                type="checkbox"
                checked={Boolean(values.status)}
                disabled={busy}
                onChange={(e) => set("status", e.target.checked)}
              />
              Active section
            </label>
          )}
          <button
            disabled={busy || (record.id > 0 && !Object.keys(changes).length)}
          >
            Save classification
          </button>
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
    </section>
  );
}
export function ExamTaxonomy({ org }: { org: string }) {
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
      if (!after)
        await api(`/organisations/${org}/exam-content/taxonomy/${kind}/new`);
      const term = after ? loaded : search;
      const r = await api<{ items: QuestionChoice[]; next: number | null }>(
        `/organisations/${org}/exam-content/choices/${kind}?search=${encodeURIComponent(term)}&after=${after}`,
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
        key={`${kind}-${editing}`}
        org={org}
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
      <h3>Subjects, topics and sections</h3>
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
          {Object.entries(labels).map(([value, label]) => (
            <option key={value} value={value}>
              {label}
            </option>
          ))}
        </select>
      </label>
      <button disabled={busy} onClick={() => setEditing("new")}>
        Create {labels[kind].toLowerCase()}
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
