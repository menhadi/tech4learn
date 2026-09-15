import { useEffect, useState } from "react";
import { api } from "./api";
import { DraftForm } from "./DraftForm";
import { SmartTable } from "./DirectoryTable";
import {
  QuestionChoiceField,
  type QuestionChoice,
} from "./QuestionChoiceField";
import { FormattedField } from "./ExamQuestionEditor";
import { ExamPaperControls } from "./ExamPaperControls";
import { ExamDocuments } from "./ExamDocuments";
export type Exam = {
  id: number;
  revision: string;
  fields: Record<string, any>;
  test_types: Record<string, string>;
  timezone: string;
  status?: string;
  sections?: {
    id: number;
    name: string;
    display_order: number;
    duration: number | null;
  }[];
  subject_durations?: { subject_id: number; duration: number }[];
  paper_subjects?: { id: number; name: string }[];
};
const flags = {
  online_attempt_enabled: "Allow online attempts",
  frontend_visible: "Show in the exam catalogue",
  offline_enabled: "Allow offline attempts",
  omr_enabled: "Enable OMR",
  show_instruction: "Show instructions",
  random_question: "Randomise question order",
  option_shuffle: "Shuffle options",
  allow_answer_change: "Allow answer changes",
  result_after_finish: "Show result after finishing",
  negative_marking: "Use negative marking",
  calculator_allowed: "Allow calculator",
  browser_tolerance: "Monitor browser switching",
  proctor: "Enable proctoring",
};
function ExamQuestionsEditor({
  org,
  record,
  onSaved,
  disabled,
}: {
  org: string;
  record: Exam;
  onSaved: (r: Exam) => void;
  disabled: boolean;
}) {
  const [mode, setMode] = useState("attached"),
    [items, setItems] = useState<{ id: number; question: string }[]>([]),
    [selected, setSelected] = useState<number[]>([]),
    [next, setNext] = useState<number | null>(null),
    [busy, setBusy] = useState(false),
    [error, setError] = useState(""),
    [request, setRequest] = useState<string | null>(null);
  const [search, setSearch] = useState(""),
    [loadedSearch, setLoadedSearch] = useState("");
  const [sectionDefinition, setSectionDefinition] = useState<number | null>(
    null,
  );
  const [assignmentRetry, setAssignmentRetry] = useState<{
    key: string;
    id: string;
  } | null>(null);
  const base = `/organisations/${org}/exam-content`;
  async function load(after = 0) {
    setBusy(true);
    setError("");
    try {
      const result = await api<{ items: typeof items; next: number | null }>(
        mode === "attached"
          ? `${base}/exams/${record.id}/questions?after=${after}`
          : `${base}/questions?source=organisation&search=${encodeURIComponent(after ? loadedSearch : search)}&after=${after}`,
      );
      setItems((old) => (after ? [...old, ...result.items] : result.items));
      setNext(result.next);
      setLoadedSearch(after ? loadedSearch : search);
      if (!after) {
        setSelected([]);
        setRequest(null);
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : "Unable to load questions.");
    } finally {
      setBusy(false);
    }
  }
  return (
    <section>
      <h4>Exam questions</h4>
      {disabled && (
        <p>Save or reload the settings before changing exam questions.</p>
      )}
      <label>
        Question source
        <select
          value={mode}
          disabled={busy || disabled}
          onChange={(e) => {
            setMode(e.target.value);
            setItems([]);
            setSelected([]);
            setNext(null);
            setRequest(null);
          }}
        >
          <option value="attached">In this exam</option>
          <option value="bank">Organisation question bank</option>
        </select>
      </label>
      {mode === "bank" && (
        <label>
          Search question bank
          <input
            maxLength={120}
            disabled={busy || disabled}
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </label>
      )}
      <button
        className="secondary"
        disabled={busy || disabled}
        onClick={() => void load()}
      >
        Load questions
      </button>
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      <SmartTable>
        <caption>Exam questions — {items.length} loaded</caption>
        <thead>
          <tr>
            <th>Question</th>
            <th>ID</th>
            <th>Select</th>
          </tr>
        </thead>
        <tbody>
          {items.map((q) => (
            <tr key={q.id}>
              <td>{q.question || "(Question contains media)"}</td>
              <td>{q.id}</td>
              <td>
                <input
                  type="checkbox"
                  aria-label={`Select exam question ${q.id}`}
                  checked={selected.includes(q.id)}
                  disabled={
                    busy ||
                    disabled ||
                    (!selected.includes(q.id) && selected.length >= 100)
                  }
                  onChange={(e) => {
                    setSelected((old) =>
                      e.target.checked
                        ? [...old, q.id]
                        : old.filter((id) => id !== q.id),
                    );
                    setRequest(null);
                  }}
                />
              </td>
            </tr>
          ))}
        </tbody>
      </SmartTable>
      {next !== null && (
        <button
          className="secondary"
          disabled={busy || disabled}
          onClick={() => void load(next)}
        >
          Load more questions
        </button>
      )}
      <button
        disabled={busy || disabled || !selected.length}
        onClick={async () => {
          setBusy(true);
          setError("");
          const requestId = request ?? crypto.randomUUID();
          setRequest(requestId);
          try {
            const saved = await api<Exam>(
              `${base}/exams/${record.id}/actions/${mode === "bank" ? "add-questions" : "remove-questions"}`,
              "POST",
              {
                fields: { question_ids: selected },
                revision: record.revision,
                request_id: requestId,
              },
            );
            onSaved(saved);
            setSelected([]);
            setItems([]);
            setNext(null);
            setRequest(null);
          } catch (e) {
            setError(
              e instanceof Error
                ? e.message
                : "Unable to change exam questions.",
            );
          } finally {
            setBusy(false);
          }
        }}
      >
        {mode === "bank" ? "Add selected to exam" : "Remove selected from exam"}
      </button>
      <p>
        Removing a question from this exam preserves it in the question bank.
      </p>
      {mode === "attached" && (
        <div>
          <QuestionChoiceField
            org={org}
            kind="sections"
            label="Section for selected questions (empty means General)"
            value={sectionDefinition}
            disabled={busy || disabled}
            onChange={setSectionDefinition}
          />
          <p>
            Choose a question section linked to an exam group. ExamElite creates
            its matching exam section when needed; edit its duration above.
          </p>
          <button
            type="button"
            className="secondary"
            disabled={busy || disabled || !selected.length}
            onClick={async () => {
              const fields = {
                question_ids: selected,
                question_section_id: sectionDefinition,
              };
              const key = JSON.stringify([fields, record.revision]);
              const requestId =
                assignmentRetry?.key === key
                  ? assignmentRetry.id
                  : crypto.randomUUID();
              setAssignmentRetry({ key, id: requestId });
              setBusy(true);
              setError("");
              try {
                onSaved(
                  await api<Exam>(
                    `${base}/exams/${record.id}/actions/assign-section`,
                    "POST",
                    {
                      fields,
                      revision: record.revision,
                      request_id: requestId,
                    },
                  ),
                );
                setSelected([]);
                setAssignmentRetry(null);
              } catch (e) {
                setError(
                  e instanceof Error ? e.message : "Unable to assign section.",
                );
              } finally {
                setBusy(false);
              }
            }}
          >
            Assign selected questions to section
          </button>
        </div>
      )}
    </section>
  );
}
function ExamEditor({
  org,
  id,
  onClose,
}: {
  org: string;
  id: number | "new";
  onClose: () => void;
}) {
  const [record, setRecord] = useState<Exam | null>(null),
    [changes, setChanges] = useState<Record<string, any>>({}),
    [request, setRequest] = useState<string | null>(null),
    [busy, setBusy] = useState(false),
    [error, setError] = useState(""),
    [notice, setNotice] = useState("");
  const base = `/organisations/${org}/exam-content/taxonomy/exams`;
  async function load() {
    setBusy(true);
    setError("");
    try {
      setRecord(await api<Exam>(`${base}/${record?.id || id}`));
      setChanges({});
      setRequest(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Unable to load exam.");
    } finally {
      setBusy(false);
    }
  }
  useEffect(() => {
    void load();
  }, [org, id]);
  const set = (key: string, value: any) => {
    setChanges((old) => ({ ...old, [key]: value }));
    setRequest(null);
    setNotice("");
  };
  const values = { ...record?.fields, ...changes };
  return (
    <section className="panel">
      <h3>{record?.id ? "Edit exam" : "Create exam"}</h3>
      <button className="secondary" disabled={busy} onClick={onClose}>
        Back to exams
      </button>
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      {notice && <p role="status">{notice}</p>}
      {record && (
        <>
          <DraftForm
            key={record.id || "new"}
            draftKey={`exam-settings-${record.id || "new"}`}
            title="Exam settings"
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
                  "This draft belongs to an older exam. Reload before editing.",
                );
            }}
            onSubmit={async (e) => {
              e.preventDefault();
              setBusy(true);
              setError("");
              const requestId = request ?? crypto.randomUUID();
              setRequest(requestId);
              try {
                const saved = await api<Exam>(
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
                setNotice("Exam settings saved.");
              } catch (e) {
                setError(
                  e instanceof Error ? e.message : "Unable to save exam.",
                );
                throw e;
              } finally {
                setBusy(false);
              }
            }}
          >
            <fieldset>
              <legend>Exam details</legend>
              <label>
                Name
                <input
                  required
                  maxLength={255}
                  disabled={busy}
                  value={values.name ?? ""}
                  onChange={(e) => set("name", e.target.value)}
                />
              </label>
              <label>
                Test type
                <select
                  value={values.test_type ?? ""}
                  required
                  disabled={busy}
                  onChange={(e) => set("test_type", e.target.value)}
                >
                  {Object.entries(record.test_types).map(([key, label]) => (
                    <option key={key} value={key}>
                      {label}
                    </option>
                  ))}
                </select>
              </label>
              {["subject_test", "topic_test", "subtopic_test"].includes(
                values.test_type,
              ) && (
                <QuestionChoiceField
                  org={org}
                  kind="subjects"
                  label="Test subject"
                  required
                  value={values.test_subject_id ?? null}
                  disabled={busy}
                  onChange={(v) => set("test_subject_id", v)}
                />
              )}
              {["topic_test", "subtopic_test"].includes(values.test_type) && (
                <QuestionChoiceField
                  org={org}
                  kind="topics"
                  label="Test topic"
                  required
                  value={values.test_topic_id ?? null}
                  disabled={busy}
                  onChange={(v) => set("test_topic_id", v)}
                />
              )}
              {values.test_type === "subtopic_test" && (
                <QuestionChoiceField
                  org={org}
                  kind="subtopics"
                  label="Test subtopic"
                  required
                  value={values.test_stopic_id ?? null}
                  disabled={busy}
                  onChange={(v) => set("test_stopic_id", v)}
                />
              )}
              {values.test_type === "previous_year" && (
                <>
                  <label>
                    Exam year
                    <input
                      type="number"
                      min="1900"
                      required
                      disabled={busy}
                      value={values.exam_year ?? ""}
                      onChange={(e) => set("exam_year", Number(e.target.value))}
                    />
                  </label>
                  <label>
                    Exam session
                    <input
                      maxLength={100}
                      disabled={busy}
                      value={values.exam_session ?? ""}
                      onChange={(e) => set("exam_session", e.target.value)}
                    />
                  </label>
                </>
              )}
            </fieldset>
            <QuestionChoiceField
              org={org}
              kind="groups"
              label="Exam groups"
              multiple
              value={values.groups ?? []}
              disabled={busy}
              onChange={(v) => set("groups", v)}
            />
            <QuestionChoiceField
              org={org}
              kind="packages"
              label="Exam packages"
              multiple
              value={values.packages ?? []}
              disabled={busy}
              onChange={(v) => set("packages", v)}
            />
            <QuestionChoiceField
              org={org}
              kind="categories"
              label="Category"
              value={values.category_level_1 ?? null}
              disabled={busy || Boolean(values.packages?.length)}
              onChange={(v) => {
                set("category_level_1", v);
                set("category_level_2", null);
              }}
            />
            <QuestionChoiceField
              org={org}
              kind="subcategories"
              label="Subcategory"
              value={values.category_level_2 ?? null}
              parentId={
                values.category_level_1 ? Number(values.category_level_1) : null
              }
              disabled={
                busy ||
                Boolean(values.packages?.length) ||
                !values.category_level_1
              }
              onChange={(v) => set("category_level_2", v)}
            />
            <p>
              For a standalone exam, categories must allow its selected groups.
              When packages are selected, groups and classification come from
              those packages.
            </p>
            <QuestionChoiceField
              org={org}
              kind="languages"
              label="Exam languages"
              multiple
              value={values.language_ids ?? []}
              disabled={busy}
              onChange={(v) => set("language_ids", v)}
            />
            <fieldset>
              <legend>Timing and marking</legend>
              {[
                ["duration", "Duration (minutes)"],
                ["attempt_count", "Attempt limit (0 means unlimited)"],
                ["passing_percentage", "Passing percentage"],
                ["display_order", "Display order"],
                ["tolerance_count", "Allowed browser switches"],
              ].map(([key, label]) => (
                <label key={key}>
                  {label}
                  <input
                    type="number"
                    min="0"
                    max={key === "passing_percentage" ? 100 : undefined}
                    step={key === "passing_percentage" ? "any" : 1}
                    required={["duration", "attempt_count"].includes(key)}
                    disabled={busy}
                    value={values[key] ?? ""}
                    onChange={(e) =>
                      set(
                        key,
                        e.target.value === "" ? null : Number(e.target.value),
                      )
                    }
                  />
                </label>
              ))}
              <label>
                Question grouping
                <select
                  value={values.grouping_mode ?? "subject"}
                  disabled={busy}
                  onChange={(e) => set("grouping_mode", e.target.value)}
                >
                  <option value="none">None</option>
                  <option value="subject">Subject</option>
                  <option value="section">Section</option>
                </select>
              </label>
              <label>
                <input
                  type="checkbox"
                  checked={Boolean(values.use_group_timer)}
                  disabled={busy || values.grouping_mode === "none"}
                  onChange={(e) => set("use_group_timer", e.target.checked)}
                />
                Use separate group timers
              </label>
              <p>Schedule timezone: {record.timezone}</p>
              {[
                ["start_date", "Available from"],
                ["end_date", "Available until"],
              ].map(([key, label]) => (
                <label key={key}>
                  {label}
                  <input
                    type="datetime-local"
                    disabled={busy}
                    value={
                      values[key]
                        ? String(values[key]).replace(" ", "T").slice(0, 16)
                        : ""
                    }
                    onChange={(e) =>
                      set(
                        key,
                        e.target.value
                          ? e.target.value.replace("T", " ") + ":00"
                          : null,
                      )
                    }
                  />
                </label>
              ))}
            </fieldset>
            <fieldset>
              <legend>Delivery and review options</legend>
              {Object.entries(flags).map(([key, label]) => (
                <label key={key}>
                  <input
                    type="checkbox"
                    checked={Boolean(values[key])}
                    disabled={busy}
                    onChange={(e) => set(key, e.target.checked)}
                  />
                  {label}
                </label>
              ))}
            </fieldset>
            <FormattedField
              label="Instructions"
              value={values.instruction ?? ""}
              disabled={busy}
              onChange={(v) => set("instruction", v)}
            />
            <FormattedField
              label="Syllabus"
              value={values.syllabus ?? ""}
              disabled={busy}
              onChange={(v) => set("syllabus", v)}
            />
            <button
              disabled={busy || (record.id > 0 && !Object.keys(changes).length)}
            >
              Save exam settings
            </button>
            <button
              className="secondary"
              type="button"
              disabled={busy}
              onClick={() => void load()}
            >
              Reload saved exam
            </button>
          </DraftForm>
          {record.id > 0 && (
            <ExamDocuments
              key={`${org}-${record.id}-${record.revision}`}
              org={org}
              record={record}
              disabled={busy || Object.keys(changes).length > 0}
            />
          )}
          {record.id > 0 && (
            <ExamPaperControls
              org={org}
              record={record}
              onSaved={setRecord}
              disabled={busy || Object.keys(changes).length > 0}
            />
          )}
          {record.id > 0 && (
            <ExamQuestionsEditor
              org={org}
              record={record}
              onSaved={(r) => {
                setRecord(r);
                setNotice("Exam questions saved.");
              }}
              disabled={busy || Object.keys(changes).length > 0}
            />
          )}
        </>
      )}
    </section>
  );
}
export function ExamBuilder({ org }: { org: string }) {
  const [items, setItems] = useState<QuestionChoice[]>([]),
    [next, setNext] = useState<number | null>(null),
    [search, setSearch] = useState(""),
    [loaded, setLoaded] = useState(""),
    [editing, setEditing] = useState<number | "new" | null>(null),
    [busy, setBusy] = useState(false),
    [error, setError] = useState("");
  async function load(after = 0) {
    setBusy(true);
    setError("");
    try {
      if (!after)
        await api(`/organisations/${org}/exam-content/taxonomy/exams/new`);
      const term = after ? loaded : search;
      const r = await api<{ items: QuestionChoice[]; next: number | null }>(
        `/organisations/${org}/exam-content/choices/exams?search=${encodeURIComponent(term)}&after=${after}`,
      );
      setItems((old) => (after ? [...old, ...r.items] : r.items));
      setNext(r.next);
      setLoaded(term);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Unable to load exams.");
    } finally {
      setBusy(false);
    }
  }
  if (editing !== null)
    return (
      <ExamEditor
        key={editing}
        org={org}
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
      <h3>Create and manage exams</h3>
      <button disabled={busy} onClick={() => setEditing("new")}>
        Create exam
      </button>
      <form
        onSubmit={(e) => {
          e.preventDefault();
          void load();
        }}
      >
        <label>
          Find exams
          <input
            value={search}
            maxLength={120}
            disabled={busy}
            onChange={(e) => setSearch(e.target.value)}
          />
        </label>
        <button disabled={busy}>Load exams</button>
      </form>
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      <SmartTable>
        <caption>Exams — {items.length} loaded</caption>
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
                  Edit exam
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
          Load next 100 exams
        </button>
      )}
    </section>
  );
}
