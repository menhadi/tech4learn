import { useEffect, useState } from "react";
import { api } from "./api";
import { DraftForm } from "./DraftForm";
import { SmartTable } from "./DirectoryTable";

type Modules = { enabled_modules: Record<string, boolean>; version: number };
type Question = {
  id: number;
  question: string;
  subject_id: number | null;
  qtype_id: number | null;
};
export function ExamModuleControl({ org }: { org: string }) {
  const [saved, setSaved] = useState<Modules | null>(null),
    [enabled, setEnabled] = useState(false),
    [busy, setBusy] = useState(false),
    [error, setError] = useState(""),
    [notice, setNotice] = useState("");
  const base = `/platform/exam-content/${org}/modules`;
  const accept = (s: Modules) => {
    setSaved(s);
    setEnabled(s.enabled_modules.exams === true);
  };
  useEffect(() => {
    let active = true;
    api<Modules>(base)
      .then((s) => {
        if (active) accept(s);
      })
      .catch((e) => {
        if (active) setError(e.message);
      });
    return () => {
      active = false;
    };
  }, [base]);
  return (
    <section className="panel">
      <h3>Organisation module access</h3>
      {error && (
        <p role="alert" className="error">
          {error}
        </p>
      )}
      {notice && <p role="status">{notice}</p>}
      {saved && (
        <DraftForm
          draftKey={`exam-module-${org}`}
          title="Exams module"
          draftState={{ enabled }}
          restoreState={(s) => {
            if (typeof s?.enabled === "boolean") setEnabled(s.enabled);
          }}
          onSubmit={async (e) => {
            e.preventDefault();
            setBusy(true);
            setError("");
            setNotice("");
            try {
              accept(
                await api<Modules>(base, "POST", {
                  exams: enabled,
                  version: saved.version,
                }),
              );
              setNotice("Exams module access saved.");
            } catch (e) {
              setError(e instanceof Error ? e.message : "Unable to save.");
              throw e;
            } finally {
              setBusy(false);
            }
          }}
        >
          <label>
            <input
              type="checkbox"
              checked={enabled}
              disabled={busy}
              onChange={(e) => setEnabled(e.target.checked)}
            />{" "}
            Enable Exams for this organisation
          </label>
          <p>
            Staff also need exam permissions. Feature restrictions below apply
            within this module.
          </p>
          <button disabled={busy}>Save module access</button>
          <button
            type="button"
            className="secondary"
            disabled={busy}
            onClick={() => {
              setBusy(true);
              void api<Modules>(base)
                .then((s) => {
                  accept(s);
                  setError("");
                })
                .catch((e) => setError(e.message))
                .finally(() => setBusy(false));
            }}
          >
            Reload saved access
          </button>
        </DraftForm>
      )}
    </section>
  );
}
export function ExamQuestions({
  org,
  central = false,
}: {
  org: string;
  central?: boolean;
}) {
  const [source, setSource] = useState(central ? "central" : "organisation"),
    [search, setSearch] = useState(""),
    [items, setItems] = useState<Question[]>([]),
    [next, setNext] = useState<number | null>(null),
    [selected, setSelected] = useState<number[]>([]),
    [busy, setBusy] = useState(false),
    [error, setError] = useState(""),
    [notice, setNotice] = useState(""),
    [loadedSearch, setLoadedSearch] = useState("");
  const [request, setRequest] = useState<string | null>(null);
  const [history, setHistory] = useState<
    {
      request_id: string;
      direction: string;
      count: number;
      created_at: string;
      items: { source_id: number; target_id: number }[];
    }[]
  >([]);
  const base = central
    ? `/platform/exam-content/${org}`
    : `/organisations/${org}/exam-content`;
  async function load(after = 0) {
    setBusy(true);
    setError("");
    const term = after ? loadedSearch : search;
    try {
      const r = await api<{ items: Question[]; next: number | null }>(
        `${base}/questions?source=${source}&search=${encodeURIComponent(term)}&after=${after}`,
      );
      setItems((old) => (after ? [...old, ...r.items] : r.items));
      setNext(r.next);
      setLoadedSearch(term);
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
  async function transfer() {
    const id = request ?? crypto.randomUUID();
    setRequest(id);
    setBusy(true);
    setError("");
    setNotice("");
    try {
      const r = await api<{ count: number }>(`${base}/transfer`, "POST", {
        direction: source === "central" ? "share" : "pull",
        question_ids: selected,
        request_id: id,
      });
      setNotice(
        `${r.count} question ${r.count === 1 ? "copy is" : "copies are"} ready in ${source === "central" ? "the organisation question bank" : "the central question bank"}. Existing copies and edits were preserved.`,
      );
      setSelected([]);
      setRequest(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Unable to copy questions.");
    } finally {
      setBusy(false);
    }
  }
  return (
    <section className="panel">
      <h3>{central ? "Question sharing" : "Question bank"}</h3>
      <p>
        {central
          ? "Share central questions with this organisation, or pull its questions into the central bank. Each destination owns its copy."
          : "Questions shared with your organisation appear here. Question editing inside Tech4Learn is still being integrated."}
      </p>
      {central && (
        <label>
          Source bank
          <select
            value={source}
            disabled={busy}
            onChange={(e) => {
              setSource(e.target.value);
              setItems([]);
              setNext(null);
              setSelected([]);
              setRequest(null);
              setNotice("");
              setError("");
            }}
          >
            <option value="central">Central question bank</option>
            <option value="organisation">Organisation question bank</option>
          </select>
        </label>
      )}
      {central && (
        <details>
          <summary>Question transfer history</summary>
          <button
            className="secondary"
            disabled={busy}
            onClick={() => {
              setBusy(true);
              setError("");
              void api<{ items: typeof history }>(base + "/transfers")
                .then((r) => setHistory(r.items))
                .catch((e) => setError(e.message))
                .finally(() => setBusy(false));
            }}
          >
            Load latest 50 transfers
          </button>
          <SmartTable>
            <caption>Latest question transfers</caption>
            <thead>
              <tr>
                <th>Time</th>
                <th>Direction</th>
                <th>Questions</th>
                <th>Source → copy IDs</th>
              </tr>
            </thead>
            <tbody>
              {history.map((h) => (
                <tr key={h.request_id}>
                  <td>{h.created_at}</td>
                  <td>
                    {h.direction === "share"
                      ? "Central → organisation"
                      : "Organisation → central"}
                  </td>
                  <td>{h.count}</td>
                  <td>
                    {h.items
                      .map((i) => `${i.source_id} → ${i.target_id}`)
                      .join(", ")}
                  </td>
                </tr>
              ))}
            </tbody>
          </SmartTable>
        </details>
      )}
      <form
        onSubmit={(e) => {
          e.preventDefault();
          void load();
        }}
      >
        <label>
          Find questions
          <input
            value={search}
            maxLength={120}
            disabled={busy}
            onChange={(e) => setSearch(e.target.value)}
          />
        </label>
        <button disabled={busy}>Load questions</button>
      </form>
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      {notice && <p role="status">{notice}</p>}
      <SmartTable>
        <caption>Questions — {items.length} loaded</caption>
        <thead>
          <tr>
            <th>Question</th>
            <th>ID</th>
            {central && <th>Select</th>}
          </tr>
        </thead>
        <tbody>
          {items.map((q) => (
            <tr key={q.id}>
              <td>{q.question || "(Question contains media)"}</td>
              <td>{q.id}</td>
              {central && (
                <td>
                  <input
                    type="checkbox"
                    aria-label={`Select question ${q.id}`}
                    checked={selected.includes(q.id)}
                    disabled={
                      busy ||
                      (!selected.includes(q.id) && selected.length >= 50)
                    }
                    onChange={(e) => {
                      setRequest(null);
                      setSelected((old) =>
                        e.target.checked
                          ? [...old, q.id]
                          : old.filter((id) => id !== q.id),
                      );
                    }}
                  />
                </td>
              )}
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
          Load next 50 questions
        </button>
      )}
      {central && (
        <>
          <p>{selected.length} selected. Up to 50 questions per request.</p>
          <button
            disabled={busy || selected.length === 0}
            onClick={() => void transfer()}
          >
            {source === "central"
              ? "Share selected with organisation"
              : "Pull selected into central bank"}
          </button>
        </>
      )}
    </section>
  );
}
