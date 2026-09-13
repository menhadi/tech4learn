import { CentreLocation } from "./CentreLocation";
import { ExamElite } from "./ExamElite";
import { ExamWorkspace } from "./ExamWorkspace";
import { GroupedMenu, plannedPages, organisationMenu } from "./GroupedMenu";
import { DraftForm } from "./DraftForm";
import { DirectoryTable, RecordStatus,emptyTableQuery,type TableQuery } from "./DirectoryTable";
import { useEffect, useState, type FormEvent } from "react";
import type { Invitation, Organisation } from "@tech4learn/contracts";
import { api } from "./api";
import { OrganisationSetup } from "./OrganisationSetup";
import { CustomFields } from "./CustomFields";
import { Learners } from "./Learners";
import { Attendance } from "./Attendance";
import { createPortal } from "react-dom";
import { PermissionMatrix } from "./PermissionMatrix";
import { AIProviders } from "./AIProviders";
import { AcademicStructure, type AcademicGroup } from "./AcademicStructure";
type Scope = {
  scope_type: "organisation" | "centres" | "groups";
  scope_ids: string[];
};
type Access = Scope & {
  roleId: string | null;
  roleName: string;
  permissions: string[];
  owner: boolean;
};
type Role = {
  id: string;
  name: string;
  permissions: string[];
  protected: boolean;
};
type Centre = {
  id: string;
  name: string;
  address: string;
  centre_type: string;
  latitude: number | null;
  longitude: number | null;
  radius: number;
  location_approved: boolean;
  archived: boolean;
};
type Group = AcademicGroup;
type Member = Scope & {
  user_id: string;
  name: string;
  email: string;
  role_id: string;
  role_name: string;
  status: "active" | "suspended";
  protected: boolean;
};
type Audit = {
  id: string;
  action: string;
  actor_name: string | null;
  created_at: string;
  details: unknown;
};
type Snapshot = {
  access: Access;
  modules?: Record<string, boolean>;
  catalogue: { key: string; label: string; requires?: string[] }[];
  roles: Role[];
  members: Member[];
  centres: Centre[];
  groups: Group[];
};
const errorText = (e: unknown) =>
  e instanceof Error ? e.message : "Unable to connect.";
const form = (e: FormEvent<HTMLFormElement>) => {
  e.preventDefault();
  return Object.fromEntries(new FormData(e.currentTarget));
};
export function OrganisationWorkspace({
  organisation: org,
  userId,
  superadmin,
  onInvitation,
  onProfile,
  onNavigate,
}: {
  organisation: Organisation;
  userId: string;
  superadmin: boolean;
  onInvitation: (invite: Invitation) => void;
  onProfile: (org: Organisation) => void;
  onNavigate?: () => void;
}) {
  const [data, setData] = useState<Snapshot | null>(null),
    [tab, setTab] = useState("Daily overview"),
    [error, setError] = useState(""),
    [notice, setNotice] = useState(""),
    [busy, setBusy] = useState(false);
  const [role, setRole] = useState<Role | null>(null),
    [rolePermissions, setRolePermissions] = useState<string[]>([
      "organisation.view",
    ]);
  const [member, setMember] = useState<Member | null>(null),
    [grantRole, setGrantRole] = useState(""),
    [scope, setScope] = useState<Scope>({
      scope_type: "organisation",
      scope_ids: [],
    });
  const [centre, setCentre] = useState<Centre | null>(null),
    [focusGroup, setFocusGroup] = useState(""),
    [history, setHistory] = useState<Audit[]>([]),
    [historyQuery,setHistoryQuery]=useState<TableQuery>({...emptyTableQuery,sort:"created_at",direction:"desc"}),
    [historyCounts,setHistoryCounts]=useState({total:0,filtered:0});
  const [locationCentre, setLocationCentre] = useState<Centre | null>(null);
  const [showCentreEditor, setShowCentreEditor] = useState(false);
  const [revision, setRevision] = useState(0);
  const base = `/organisations/${org.id}`;
  async function load() {
    const current = await api<
      Pick<Snapshot, "access" | "catalogue" | "modules">
    >(`${base}/access`);
    const get = <T,>(permission: string, path: string) =>
      current.access.permissions.includes(permission)
        ? api<T[]>(base + path)
        : Promise.resolve([] as T[]);
    const [roles, members, centres, groups] = await Promise.all([
      get<Role>("roles.view", "/roles"),
      get<Member>("members.view", "/members"),
      get<Centre>("centres.view", "/centres"),
      get<Group>("groups.view", "/groups"),
    ]);
    return { ...current, roles, members, centres, groups };
  }
  useEffect(() => {
    let active = true;
    load()
      .then((d) => {
        if (active) {
          setData(d);
          setGrantRole(d.roles.find((r) => r.name === "Viewer")?.id || "");
        }
      })
      .catch((e) => {
        if (active) setError(errorText(e));
      });
    return () => {
      active = false;
    };
  }, [org.id]);
  useEffect(() => {
    let active = true;
    if (tab === "History") {
      setHistory([]);
      api<{rows:Audit[];total:number;filtered:number}>(`${base}/audit-directory?query=${encodeURIComponent(JSON.stringify(historyQuery))}`)
        .then((rows) => {
          if (active) {setHistory(rows.rows);setHistoryCounts({total:rows.total,filtered:rows.filtered});}
        })
        .catch((e) => {
          if (active) setError(errorText(e));
        });
    }
    return () => {
      active = false;
    };
  }, [tab, historyQuery, org.id]);
  const can = (p: string) => !!data?.access.permissions.includes(p);
  async function act(work: () => Promise<unknown>, success = "Saved.") {
    setBusy(true);
    setError("");
    setNotice("");
    try {
      await work();
      setData(await load());
      setRevision((value) => value + 1);
      setNotice(success);
    } catch (e) {
      setError(errorText(e));
    } finally {
      setBusy(false);
    }
  }
  const tabs = [
    "Daily overview",
    ...(can("attendance.view") && data?.modules?.attendance === true
      ? ["AI connections"]
      : []),
    "Profile",
    ...(can("configuration.view") ? ["Setup"] : []),
    ...(can("fields.view") ||
    can("fields.manage") ||
    can("centres.view") ||
    can("groups.view")
      ? ["Custom fields"]
      : []),
    ...(can("learners.view") && data?.modules?.learners !== false
      ? ["Learners"]
      : []),
    ...(can("centres.view") ? ["Centres"] : []),
    ...(can("attendance.view") && data?.modules?.attendance === true
      ? ["Attendance"]
      : []),
    ...(can("attendance.capture") && data?.modules?.attendance === true
      ? ["Photo capture"]
      : []),
    ...(can("groups.view") ? ["Groups"] : []),
    ...(can("roles.view") ? ["Roles"] : []),
    ...(can("members.view") ? ["Team"] : []),
    ...(can("audit.view") ? ["History"] : []),
  ];
  if (can("exams.manage") && data?.modules?.exams === true) tabs.push("Exam workspace");
  if (can("configuration.view") || can("exams.manage")) tabs.push("Exam results");
  const upcoming = [...(can("groups.view") ? ["FLN workspace"] : []),...(can("configuration.view") ? ["Email settings","Email templates","Message settings","Delivery history"] : [])];
  const menuGroups=organisationMenu(org.centre_label,tabs,upcoming);
  const currentGroup=menuGroups.find(g=>g.items.some(i=>i.id===tab));
  const currentLabel=currentGroup?.items.find(i=>i.id===tab)?.label || tab;
  function chooseMember(m: Member | null) {
    setMember(m);
    setGrantRole(
      m?.role_id || data?.roles.find((r) => r.name === "Viewer")?.id || "",
    );
    setScope(
      m
        ? { scope_type: m.scope_type, scope_ids: m.scope_ids }
        : { scope_type: "organisation", scope_ids: [] },
    );
  }
  const grant = { role_id: grantRole, ...scope };
  const selectedRole = data?.roles.find((r) => r.id === grantRole);
  const wide = selectedRole?.permissions.some((p) =>
    [
      "attendance.policy",
      "configuration.view",
      "configuration.manage",
      "fields.manage",
      "organisation.edit",
      "centres.create",
      "roles.view",
      "roles.manage",
      "members.view",
      "members.manage",
      "audit.view",
      "audit.export",
    ].includes(p),
  );
  return (
    <section className="panel org-workspace">
      <div className="org-heading">
        <span className="org-mark large" aria-hidden="true" />
        <div>
          <p className="eyebrow">Organisation workspace</p>
          <h2>{org.name}</h2>
        </div>
      </div>
      {data && (
        <p className="muted">
          {data.access.roleName} ·{" "}
          {data.access.scope_type === "organisation"
            ? "Whole organisation"
            : `Assigned ${data.access.scope_type}`}
        </p>
      )}
      {error && (
        <p role="alert" className="error">
          {error}
        </p>
      )}
      {notice && <p role="status">{notice}</p>}
      {!data ? (
        <p>Loading access…</p>
      ) : (
        <>
          {(() => {
            const menu = (
              <GroupedMenu label="Organisation sections" active={tab} groups={menuGroups} onSelect={(next)=>{setTab(next);setFocusGroup("");onNavigate?.();setError("");setNotice("");}} />
            );
            const target = document.getElementById("organisation-menu-slot");
            return target ? createPortal(menu, target) : menu;
          })()}
          <div className="workspace-breadcrumb" aria-label="Breadcrumb"><span>{org.name}</span><span aria-hidden="true">/</span><span>{currentGroup?.label}</span><span aria-hidden="true">/</span><strong>{currentLabel}</strong></div>
          <h3 className="workspace-page-title">{currentLabel}</h3>
          {can("exams.manage") && tab === "Exam workspace" && <ExamWorkspace key={org.id} org={org.id} />}
          {tab === "Exam results" && <>{can("exams.manage") && <ExamWorkspace key={org.id} org={org.id} resultsOnly />}{can("configuration.view") && <details><summary>Previously linked ExamElite results</summary><ExamElite key={`${org.id}-${tab}`} org={org.id} results /></details>}</>}
          {upcoming.includes(tab) && plannedPages[tab] && <section className="planned-workspace"><span className="feature-planned">Planned integration</span><h3>{plannedPages[tab].title}</h3><p>{plannedPages[tab].description}</p><h4>What will be available</h4><ul>{plannedPages[tab].items.map(line=><li key={line}>{line}</li>)}</ul>{plannedPages[tab].academics&&<button type="button" onClick={()=>setTab("Groups")}>Open classes & sections</button>}</section>}
          {tab === "AI connections" && <AIProviders org={org.id} />}
          {tab === "Daily overview" && (
            <>
              <div className="daily-summary">
                {can("centres.view") && (
                  <button
                    className="summary-card"
                    onClick={() => setTab("Centres")}
                  >
                    <strong>
                      {data.centres.filter((c) => !c.archived).length}
                    </strong>
                    <span>
                      Active {org.centre_label.toLowerCase()}s in your scope
                    </span>
                  </button>
                )}
                {can("groups.view") && (
                  <button
                    className="summary-card"
                    onClick={() => setTab("Groups")}
                  >
                    <strong>
                      {data.groups.filter((g) => !g.archived).length}
                    </strong>
                    <span>Active groups in your scope</span>
                  </button>
                )}
              </div>
              {can("attendance.view") && data.modules?.attendance === true ? (
                <Attendance
                  org={org.id}
                  permissions={data.access.permissions}
                  groups={data.groups}
                  mode="daily"
                />
              ) : (
                <p>
                  Choose a section in the left menu to manage your organisation.
                  Daily attendance appears here when the module is enabled and
                  your role has access.
                </p>
              )}
            </>
          )}
          {tab === "Setup" && (
            <OrganisationSetup
              org={org.id}
              slug={org.slug}
              superadmin={superadmin}
              editable={can("configuration.manage")}
              onSaved={() => {
                void load()
                  .then(setData)
                  .catch((e) => setError(errorText(e)));
                window.dispatchEvent(new Event("branding-updated"));
              }}
            />
          )}
          {tab === "Custom fields" && (
            <CustomFields
              org={org.id}
              permissions={data.access.permissions}
              records={{
                organisation: [{ id: org.id, name: org.name }],
                centres: data.centres,
                groups: data.groups,
                staff: data.members.map((m) => ({
                  id: m.user_id,
                  name: m.name,
                })),
              }}
            />
          )}
          {tab === "Learners" && (
            <Learners
              key={`${org.id}-${focusGroup}`}
              initialGroup={focusGroup}
              org={org.id}
              permissions={data.access.permissions}
              groups={data.groups}
            />
          )}
          {(tab === "Attendance" || tab === "Photo capture") && (
            <Attendance
              key={`${org.id}-${tab}-${focusGroup}`}
              initialGroup={focusGroup}
              mode={tab === "Photo capture" ? "capture" : "daily"}
              org={org.id}
              permissions={data.access.permissions}
              groups={data.groups}
            />
          )}
          {tab === "Profile" && (
            <DraftForm title="Organisation profile" draftKey={"organisation-profile"}
              onSubmit={(e) => {
                const body = form(e);
                void act(async () =>
                  onProfile(await api<Organisation>(base, "PATCH", body)),
                );
              }}
            >
              <fieldset disabled={busy || !can("organisation.edit")}>
                <label>
                  Display name
                  <input
                    name="name"
                    defaultValue={org.name}
                    maxLength={120}
                    required
                  />
                </label>
                <div className="form-grid">
                  <label>
                    Brand colour
                    <input
                      name="colour"
                      type="color"
                      defaultValue={org.colour}
                    />
                  </label>
                  <label>
                    Your word for a centre
                    <input
                      name="centre_label"
                      defaultValue={org.centre_label}
                      maxLength={40}
                      required
                    />
                  </label>
                </div>
                {can("organisation.edit") && <button>Save settings</button>}
              </fieldset>
              {!can("organisation.edit") && (
                <p className="muted">You have view access to this profile.</p>
              )}
            </DraftForm>
          )}
          {tab === "Centres" && (
            <>
              <h3>{org.centre_label} directory</h3>
              <p className="muted">
                Add each location separately, then create its groups and assign
                staff and learners. Approved coordinates are used for attendance
                location checks.
              </p>
              {can("centres.create") && (
                <button
                  type="button"
                  onClick={() => {
                    setCentre(null);
                    setLocationCentre(null);
                    setShowCentreEditor(true);
                    requestAnimationFrame(() =>
                      document.getElementById("centre-editor")?.scrollIntoView({
                        behavior: "smooth",
                        block: "start",
                      }),
                    );
                  }}
                >
                  + Add {org.centre_label.toLowerCase()}
                </button>
              )}
              {!data.centres.length && (
                <p>No centres in your access scope yet.</p>
              )}
              <DirectoryTable
                title={`${org.centre_label} directory`}
                columns={[
                  org.centre_label,
                  "Type",
                  "Address",
                  "Location",
                  "Status",
                  "Actions",
                ]}
              >
                {data.centres.map((c) => (
                  <tr key={c.id}>
                    <th scope="row">
                      <strong>{c.name}</strong>
                    </th>
                    <td className="type-cell">
                      {c.centre_type.replaceAll("_", " ")}
                    </td>
                    <td>
                      {c.address || <span className="muted">Not set</span>}
                    </td>
                    <td>
                      <span
                        className={`status-badge ${c.location_approved ? "status-active" : "status-warning"}`}
                      >
                        {c.location_approved
                          ? "Verified"
                          : c.latitude === null
                            ? "Not configured"
                            : "Needs review"}
                      </span>
                      <small className="cell-note">
                        {c.latitude === null
                          ? "Add coordinates to verify attendance"
                          : `${c.latitude}, ${c.longitude} · ${c.radius} m`}
                      </small>
                    </td>
                    <td>
                      <RecordStatus archived={c.archived} />
                    </td>
                    <td className="row-actions">
                      {!c.archived && data.access.scope_type !== "groups" && (
                        <div className="actions">
                          {can("centres.edit") && (
                            <button
                              type="button"
                              className="secondary"
                              onClick={() => {
                                setCentre(c);
                                setLocationCentre(null);
                                setShowCentreEditor(true);
                                requestAnimationFrame(() =>
                                  document
                                    .getElementById("centre-editor")
                                    ?.scrollIntoView({
                                      behavior: "smooth",
                                      block: "start",
                                    }),
                                );
                              }}
                            >
                              Edit
                            </button>
                          )}
                          {can("centres.edit") && (
                            <button type="button" className="secondary" onClick={() => {
                              setLocationCentre(c);
                              setShowCentreEditor(false);
                              requestAnimationFrame(() => document.getElementById("centre-location-editor")?.scrollIntoView({behavior:"smooth",block:"start"}));
                            }}>Update location</button>
                          )}
                          {can("centres.approve") &&
                            !c.location_approved &&
                            c.latitude !== null && (
                              <button
                                type="button"
                                disabled={busy}
                                onClick={() =>
                                  void act(
                                    () =>
                                      api(
                                        `${base}/centres/${c.id}/approve`,
                                        "POST",
                                        {},
                                      ),
                                    "Location approved.",
                                  )
                                }
                              >
                                Approve location
                              </button>
                            )}
                          {can("centres.archive") && (
                            <details>
                              <summary>Archive centre</summary>
                              <p>
                                Active groups must be archived first. The centre
                                and its history will be retained.
                              </p>
                              <button
                                type="button"
                                disabled={busy}
                                onClick={() =>
                                  void act(
                                    () =>
                                      api(
                                        `${base}/centres/${c.id}/archive`,
                                        "POST",
                                        {},
                                      ),
                                    "Centre archived.",
                                  )
                                }
                              >
                                Confirm archive
                              </button>
                            </details>
                          )}
                        </div>
                      )}
                    </td>
                  </tr>
                ))}
              </DirectoryTable>
              {locationCentre && can("centres.edit") && data.access.scope_type !== "groups" && (
                <DraftForm title="Update centre location" draftKey={`centre-location:${locationCentre.id}`} key={locationCentre.id} id="centre-location-editor"
                  onSubmit={e => {
                    const b = form(e);
                    void act(async () => {
                      await api(`${base}/centres/${locationCentre.id}/location`, "PATCH", {
                        latitude: b.latitude === "" ? null : Number(b.latitude),
                        longitude: b.longitude === "" ? null : Number(b.longitude),
                        radius: Number(b.radius),
                      });
                      setLocationCentre(null);
                    }, "Location saved. Use Approve location in this centre's row to approve the change.");
                  }}>
                  <h3>Update location: {locationCentre.name}</h3>
                  <fieldset disabled={busy}>
                    <CentreLocation latitude={locationCentre.latitude} longitude={locationCentre.longitude} />
                    <label>Allowed distance from centre (metres)<input name="radius" type="number" min={10} max={10000} step={1} defaultValue={locationCentre.radius} required /></label>
                    <div className="actions"><button>Save location for approval</button><button type="button" className="secondary" onClick={()=>setLocationCentre(null)}>Cancel</button></div>
                  </fieldset>
                </DraftForm>
              )}
              {showCentreEditor && (centre ? can("centres.edit") : can("centres.create")) &&
                data.access.scope_type !== "groups" && (
                  <DraftForm title="Centre details" draftKey={`centre:${centre?.id||"new"}`}
                    id="centre-editor"
                    key={centre?.id || `new-centre-${revision}`}
                    onSubmit={(e) => {
                      const b = form(e);
                      void act(async () => {
                        await api(
                          `${base}/centres${centre ? "/" + centre.id : ""}`,
                          centre ? "PATCH" : "POST",
                          {
                            ...b,
                            latitude:
                              b.latitude === "" ? null : Number(b.latitude),
                            longitude:
                              b.longitude === "" ? null : Number(b.longitude),
                            radius: Number(b.radius),
                          },
                        );
                        setCentre(null);
                        setShowCentreEditor(false);
                      }, "Centre saved. Changed coordinates need approval.");
                    }}
                  >
                    <h3>
                      {centre ? "Edit" : "Add"} {org.centre_label.toLowerCase()}
                    </h3>
                    <fieldset disabled={busy}>
                      <label>
                        Name
                        <input
                          name="name"
                          defaultValue={centre?.name}
                          maxLength={120}
                          required
                        />
                      </label>
                      <label>
                        Village / address
                        <input
                          name="address"
                          defaultValue={centre?.address}
                          maxLength={500}
                        />
                      </label>
                      <label>
                        Centre type
                        <select
                          name="centre_type"
                          defaultValue={
                            centre?.centre_type || "learning_centre"
                          }
                        >
                          <option value="school">School</option>
                          <option value="college">College</option>
                          <option value="coaching">Coaching centre</option>
                          <option value="community_centre">
                            Community centre
                          </option>
                          <option value="learning_centre">
                            Learning centre
                          </option>
                          <option value="training_centre">
                            Training centre
                          </option>
                          <option value="other">Other</option>
                        </select>
                      </label>
                      <CentreLocation latitude={centre?.latitude ?? null} longitude={centre?.longitude ?? null} />
                      <label>
                        Allowed distance from centre (metres; default 100)
                        <input
                          name="radius"
                          type="number"
                          min={10}
                          max={10000}
                          step={1}
                          defaultValue={centre?.radius ?? 100}
                          required
                        />
                      </label>
                      <div className="actions">
                        <button>Save centre</button>
                        {centre && (
                          <button
                            type="button"
                            className="secondary"
                            onClick={() => { setCentre(null); setShowCentreEditor(false); }}
                          >
                            Cancel edit
                          </button>
                        )}
                      </div>
                    </fieldset>
                  </DraftForm>
                )}
            </>
          )}
          {tab === "Groups" && (
            <AcademicStructure
              org={org.id}
              centres={data.centres}
              groups={data.groups}
              permissions={data.access.permissions.filter(
                (p) =>
                  (!p.startsWith("attendance.") ||
                    data.modules?.attendance === true) &&
                  (!p.startsWith("learners.") ||
                    data.modules?.learners !== false),
              )}
              scope={data.access.scope_type}
              onRefresh={async () => {
                setData(await load());
              }}
              onStudents={(id) => {
                setFocusGroup(id);
                setTab("Learners");
              }}
              onAttendance={(id) => {
                setFocusGroup(id);
                setTab("Photo capture");
              }}
            />
          )}
          {tab === "Roles" && (
            <>
              <h3>Roles and permissions</h3>
              <p className="muted">
                Templates are editable starting points. The organisation admin
                safety role stays protected. Assessment and exam permissions
                will be added when those modules are available.
              </p>
              <DirectoryTable
                title="Roles and permissions"
                columns={["Role", "Permissions", "Protection", "Actions"]}
              >
                {data.roles.map((r) => (
                  <tr key={r.id}>
                    <th scope="row">
                      <strong>{r.name}</strong>
                    </th>
                    <td>{r.permissions.length} permissions</td>
                    <td>
                      <span className="status-badge status-neutral">
                        {r.protected ? "Protected" : "Customisable"}
                      </span>
                    </td>
                    <td className="row-actions">
                      <details>
                        <summary>View permissions</summary>
                        <PermissionMatrix
                          catalogue={data.catalogue}
                          selected={r.permissions}
                        />
                      </details>
                      {can("roles.manage") &&
                        !r.protected &&
                        r.id !== data.access.roleId &&
                        r.permissions.every((p) => can(p)) && (
                          <button
                            className="secondary"
                            onClick={() => {
                              setRole(r);
                              setRolePermissions(r.permissions);
                            }}
                          >
                            Edit role
                          </button>
                        )}
                    </td>
                  </tr>
                ))}
              </DirectoryTable>
              {can("roles.manage") && (
                <DraftForm title="Role and permissions" draftKey={`role:${role?.id||"new"}`} draftState={rolePermissions} restoreState={setRolePermissions}
                  key={role?.id || `new-role-${revision}`}
                  onSubmit={(e) => {
                    const b = form(e);
                    void act(async () => {
                      await api(
                        `${base}/roles${role ? "/" + role.id : ""}`,
                        role ? "PATCH" : "POST",
                        { name: b.name, permissions: rolePermissions },
                      );
                      setRole(null);
                      setRolePermissions(["organisation.view"]);
                    });
                  }}
                >
                  <h3>{role ? "Edit role" : "Create custom role"}</h3>
                  <fieldset disabled={busy}>
                    <label>
                      Role name
                      <input
                        name="name"
                        defaultValue={role?.name}
                        required
                        maxLength={80}
                      />
                    </label>
                    <p>
                      Enable each section’s view permission along with its
                      actions. Managing staff also needs view access to roles,
                      centres and groups.
                    </p>
                    <p>
                      Rows and actions come from the available modules. A dash
                      means that action is not implemented. Archive preserves
                      history; it is not permanent deletion.
                    </p>
                    <PermissionMatrix
                      catalogue={data.catalogue}
                      selected={rolePermissions}
                      allowed={data.access.permissions}
                      onChange={setRolePermissions}
                    />
                    <div className="actions">
                      <button>Save role</button>
                      {role && (
                        <button
                          type="button"
                          className="secondary"
                          onClick={() => {
                            setRole(null);
                            setRolePermissions(["organisation.view"]);
                          }}
                        >
                          Cancel edit
                        </button>
                      )}
                    </div>
                  </fieldset>
                </DraftForm>
              )}
            </>
          )}
          {tab === "Team" && (
            <>
              <h3>People and access</h3>
              {!data.members.length && <p>No accepted memberships yet.</p>}
              <DirectoryTable
                title="Staff directory"
                columns={[
                  "Staff member",
                  "Email",
                  "Role",
                  "Access scope",
                  "Status",
                  "Actions",
                ]}
              >
                {data.members.map((m) => (
                  <tr key={m.user_id}>
                    <th scope="row">
                      <strong>
                        {m.name}
                        {m.user_id === userId ? " (you)" : ""}
                      </strong>
                    </th>
                    <td>{m.email}</td>
                    <td>{m.role_name}</td>
                    <td>
                      {m.scope_type === "organisation"
                        ? "Whole organisation"
                        : `Assigned ${m.scope_type}`}
                    </td>
                    <td>
                      <span className="status-badge status-neutral">
                        {m.status}
                      </span>
                    </td>
                    <td className="row-actions">
                      {can("members.manage") &&
                        m.user_id !== userId &&
                        (!m.protected || data.access.owner) && (
                          <button
                            className="secondary"
                            onClick={() => chooseMember(m)}
                          >
                            Change access
                          </button>
                        )}
                    </td>
                  </tr>
                ))}
              </DirectoryTable>
              {can("members.manage") && (
                <DraftForm title="Staff access" draftKey={`member:${member?.user_id||"new"}`} draftState={{grantRole,scope}} restoreState={v=>{setGrantRole(v.grantRole);setScope(v.scope);}}
                  key={member?.user_id || `invite-${revision}`}
                  onSubmit={(e) => {
                    const b = form(e);
                    void act(
                      async () => {
                        if (member)
                          await api(
                            `${base}/members/${member.user_id}`,
                            "PATCH",
                            { ...grant, status: b.status },
                          );
                        else
                          onInvitation(
                            await api<Invitation>(
                              `${base}/invitations`,
                              "POST",
                              { ...grant, email: b.email },
                            ),
                          );
                        chooseMember(null);
                      },
                      member
                        ? "Staff access updated."
                        : "Invitation ready above. Share the link privately.",
                    );
                  }}
                >
                  <h3>
                    {member
                      ? `Access for ${member.name}`
                      : "Invite a colleague"}
                  </h3>
                  <fieldset disabled={busy}>
                    {!member && (
                      <label>
                        Email
                        <input name="email" type="email" required />
                      </label>
                    )}
                    <label>
                      Role
                      <select
                        value={grantRole}
                        onChange={(e) => {
                          setGrantRole(e.target.value);
                          setScope({
                            scope_type: "organisation",
                            scope_ids: [],
                          });
                        }}
                        required
                      >
                        <option value="">Choose a role</option>
                        {data.roles
                          .filter(
                            (r) =>
                              r.permissions.every((p) => can(p)) &&
                              (!r.protected || data.access.owner),
                          )
                          .map((r) => (
                            <option key={r.id} value={r.id}>
                              {r.name}
                            </option>
                          ))}
                      </select>
                    </label>
                    <label>
                      Where this person can work
                      <select
                        value={scope.scope_type}
                        onChange={(e) =>
                          setScope({
                            scope_type: e.target.value as Scope["scope_type"],
                            scope_ids: [],
                          })
                        }
                      >
                        <option value="organisation">Whole organisation</option>
                        {!wide && (
                          <>
                            <option value="centres">Selected centres</option>
                            <option value="groups">Selected groups</option>
                          </>
                        )}
                      </select>
                    </label>
                    {scope.scope_type !== "organisation" && (
                      <div className="permission-grid">
                        {(scope.scope_type === "centres"
                          ? data.centres
                          : data.groups
                        )
                          .filter((r) => !r.archived)
                          .map((r) => (
                            <label className="check" key={r.id}>
                              <input
                                type="checkbox"
                                checked={scope.scope_ids.includes(r.id)}
                                onChange={(e) =>
                                  setScope((old) => ({
                                    ...old,
                                    scope_ids: e.target.checked
                                      ? [...old.scope_ids, r.id]
                                      : old.scope_ids.filter(
                                          (id) => id !== r.id,
                                        ),
                                  }))
                                }
                              />
                              {r.name}
                            </label>
                          ))}
                      </div>
                    )}
                    {wide && (
                      <small>This role needs organisation-wide access.</small>
                    )}
                    {member && (
                      <label>
                        Status
                        <select name="status" defaultValue={member.status}>
                          <option value="active">Active</option>
                          <option value="suspended">Suspended</option>
                        </select>
                      </label>
                    )}
                    <p className="muted">
                      {member
                        ? "Access changes apply to subsequent requests, including existing sessions."
                        : "The invitation lasts three days. No email is sent automatically. A new invitation replaces the previous pending link for this email."}
                    </p>
                    <div className="actions">
                      <button>
                        {member ? "Save access" : "Prepare invitation"}
                      </button>
                      {member && (
                        <button
                          type="button"
                          className="secondary"
                          onClick={() => chooseMember(null)}
                        >
                          Cancel edit
                        </button>
                      )}
                    </div>
                  </fieldset>
                </DraftForm>
              )}
            </>
          )}
          {tab === "History" && (
            <>
              <h3>Change history</h3>
              <DirectoryTable title="Change history" columns={["Event","Staff member","Time","Details"]} columnKeys={["action","actor_name","created_at","details"]} remote={{query:historyQuery,onChange:q=>setHistoryQuery({...q,sort:q.sort||"created_at"}),...historyCounts}}>
                {history.map(h=><tr key={h.id}><th scope="row">{h.action}</th><td>{h.actor_name||"System"}</td><td>{h.created_at}</td><td>{JSON.stringify(h.details)}</td></tr>)}
              </DirectoryTable>
              <div className="actions">
                {can("audit.export") && (
                  <button
                    disabled={busy}
                    className="secondary"
                    onClick={() =>
                      void act(async () => {
                        const rows = await api<Audit[]>(
                          `${base}/audit/export?offset=${historyQuery.offset}`,
                        );
                        const url = URL.createObjectURL(
                          new Blob([JSON.stringify(rows, null, 2)], {
                            type: "application/json",
                          }),
                        );
                        const a = document.createElement("a");
                        a.href = url;
                        a.download = `tech4learn-history-${org.slug}-${historyQuery.offset}.json`;
                        a.click();
                        setTimeout(() => URL.revokeObjectURL(url), 1000);
                      }, "History page exported.")
                    }
                  >
                    Export up to 50 records from this position
                  </button>
                )}
              </div>
            </>
          )}
        </>
      )}
    </section>
  );
}
