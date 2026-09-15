import { useEffect, useRef, useState } from "react";
import { api } from "./api";
export type QuestionChoice = {
  id: number;
  label: string;
  type?: string;
  subject_id?: number;
  group_id?: number;
  topic_id?: number;
};
export function QuestionChoiceField({
  org,
  kind,
  label,
  value,
  multiple = false,
  required = false,
  disabled,
  onChange,
  parentId,
  allowedIds,
}: {
  org: string;
  kind: string;
  label: string;
  value: number | number[] | null;
  multiple?: boolean;
  required?: boolean;
  disabled: boolean;
  onChange: (value: any, option?: QuestionChoice) => void;
  parentId?: number | null;
  allowedIds?: number[];
}) {
  const [items, setItems] = useState<QuestionChoice[]>([]),
    [search, setSearch] = useState(""),
    [loaded, setLoaded] = useState(""),
    [next, setNext] = useState<number | null>(null),
    [loading, setLoading] = useState(false),
    [error, setError] = useState("");
  const generation = useRef(0);
  async function load(after = 0) {
    const request = ++generation.current;
    if (parentId === null) {
      setItems([]);
      setNext(null);
      setLoading(false);
      return;
    }
    setLoading(true);
    setError("");
    const term = after ? loaded : search;
    try {
      const r = await api<{ items: QuestionChoice[]; next: number | null }>(
        `/organisations/${org}/exam-content/choices/${kind}?search=${encodeURIComponent(term)}&after=${after}${parentId === undefined ? "" : `&parent_id=${parentId}`}`,
      );
      if (request !== generation.current) return;
      setItems((old) => (after ? [...old, ...r.items] : r.items));
      setNext(r.next);
      setLoaded(term);
    } catch (e) {
      if (request !== generation.current) return;
      setError(e instanceof Error ? e.message : "Unable to load choices.");
    } finally {
      if (request === generation.current) setLoading(false);
    }
  }
  useEffect(() => {
    setItems([]);
    setNext(null);
    setError("");
    void load();
    return () => {
      generation.current++;
    };
  }, [org, kind, parentId]);
  const selected = Array.isArray(value) ? value : value ? [value] : [];
  return (
    <fieldset>
      <legend>
        {label}
        {required ? " (required)" : ""}
      </legend>
      <div data-no-draft="true">
        <label>
          Search {label.toLowerCase()}
          <input
            value={search}
            maxLength={120}
            disabled={disabled || loading}
            onChange={(e) => setSearch(e.target.value)}
          />
        </label>
        <button
          className="secondary"
          type="button"
          disabled={disabled || loading}
          onClick={() => void load()}
        >
          Search {label.toLowerCase()}
        </button>
      </div>
      {error && (
        <p role="alert" className="error">
          {error}
        </p>
      )}
      {multiple ? (
        <>
          <p>{selected.length} selected</p>
          {selected
            .filter((id) => !items.some((i) => i.id === id))
            .map((id) => (
              <label key={id}>
                <input
                  type="checkbox"
                  checked
                  disabled={disabled}
                  onChange={() => onChange(selected.filter((x) => x !== id))}
                />{" "}
                Selected #{id}
              </label>
            ))}
          {items
            .filter((item) => !allowedIds || allowedIds.includes(item.id))
            .map((item) => (
              <label key={item.id}>
                <input
                  type="checkbox"
                  checked={selected.includes(item.id)}
                  disabled={disabled}
                  onChange={(e) =>
                    onChange(
                      e.target.checked
                        ? [...selected, item.id]
                        : selected.filter((x) => x !== item.id),
                      item,
                    )
                  }
                />
                {item.label}
              </label>
            ))}
        </>
      ) : (
        <label>
          {label}
          <select
            required={required}
            value={value === null ? "" : String(value ?? "")}
            disabled={disabled || loading}
            onChange={(e) => {
              const id = e.target.value ? Number(e.target.value) : null;
              onChange(
                id,
                items.find((i) => i.id === id),
              );
            }}
          >
            <option value="">
              {required ? "Choose " + label.toLowerCase() : "None"}
            </option>
            {selected
              .filter((id) => !items.some((i) => i.id === id))
              .map((id) => (
                <option key={id} value={id}>
                  Selected #{id}
                </option>
              ))}
            {items
              .filter((item) => !allowedIds || allowedIds.includes(item.id))
              .map((item) => (
                <option key={item.id} value={item.id}>
                  {item.label}
                </option>
              ))}
          </select>
        </label>
      )}
      {next !== null && (
        <button
          className="secondary"
          type="button"
          disabled={disabled || loading}
          onClick={() => void load(next)}
        >
          Load more {label.toLowerCase()}
        </button>
      )}
    </fieldset>
  );
}
