import { useEffect, useState, type FormEvent } from "react";
import { api } from "./api";

export type AcademicGroup = {
  id: string;
  name: string;
  display_name?: string;
  centre_id: string;
  centre_name: string;
  class_id: string | null;
  class_name?: string;
  academic_year_id?: string;
  year_name?: string;
  archived: boolean;
};
type Centre = { id: string; name: string; archived: boolean };
type Year = {
  id: string;
  name: string;
  starts_on: string;
  ends_on: string;
  archived: boolean;
};
type Class = {
  id: string;
  name: string;
  centre_id: string;
  centre_name: string;
  academic_year_id: string;
  year_name: string;
  archived: boolean;
  year_archived: boolean;
};
const values = (e: FormEvent<HTMLFormElement>) => {
  e.preventDefault();
  return Object.fromEntries(new FormData(e.currentTarget));
};
export function AcademicStructure({
  org,
  centres,
  groups,
  permissions,
  scope,
  onRefresh,
  onStudents,
  onAttendance,
}: {
  org: string;
  centres: Centre[];
  groups: AcademicGroup[];
  permissions: string[];
  scope: string;
  onRefresh: () => Promise<void>;
  onStudents: (id: string) => void;
  onAttendance: (id: string) => void;
}) {
  const base = `/organisations/${org}`;
  const [years, setYears] = useState<Year[]>([]),
    [classes, setClasses] = useState<Class[]>([]),
    [error, setError] = useState(""),
    [notice, setNotice] = useState(""),
    [busy, setBusy] = useState(false),
    [loading, setLoading] = useState(true);
  const [centreFilter, setCentreFilter] = useState(""),
    [yearFilter, setYearFilter] = useState(""),
    [showArchived, setShowArchived] = useState(false),
    [edit, setEdit] = useState<AcademicGroup | null>(null),
    [selectedClass, setSelectedClass] = useState("");
  const can = (p: string) => permissions.includes(p),
    canCreate = can("groups.create") && scope !== "groups";
  async function load() {
    const [y, c] = await Promise.all([
      api<Year[]>(base + "/academic-years"),
      api<Class[]>(base + "/classes"),
    ]);
    setYears(y);
    setClasses(c);
  }
  useEffect(() => {
    let active = true;
    Promise.all([
      api<Year[]>(base + "/academic-years"),
      api<Class[]>(base + "/classes"),
    ])
      .then(([y, c]) => {
        if (active) {
          setYears(y);
          setClasses(c);
          setLoading(false);
        }
      })
      .catch((e) => {
        if (active) {
          setError(e.message);
          setLoading(false);
        }
      });
    return () => {
      active = false;
    };
  }, [org]);
  async function act(run: () => Promise<unknown>, message: string) {
    setBusy(true);
    setError("");
    setNotice("");
    try {
      await run();
      await load();
      await onRefresh();
      setNotice(message);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Unable to save.");
    } finally {
      setBusy(false);
    }
  }
  const availableClasses = classes.filter(
    (c) =>
      !c.archived &&
      !c.year_archived &&
      (!edit || c.centre_id === edit.centre_id),
  );
  const visibleGroups = groups.filter(
    (g) =>
      (showArchived || !g.archived) &&
      (!centreFilter || g.centre_id === centreFilter),
  );
  function section(g: AcademicGroup) {
    return (
      <div className="record" key={g.id}>
        <strong>{g.name}</strong>
        <p className="muted">
          {g.centre_name}
          {g.archived ? " · Archived" : ""}
        </p>
        <div className="actions">
          {can("learners.view") && (
            <button
              type="button"
              className="secondary"
              onClick={() => onStudents(g.id)}
            >
              Students
            </button>
          )}
          {!g.archived && can("attendance.capture") && (
            <button
              type="button"
              className="secondary"
              onClick={() => onAttendance(g.id)}
            >
              Photo attendance
            </button>
          )}
          {!g.archived && can("groups.edit") && (
            <button
              type="button"
              className="secondary"
              onClick={() => {
                setEdit(g);
                setSelectedClass(g.class_id || "");
              }}
            >
              Edit section / link class
            </button>
          )}
          {!g.archived && can("groups.archive") && (
            <details>
              <summary>Archive section</summary>
              <p>
                Active students must be transferred or archived first. History
                remains available.
              </p>
              <button
                disabled={busy}
                onClick={() =>
                  void act(
                    () => api(`${base}/groups/${g.id}/archive`, "POST", {}),
                    "Section archived.",
                  )
                }
              >
                Confirm archive
              </button>
            </details>
          )}
        </div>
      </div>
    );
  }
  return (
    <div className="academic-structure">
      <p>
        Choose a centre and academic year to manage its classes and sections.
        Unassigned groups keep their existing students and history until you
        link them to a class.
      </p>
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      {notice && <p role="status">{notice}</p>}
      <div className="form-grid">
        <label>
          Filter centre
          <select
            value={centreFilter}
            onChange={(e) => setCentreFilter(e.target.value)}
          >
            <option value="">All accessible centres</option>
            {centres.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name}
                {c.archived ? " (archived)" : ""}
              </option>
            ))}
          </select>
        </label>
        <label>
          Filter academic year
          <select
            value={yearFilter}
            onChange={(e) => setYearFilter(e.target.value)}
          >
            <option value="">All years</option>
            {years.map((y) => (
              <option key={y.id} value={y.id}>
                {y.name}
                {y.archived ? " (archived)" : ""}
              </option>
            ))}
          </select>
        </label>
      </div>
      <label className="check">
        <input
          type="checkbox"
          checked={showArchived}
          onChange={(e) => setShowArchived(e.target.checked)}
        />
        Show archived records
      </label>
      {loading ? (
        <p>Loading classes…</p>
      ) : (
        classes
          .filter(
            (c) =>
              (showArchived || !c.archived) &&
              (!centreFilter || c.centre_id === centreFilter) &&
              (!yearFilter || c.academic_year_id === yearFilter),
          )
          .map((c) => (
            <section className="record" key={c.id}>
              <h4>
                {c.name} · {c.year_name}
                {c.archived ? " · Archived" : ""}
              </h4>
              <p>{c.centre_name}</p>
              {visibleGroups.filter((g) => g.class_id === c.id).map(section)}
              {!visibleGroups.some((g) => g.class_id === c.id) && (
                <p>No sections in your access scope.</p>
              )}
              <div className="actions">
                {canCreate && !c.archived && !c.year_archived && (
                  <button
                    type="button"
                    onClick={() => {
                      setEdit(null);
                      setSelectedClass(c.id);
                    }}
                  >
                    Add section
                  </button>
                )}
                {can("groups.archive") && scope !== "groups" && !c.archived && (
                  <details>
                    <summary>Archive class</summary>
                    <p>Archive all active sections first.</p>
                    <button
                      disabled={busy}
                      onClick={() =>
                        void act(
                          () =>
                            api(`${base}/classes/${c.id}/archive`, "POST", {}),
                          "Class archived.",
                        )
                      }
                    >
                      Confirm archive class
                    </button>
                  </details>
                )}
              </div>
            </section>
          ))
      )}
      {!loading && !classes.length && (
        <p>
          No classes yet. Add an academic year, then a class and section below.
        </p>
      )}
      {visibleGroups.some((g) => !g.class_id) && (
        <details open>
          <summary>Unassigned groups</summary>
          <p>
            These groups have not been assigned to an academic year. They remain
            visible here regardless of the year filter.
          </p>
          {visibleGroups.filter((g) => !g.class_id).map(section)}
        </details>
      )}
      {(edit ? can("groups.edit") : canCreate) && (
        <details open={!!edit || !!selectedClass} className="record">
          <summary>
            {edit ? "Edit section / link class" : "Add section"}
          </summary>
          <form
            key={edit?.id || "new"}
            onSubmit={(e) => {
              const b = values(e);
              const klass = classes.find((c) => c.id === b.class_id);
              void act(async () => {
                await api(
                  `${base}/groups${edit ? "/" + edit.id : ""}`,
                  edit ? "PATCH" : "POST",
                  {
                    name: b.name,
                    centre_id: edit?.centre_id || klass?.centre_id,
                    class_id: edit?.class_id || b.class_id || null,
                  },
                );
                setEdit(null);
                setSelectedClass("");
              }, "Section saved.");
            }}
          >
            <fieldset disabled={busy}>
              <label>
                Section name
                <input
                  name="name"
                  required
                  maxLength={120}
                  placeholder="e.g. A, B, Morning"
                  defaultValue={edit?.name}
                />
              </label>
              {edit?.class_id ? (
                <>
                  <p>
                    {edit.display_name}. Class/year links cannot be moved; use
                    student transfers for promotion.
                  </p>
                  <input type="hidden" name="class_id" value={edit.class_id} />
                </>
              ) : (
                <label>
                  Class and academic year
                  <select
                    name="class_id"
                    required={!edit}
                    value={selectedClass}
                    onChange={(e) => setSelectedClass(e.target.value)}
                    disabled={scope === "groups"}
                  >
                    <option value="">
                      {edit ? "Keep as unassigned group" : "Choose a class"}
                    </option>
                    {availableClasses.map((c) => (
                      <option key={c.id} value={c.id}>
                        {c.centre_name} / {c.year_name} / {c.name}
                      </option>
                    ))}
                  </select>
                </label>
              )}
              <div className="actions">
                <button>Save section</button>
                <button
                  type="button"
                  className="secondary"
                  onClick={() => {
                    setEdit(null);
                    setSelectedClass("");
                  }}
                >
                  Cancel
                </button>
              </div>
            </fieldset>
          </form>
        </details>
      )}
      {canCreate && (
        <details className="record" open={!classes.length && !loading}>
          <summary>Add class</summary>
          <form
            onSubmit={(e) => {
              const b = values(e);
              void act(async () => {
                const row = await api<Class>(base + "/classes", "POST", b);
                setSelectedClass(row.id);
                setEdit(null);
              }, "Class created. Add its sections next.");
            }}
          >
            <fieldset disabled={busy}>
              <label>
                Centre
                <select name="centre_id" required defaultValue="">
                  <option value="">Choose a centre</option>
                  {centres
                    .filter((c) => !c.archived)
                    .map((c) => (
                      <option key={c.id} value={c.id}>
                        {c.name}
                      </option>
                    ))}
                </select>
              </label>
              <label>
                Academic year
                <select name="academic_year_id" required defaultValue="">
                  <option value="">Choose an academic year</option>
                  {years
                    .filter((y) => !y.archived)
                    .map((y) => (
                      <option key={y.id} value={y.id}>
                        {y.name}
                      </option>
                    ))}
                </select>
              </label>
              <label>
                Class name
                <input
                  name="name"
                  placeholder="e.g. Class 3, Year 1, Foundation"
                  required
                  maxLength={80}
                />
              </label>
              <button>Create class</button>
            </fieldset>
          </form>
        </details>
      )}
      <details className="record" open={!years.length && !loading}>
        <summary>Academic years</summary>
        {years.map((y) => (
          <div className="record" key={y.id}>
            <strong>{y.name}</strong>
            <p>
              {y.starts_on.slice(0, 10)} – {y.ends_on.slice(0, 10)}
              {y.archived ? " · Archived" : ""}
            </p>
            {scope === "organisation" &&
              can("groups.archive") &&
              !y.archived && (
                <details>
                  <summary>Archive year</summary>
                  <p>All classes in this year must be archived first.</p>
                  <button
                    disabled={busy}
                    onClick={() =>
                      void act(
                        () =>
                          api(
                            `${base}/academic-years/${y.id}/archive`,
                            "POST",
                            {},
                          ),
                        "Academic year archived.",
                      )
                    }
                  >
                    Confirm archive year
                  </button>
                </details>
              )}
          </div>
        ))}
        {canCreate && scope === "organisation" ? (
          <form
            onSubmit={(e) => {
              const b = values(e);
              void act(
                () => api(base + "/academic-years", "POST", b),
                "Academic year created.",
              );
            }}
          >
            <fieldset disabled={busy}>
              <label>
                Year name
                <input
                  name="name"
                  placeholder="e.g. 2026–27"
                  required
                  maxLength={80}
                />
              </label>
              <div className="form-grid">
                <label>
                  Start date
                  <input type="date" name="starts_on" required />
                </label>
                <label>
                  End date
                  <input type="date" name="ends_on" required />
                </label>
              </div>
              <button>Create academic year</button>
            </fieldset>
          </form>
        ) : (
          <p>
            Organisation-wide staff with create permission manage new academic
            years.
          </p>
        )}
      </details>
    </div>
  );
}
