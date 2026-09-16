import { useState } from "react";
import { api } from "./api";
import { DraftForm } from "./DraftForm";
import { SmartTable } from "./DirectoryTable";
import type { Exam } from "./ExamBuilder";

export function ExamPaperControls({
  org,
  central = false,
  record,
  onSaved,
  disabled,
}: {
  org: string;
  central?: boolean;
  record: Exam;
  onSaved: (r: Exam) => void;
  disabled: boolean;
}) {
  const [busy, setBusy] = useState(false),
    [error, setError] = useState(""),
    [message, setMessage] = useState("");
  const [section, setSection] = useState<number>(0),
    [fields, setFields] = useState({
      name: "",
      display_order: 0,
      duration: "",
    });
  const [durations, setDurations] = useState<Record<string, string>>({});
  const [retry, setRetry] = useState<{ key: string; id: string } | null>(null);
  const [confirmRemove, setConfirmRemove] = useState(false);
  const base = `${central ? `/platform/exam-content/${org}/central` : `/organisations/${org}/exam-content`}/exams/${record.id}/actions`;
  async function save(action: string, values: Record<string, unknown>) {
    setBusy(true);
    setError("");
    setMessage("");
    const key = JSON.stringify([action, values, record.revision]);
    const request = retry?.key === key ? retry.id : crypto.randomUUID();
    try {
      const saved = await api<Exam>(`${base}/${action}`, "POST", {
        fields: values,
        revision: record.revision,
        request_id: request,
      });
      onSaved(saved);
      setRetry(null);
      setMessage("Exam updated.");
      return true;
    } catch (e) {
      setRetry({ key, id: request });
      setError(e instanceof Error ? e.message : "Unable to update exam.");
      return false;
    } finally {
      setBusy(false);
    }
  }
  const locked = busy || disabled;
  const subjects = record.paper_subjects ?? [];
  return (
    <section>
      <h4>Publication and paper timing</h4>
      {disabled && (
        <p>Save or reload exam settings before changing the paper.</p>
      )}
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      {message && <p role="status">{message}</p>}
      <p>
        Exam status: <strong>{record.status || "Inactive"}</strong>. Online
        access also depends on the catalogue, online-attempt and schedule
        settings.
      </p>
      <button
        type="button"
        className="secondary"
        disabled={locked}
        onClick={() =>
          void save("set-status", {
            status: record.status === "Active" ? "Inactive" : "Active",
          })
        }
      >
        {record.status === "Active" ? "Make exam inactive" : "Make exam active"}
      </button>
      <p>
        Student results are{" "}
        <strong>
          {record.fields.result_after_finish ? "published" : "hidden"}
        </strong>
        . This controls completed and future attempts; organisation result
        restrictions still apply.
      </p>
      <button
        type="button"
        className="secondary"
        disabled={locked}
        onClick={() =>
          void save("set-result-status", {
            result_after_finish: !record.fields.result_after_finish,
          })
        }
      >
        {record.fields.result_after_finish
          ? "Hide student results"
          : "Publish student results"}
      </button>
      <h4>Exam sections</h4>
      <SmartTable>
        <caption>Sections in this exam</caption>
        <thead>
          <tr>
            <th>Name</th>
            <th>Order</th>
            <th>Minutes</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          {(record.sections ?? []).map((s) => (
            <tr key={s.id}>
              <td>{s.name}</td>
              <td>{s.display_order}</td>
              <td>{s.duration ?? "Default"}</td>
              <td>
                <button
                  type="button"
                  className="secondary"
                  disabled={locked}
                  onClick={() => {
                    setSection(s.id);
                    setFields({
                      name: s.name,
                      display_order: Number(s.display_order ?? 0),
                      duration: s.duration == null ? "" : String(s.duration),
                    });
                    setConfirmRemove(false);
                  }}
                >
                  Edit section
                </button>
              </td>
            </tr>
          ))}
        </tbody>
      </SmartTable>
      <DraftForm
        key={`${record.id}-${section}`}
        draftKey={`${central ? `central-exam-${org}` : "exam"}-section-${record.id}-${section}`}
        draftState={{ revision: record.revision, fields, retry }}
        restoreState={(s: any) => {
          if (s?.revision === record.revision && s?.fields) {
            setFields(s.fields);
            setRetry(s.retry ?? null);
          }
        }}
        onSubmit={async (e) => {
          e.preventDefault();
          const values = {
            ...fields,
            duration: fields.duration === "" ? null : Number(fields.duration),
            ...(section ? { section_id: section } : {}),
          };
          if (
            await save(section ? "update-section" : "create-section", values)
          ) {
            setSection(0);
            setFields({ name: "", display_order: 0, duration: "" });
            setConfirmRemove(false);
            return true;
          }
          return false;
        }}
      >
        <fieldset disabled={locked}>
          <legend>{section ? "Edit section" : "Create section"}</legend>
          <label>
            Section name
            <input
              required
              maxLength={191}
              value={fields.name}
              onChange={(e) => setFields({ ...fields, name: e.target.value })}
            />
          </label>
          <label>
            Display order
            <input
              type="number"
              min={0}
              step={1}
              value={fields.display_order}
              onChange={(e) =>
                setFields({ ...fields, display_order: Number(e.target.value) })
              }
            />
          </label>
          <label>
            Duration in minutes (optional)
            <input
              type="number"
              min={1}
              max={record.fields.duration || undefined}
              step={1}
              value={fields.duration}
              onChange={(e) =>
                setFields({ ...fields, duration: e.target.value })
              }
            />
          </label>
          <p>
            Section durations together cannot exceed the exam duration. Section
            timers apply when grouping and timing are set to sections.
          </p>
          <button type="submit">Save section</button>
          {section > 0 && (
            <button
              className="secondary"
              type="button"
              onClick={() => {
                setSection(0);
                setFields({ name: "", display_order: 0, duration: "" });
                setConfirmRemove(false);
              }}
            >
              New section
            </button>
          )}
        </fieldset>
      </DraftForm>
      {section > 0 && (
        <div>
          <label>
            <input
              type="checkbox"
              data-no-draft
              checked={confirmRemove}
              disabled={locked}
              onChange={(e) => setConfirmRemove(e.target.checked)}
            />
            Remove this section and move its questions to General
          </label>
          <button
            type="button"
            className="secondary"
            disabled={locked || !confirmRemove}
            onClick={async () => {
              if (await save("remove-section", { section_id: section })) {
                setSection(0);
                setFields({ name: "", display_order: 0, duration: "" });
                setConfirmRemove(false);
              }
            }}
          >
            Remove section
          </button>
        </div>
      )}
      {subjects.length > 0 && (
        <DraftForm
          draftKey={`${central ? `central-exam-${org}` : "exam"}-subject-timers-${record.id}`}
          draftState={{ revision: record.revision, durations, retry }}
          restoreState={(s: any) => {
            if (s?.revision === record.revision && s?.durations) {
              setDurations(s.durations);
              setRetry(s.retry ?? null);
            }
          }}
          onSubmit={async (e) => {
            e.preventDefault();
            const saved = await save("subject-timers", {
              subject_ids: subjects.map((s) => s.id),
              durations: subjects.map((s) =>
                Number(
                  durations[s.id] ??
                    record.subject_durations?.find((d) => d.subject_id === s.id)
                      ?.duration ??
                    "",
                ),
              ),
            });
            if (saved) setDurations({});
            return saved;
          }}
        >
          <fieldset disabled={locked}>
            <legend>Subject timers</legend>
            <p>
              Set minutes for every subject in this paper. Saving enables
              subject grouping and subject timers. Total minutes must not exceed{" "}
              {record.fields.duration}.
            </p>
            {subjects.map((s) => (
              <label key={s.id}>
                {s.name}
                <input
                  type="number"
                  required
                  min={1}
                  step={1}
                  value={
                    durations[s.id] ??
                    record.subject_durations?.find((d) => d.subject_id === s.id)
                      ?.duration ??
                    ""
                  }
                  onChange={(e) =>
                    setDurations({ ...durations, [s.id]: e.target.value })
                  }
                />
              </label>
            ))}
            <button type="submit">Save subject timers</button>
          </fieldset>
        </DraftForm>
      )}
    </section>
  );
}
