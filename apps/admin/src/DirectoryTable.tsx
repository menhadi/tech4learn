import {
  Fragment,
  Children,
  cloneElement,
  isValidElement,
  useState,
  useEffect,
  type ReactNode,
  type ReactElement,
} from "react";
export type TableQuery = {
  search: string;
  filters: Record<string, string>;
  sort: string;
  direction: "asc" | "desc";
  offset: number;
  limit: number;
};
export const emptyTableQuery: TableQuery = {
  search: "",
  filters: {},
  sort: "",
  direction: "asc",
  offset: 0,
  limit: 50,
};
function flatten(children: ReactNode): ReactNode[] {
  return Children.toArray(children).flatMap((n) =>
    isValidElement<{ children: ReactNode }>(n) && n.type === Fragment
      ? flatten(n.props.children)
      : [n],
  );
}
export function cellText(node: ReactNode): string {
  if (node === null || node === undefined || typeof node === "boolean")
    return "";
  if (typeof node === "string" || typeof node === "number") return String(node);
  if (Array.isArray(node)) return node.map(cellText).join(" ");
  if (isValidElement<Record<string, any>>(node)) {
    const p = node.props;
    if (node.type === RecordStatus) return p.archived ? "Archived" : "Active";
    if (node.type === "input")
      return p.type === "checkbox"
        ? p.checked
          ? "Yes"
          : "No"
        : String(p.value ?? p.defaultValue ?? "");
    if (node.type === "select") return String(p.value ?? p.defaultValue ?? "");
    return cellText(p.children);
  }
  return "";
}
export function DirectoryTable({
  title,
  columns,
  children,
  columnKeys,
  className,
  remote,
  onReset,
}: {
  title: string;
  columns: ReactNode[];
  children: ReactNode;
  columnKeys?: string[];
  className?: string;
  onReset?: () => void;
  remote?: {
    query: TableQuery;
    onChange: (q: TableQuery) => void;
    total: number;
    filtered: number;
    loading?: boolean;
  };
}) {
  const [local, setLocal] = useState<TableQuery>(emptyTableQuery);
  const q = remote?.query || local,
    update = (next: Partial<TableQuery>) => {
      const v = { ...q, ...next };
      if (remote) remote.onChange(v);
      else setLocal(v);
    };
  const rows = Children.toArray(children).filter(
    isValidElement,
  ) as ReactElement<Record<string, any>>[];
  const labels = columns.map(cellText),
    keys = columnKeys || labels.map((_, i) => String(i));
  const actionIndex = labels.findIndex((c) => /^actions?$/i.test(c.trim()));
  const get = (row: ReactElement<Record<string, any>>, index: number) =>
    cellText(flatten(row.props.children)[index]).replace(/\s+/g, " ").trim();
  let matches = remote
    ? rows
    : rows.filter((row) => {
        const texts = columns.map((_, i) => get(row, i).toLocaleLowerCase());
        return (
          (!q.search ||
            texts.some(
              (t, i) =>
                i !== actionIndex && t.includes(q.search.toLocaleLowerCase()),
            )) &&
          Object.entries(q.filters).every(
            ([k, v]) =>
              !v || texts[keys.indexOf(k)]?.includes(v.toLocaleLowerCase()),
          )
        );
      });
  if (!remote && q.sort) {
    const index = keys.indexOf(q.sort);
    matches = [...matches].sort(
      (a, b) =>
        get(a, index).localeCompare(get(b, index), undefined, {
          numeric: true,
          sensitivity: "base",
        }) * (q.direction === "asc" ? 1 : -1),
    );
  }
  const total = remote?.total ?? rows.length,
    filtered = remote?.filtered ?? matches.length;
  const offset = Math.min(
    q.offset,
    Math.max(0, Math.floor((filtered - 1) / q.limit) * q.limit),
  );
  useEffect(() => {
    if (remote && !remote.loading && q.offset !== offset)
      remote.onChange({ ...q, offset });
  }, [offset, q.offset, remote?.loading]);
  const visible = remote ? matches : matches.slice(offset, offset + q.limit),
    visibleSet = new Set(visible);
  // Keep editable rows mounted while filtering/paging so entered values are not discarded.
  const ordered = remote
    ? rows
    : [...matches, ...rows.filter((r) => !matches.includes(r))];
  return (
    <section className="data-directory" aria-label={title}>
      <div className="table-toolbar" data-no-draft="true">
        <h3>{title}</h3>
        <label>
          Search records
          <input
            type="search"
            maxLength={120}
            value={q.search}
            onChange={(e) => update({ search: e.target.value, offset: 0 })}
          />
        </label>
        <label>
          Rows per page
          <select
            value={q.limit}
            onChange={(e) =>
              update({ limit: Number(e.target.value), offset: 0 })
            }
          >
            {[50, 100, 500].map((n) => (
              <option key={n} value={n}>
                {n}
              </option>
            ))}
          </select>
        </label>
        <button
          type="button"
          className="secondary"
          onClick={() => {
            update({ ...emptyTableQuery, limit: q.limit });
            onReset?.();
          }}
        >
          Reset filters
        </button>
      </div>
      <p className="table-count" role="status">
        {remote?.loading
          ? "Updating records…"
          : `${filtered.toLocaleString()} of ${total.toLocaleString()} records match · Showing ${filtered ? offset + 1 : 0}–${Math.min(offset + visible.length, filtered)}`}
      </p>
      <div
        className={`directory-table ${actionIndex === columns.length - 1 ? "has-actions" : ""}`}
        role="region"
        aria-label={`${title}; scroll horizontally for all fields`}
        tabIndex={0}
      >
        <table
          className={className}
          onInvalidCapture={(e) => {
            if (remote) return;
            const row = (e.target as HTMLElement).closest("tr");
            const index = ordered.findIndex(
              (r) => String(r.key) === row?.dataset.rowKey,
            );
            if (index >= 0) {
              update({
                search: "",
                filters: {},
                sort: "",
                offset:
                  Math.floor(rows.indexOf(ordered[index]) / q.limit) * q.limit,
              });
              const target = e.target as HTMLElement;
              setTimeout(() => {
                target.scrollIntoView({ block: "center" });
                target.focus();
              }, 0);
            }
          }}
        >
          <caption className="sr-only">{title}</caption>
          <thead>
            <tr>
              {columns.map((c, i) => (
                <th
                  key={keys[i] || i}
                  scope="col"
                  aria-sort={
                    q.sort === keys[i]
                      ? q.direction === "asc"
                        ? "ascending"
                        : "descending"
                      : "none"
                  }
                >
                  {i === actionIndex || !keys[i] ? (
                    c
                  ) : (
                    <button
                      type="button"
                      className="column-sort"
                      onClick={() =>
                        update({
                          sort: keys[i],
                          direction:
                            q.sort === keys[i] && q.direction === "asc"
                              ? "desc"
                              : "asc",
                          offset: 0,
                        })
                      }
                    >
                      {c}
                      <span aria-hidden="true">
                        {q.sort === keys[i]
                          ? q.direction === "asc"
                            ? " ↑"
                            : " ↓"
                          : " ↕"}
                      </span>
                    </button>
                  )}
                </th>
              ))}
            </tr>
            <tr className="column-filters" data-no-draft="true">
              {columns.map((_, i) => (
                <th key={keys[i] || i}>
                  {i !== actionIndex && keys[i] && (
                    <input
                      type="search"
                      maxLength={120}
                      aria-label={`Filter ${labels[i]}`}
                      placeholder={`Filter ${labels[i]}`}
                      value={q.filters[keys[i]] || ""}
                      onChange={(e) =>
                        update({
                          filters: { ...q.filters, [keys[i]]: e.target.value },
                          offset: 0,
                        })
                      }
                    />
                  )}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {ordered.map((row) =>
              cloneElement(row, {
                hidden: !visibleSet.has(row),
                "data-row-key": String(row.key),
              }),
            )}
            {!visible.length && (
              <tr>
                <td colSpan={columns.length}>
                  No matching records. Change or reset your filters.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
      <div className="table-pagination">
        <button
          type="button"
          disabled={!offset || remote?.loading}
          onClick={() => update({ offset: Math.max(0, offset - q.limit) })}
        >
          Previous
        </button>
        <span>
          Page {filtered ? Math.floor(offset / q.limit) + 1 : 0} of{" "}
          {Math.ceil(filtered / q.limit)}
        </span>
        <button
          type="button"
          disabled={offset + q.limit >= filtered || remote?.loading}
          onClick={() => update({ offset: offset + q.limit })}
        >
          Next
        </button>
      </div>
    </section>
  );
}
export function SmartTable({
  children,
  className,
}: {
  children: ReactNode;
  className?: string;
}) {
  const nodes = Children.toArray(children).filter(
    isValidElement,
  ) as ReactElement<{ children: ReactNode }>[];
  const caption = nodes.find((n) => n.type === "caption");
  const head = nodes.find((n) => n.type === "thead");
  const body = nodes.find((n) => n.type === "tbody");
  const row =
    head &&
    (Children.toArray(head.props.children).find(isValidElement) as
      | ReactElement<{ children: ReactNode }>
      | undefined);
  const columns = row
    ? Children.toArray(row.props.children)
        .filter(isValidElement)
        .map((n) => (n as ReactElement<{ children: ReactNode }>).props.children)
    : [];
  return (
    <DirectoryTable
      title={cellText(caption?.props.children) || "Records"}
      columns={columns}
      className={className}
    >
      {body?.props.children}
    </DirectoryTable>
  );
}
export function RecordStatus({ archived = false }: { archived?: boolean }) {
  return (
    <span
      className={`status-badge ${archived ? "status-neutral" : "status-active"}`}
    >
      {archived ? "Archived" : "Active"}
    </span>
  );
}
