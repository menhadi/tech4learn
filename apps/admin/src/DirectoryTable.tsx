import { Children, type ReactNode } from "react";

export function DirectoryTable({
  title,
  columns,
  children,
}: {
  title: string;
  columns: string[];
  children: ReactNode;
}) {
  return (
    <div
      className="directory-table"
      role="region"
      aria-label={title}
      tabIndex={0}
    >
      <table>
        <caption>{title}</caption>
        <thead>
          <tr>
            {columns.map((c) => (
              <th key={c} scope="col">
                {c}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {Children.toArray(children).length ? (
            children
          ) : (
            <tr>
              <td colSpan={columns.length} className="empty-state">
                No records to show for this view.
              </td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
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
