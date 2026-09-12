import { SmartTable } from "./DirectoryTable";
import { useEffect, useState } from "react";
import { api } from "./api";
export function AIProviders({ org }: { org: string }) {
  const [items, setItems] = useState<
      { id: string; label: string; model: string | null; configured: boolean }[]
    >([]),
    [error, setError] = useState("");
  useEffect(() => {
    let active = true;
    api<typeof items>(`/organisations/${org}/attendance/ai/providers`)
      .then((v) => active && setItems(v))
      .catch((e) => active && setError(e.message));
    return () => {
      active = false;
    };
  }, [org]);
  return (
    <section>
      <h4>AI connections</h4>
      <p>
        Choose a connected provider when reviewing a saved attendance photo.
        Keys stay on the server. Connection readiness means a key and model are
        configured; it does not mean a live API test has passed.
      </p>
      {error && <p role="alert">{error}</p>}
      <div className="permission-table-scroll">
        <SmartTable>
          <thead>
            <tr>
              <th>Provider</th>
              <th>Vision model</th>
              <th>Readiness</th>
            </tr>
          </thead>
          <tbody>
            {items.map((p) => (
              <tr key={p.id}>
                <td>{p.label}</td>
                <td>{p.model || "Not configured"}</td>
                <td>
                  {p.configured
                    ? "Configured; test with a sample"
                    : "Needs server configuration"}
                </td>
              </tr>
            ))}
          </tbody>
        </SmartTable>
      </div>
      <h4>What the analysis does</h4>
      <ul>
        <li>
          Group photo: image-quality observations and approximate visible count.
        </li>
        <li>
          Physical register: read visible names/codes and marks, then offer
          matched entries as draft attendance.
        </li>
        <li>
          Staff confirm the date, learners and marks before saving attendance.
        </li>
      </ul>
      <p>
        Recognising learners by face is a separate integration requiring
        reference-photo enrolment and a dedicated matching engine. These four
        vision connections do not provide that workflow.
      </p>
      <p>
        AI analysis requires the Run AI analysis and View private photos
        permissions. The pilot allows 20 requests per organisation per UTC day;
        repeat completed requests reuse their result.
      </p>
    </section>
  );
}
