import { useEffect, useState } from "react";
import { api } from "./api";
import { DirectoryTable, emptyTableQuery } from "./DirectoryTable";
import { SectionSelect } from "./SectionSelect";
import type { AcademicGroup } from "./AcademicStructure";
export type ExamLearner = { id: string; name: string; code: string };

export function ExamLearnerPicker({
  org,
  onSelect,
  action = "Select student",
  title = "Choose a student",
  groups = [],
}: {
  org: string;
  onSelect: (student: ExamLearner) => void;
  action?: string;
  title?: string;
  groups?: AcademicGroup[];
}) {
  const [query, setQuery] = useState(emptyTableQuery);
  const [rows, setRows] = useState<ExamLearner[]>([]);
  const [counts, setCounts] = useState({ total: 0, filtered: 0 });
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);
  const [revision, setRevision] = useState(0);
  const [section, setSection] = useState("");
  useEffect(() => {
    let active = true;
    setLoading(true);
    setError("");
    setRows([]);
    setCounts({total:0,filtered:0});
    const timer = setTimeout(() => {
      void api<{ items: ExamLearner[]; total: number; filtered: number }>(
        `/organisations/${org}/learners?group_id=${encodeURIComponent(section)}&search=${encodeURIComponent(query.search)}&offset=${query.offset}&limit=${query.limit}&sort=${encodeURIComponent(query.sort || "name")}&direction=${query.direction}&filters=${encodeURIComponent(JSON.stringify(query.filters))}`,
      )
        .then((page) => {
          if (active) {
            setRows(
              page.items.map(({ id, name, code }) => ({ id, name, code })),
            );
            setCounts({ total: page.total, filtered: page.filtered });
          }
        })
        .catch((cause) => {
          if (active) {
            setRows([]);
            setCounts({ total: 0, filtered: 0 });
            setError(cause.message);
          }
        })
        .finally(() => {
          if (active) setLoading(false);
        });
    }, 200);
    return () => {
      active = false;
      clearTimeout(timer);
    };
  }, [org, query, revision, section]);
  return (
    <>
      {!!groups.length && <details className="exam-section-filter">
        <summary>Choose students by class and section</summary>
        <p>Use the academic hierarchy to find a section, then select it to filter the student directory. Only students you can access are returned.</p>
        <SectionSelect groups={groups} name="exam_student_section" required={false} value={section} onChange={id => {setSection(id);setQuery(current => ({...current,offset:0}));}} />
      </details>}
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      <button
        className="secondary"
        disabled={loading}
        onClick={() => setRevision((r) => r + 1)}
      >
        Refresh students
      </button>
      <DirectoryTable
        title={title}
        columns={["Student", "Code", "Actions"]}
        columnKeys={["name", "code", ""]}
        remote={{ query, onChange: setQuery, ...counts, loading }}
      >
        {rows.map((student) => (
          <tr key={student.id}>
            <td>{student.name}</td>
            <td>{student.code}</td>
            <td>
              <button disabled={loading} onClick={() => onSelect(student)}>
                {action}
              </button>
            </td>
          </tr>
        ))}
      </DirectoryTable>
    </>
  );
}
