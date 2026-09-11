import { useEffect, useState, type FormEvent } from "react";
import type { Invitation, Organisation } from "@tech4learn/contracts";
import { api } from "./api";
import { OrganisationSetup } from "./OrganisationSetup";
import { CustomFields } from "./CustomFields";
import { Learners } from "./Learners";
import { Attendance } from "./Attendance";

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
  latitude: number | null;
  longitude: number | null;
  radius: number;
  location_approved: boolean;
  archived: boolean;
};
type Group = {
  id: string;
  name: string;
  centre_id: string;
  centre_name: string;
  archived: boolean;
};
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
  catalogue: { key: string; label: string }[];
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
}: {
  organisation: Organisation;
  userId: string;
  superadmin: boolean;
  onInvitation: (invite: Invitation) => void;
  onProfile: (org: Organisation) => void;
}) {
  const [data, setData] = useState<Snapshot | null>(null),
    [tab, setTab] = useState("Profile"),
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
    [group, setGroup] = useState<Group | null>(null),
    [history, setHistory] = useState<Audit[]>([]),
    [offset, setOffset] = useState(0);
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
      api<Audit[]>(`${base}/audit?offset=${offset}`)
        .then((rows) => {
          if (active) setHistory(rows);
        })
        .catch((e) => {
          if (active) setError(errorText(e));
        });
    }
    return () => {
      active = false;
    };
  }, [tab, offset, org.id]);
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
    ...(can("groups.view") ? ["Groups"] : []),
    ...(can("roles.view") ? ["Roles"] : []),
    ...(can("members.view") ? ["Team"] : []),
    ...(can("audit.view") ? ["History"] : []),
  ];
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
          <div className="workspace-tabs" aria-label="Organisation sections">
            {tabs.map((t) => (
              <button
                type="button"
                key={t}
                className={tab === t ? "" : "secondary"}
                aria-pressed={tab === t}
                onClick={() => {
                  setTab(t);
                  setError("");
                  setNotice("");
                }}
              >
                {t}
              </button>
            ))}
          </div>
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
              key={org.id}
              org={org.id}
              permissions={data.access.permissions}
              groups={data.groups}
            />
          )}
          {tab === "Attendance" && (
            <Attendance
              key={org.id}
              org={org.id}
              permissions={data.access.permissions}
              groups={data.groups}
            />
          )}
          {tab === "Profile" && (
            <form
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
            </form>
          )}
          {tab === "Centres" && (
            <>
              <h3>{org.centre_label} directory</h3>
              <p className="muted">
                Approved coordinates will support attendance verification. Photo
                attendance is not available yet.
              </p>
              {!data.centres.length && (
                <p>No centres in your access scope yet.</p>
              )}
              {data.centres.map((c) => (
                <div className="record" key={c.id}>
                  <strong>{c.name}</strong>
                  <p>
                    {c.address || "Address not set"} ·{" "}
                    {c.archived
                      ? "Archived"
                      : c.location_approved
                        ? "Location approved"
                        : "Location needs review"}
                  </p>
                  <small>
                    {c.latitude === null
                      ? "Coordinates not set"
                      : `${c.latitude}, ${c.longitude} · ${c.radius} m`}
                  </small>
                  {!c.archived && data.access.scope_type !== "groups" && (
                    <div className="actions">
                      {can("centres.edit") && (
                        <button
                          type="button"
                          className="secondary"
                          onClick={() => setCentre(c)}
                        >
                          Edit
                        </button>
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
                            Active groups must be archived first. The centre and
                            its history will be retained.
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
                </div>
              ))}
              {(centre ? can("centres.edit") : can("centres.create")) &&
                data.access.scope_type !== "groups" && (
                  <form
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
                      <div className="form-grid">
                        <label>
                          Latitude
                          <input
                            name="latitude"
                            type="number"
                            step="any"
                            min={-90}
                            max={90}
                            defaultValue={centre?.latitude ?? ""}
                          />
                        </label>
                        <label>
                          Longitude
                          <input
                            name="longitude"
                            type="number"
                            step="any"
                            min={-180}
                            max={180}
                            defaultValue={centre?.longitude ?? ""}
                          />
                        </label>
                      </div>
                      <label>
                        Allowed radius (metres)
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
                            onClick={() => setCentre(null)}
                          >
                            Cancel edit
                          </button>
                        )}
                      </div>
                    </fieldset>
                  </form>
                )}
            </>
          )}
          {tab === "Groups" && (
            <>
              <h3>Learning groups</h3>
              {!data.groups.length && (
                <p>No groups in your access scope yet.</p>
              )}
              {data.groups.map((g) => (
                <div className="record" key={g.id}>
                  <strong>{g.name}</strong>
                  <p>
                    {g.centre_name}
                    {g.archived ? " · Archived" : ""}
                  </p>
                  {!g.archived && (
                    <div className="actions">
                      {can("groups.edit") && (
                        <button
                          className="secondary"
                          onClick={() => setGroup(g)}
                        >
                          Edit
                        </button>
                      )}
                      {can("groups.archive") && (
                        <details>
                          <summary>Archive group</summary>
                          <p>The group and its history will be retained.</p>
                          <button
                            disabled={busy}
                            onClick={() =>
                              void act(
                                () =>
                                  api(
                                    `${base}/groups/${g.id}/archive`,
                                    "POST",
                                    {},
                                  ),
                                "Group archived.",
                              )
                            }
                          >
                            Confirm archive
                          </button>
                        </details>
                      )}
                    </div>
                  )}
                </div>
              ))}
              {(group
                ? can("groups.edit")
                : can("groups.create") &&
                  data.access.scope_type !== "groups") && (
                <form
                  key={group?.id || `new-group-${revision}`}
                  onSubmit={(e) => {
                    const b = form(e);
                    void act(async () => {
                      await api(
                        `${base}/groups${group ? "/" + group.id : ""}`,
                        group ? "PATCH" : "POST",
                        b,
                      );
                      setGroup(null);
                    });
                  }}
                >
                  <h3>{group ? "Edit group" : "Add group"}</h3>
                  <fieldset disabled={busy}>
                    <label>
                      Name
                      <input
                        name="name"
                        defaultValue={group?.name}
                        maxLength={120}
                        required
                      />
                    </label>
                    {group ? (
                      <p>Centre: {group.centre_name}</p>
                    ) : (
                      <label>
                        {org.centre_label}
                        <select name="centre_id" required defaultValue="">
                          <option value="">Choose a centre</option>
                          {data.centres
                            .filter((c) => !c.archived)
                            .map((c) => (
                              <option key={c.id} value={c.id}>
                                {c.name}
                              </option>
                            ))}
                        </select>
                      </label>
                    )}
                    <div className="actions">
                      <button>Save group</button>
                      {group && (
                        <button
                          type="button"
                          className="secondary"
                          onClick={() => setGroup(null)}
                        >
                          Cancel edit
                        </button>
                      )}
                    </div>
                  </fieldset>
                </form>
              )}
            </>
          )}
          {tab === "Roles" && (
            <>
              <h3>Roles and permissions</h3>
              <p className="muted">
                Templates are editable starting points. The organisation admin
                safety role stays protected. Assessment and exam permissions
                will be added when those modules are available.
              </p>
              {data.roles.map((r) => (
                <div className="record" key={r.id}>
                  <strong>
                    {r.name}
                    {r.protected ? " · Protected" : ""}
                  </strong>
                  <p>{r.permissions.length} permissions</p>
                  <details>
                    <summary>View permissions</summary>
                    <ul>
                      {r.permissions.map((p) => (
                        <li key={p}>
                          {data.catalogue.find((c) => c.key === p)?.label || p}
                        </li>
                      ))}
                    </ul>
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
                </div>
              ))}
              {can("roles.manage") && (
                <form
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
                    <div className="permission-grid">
                      {data.catalogue.map((p) => (
                        <label className="check" key={p.key}>
                          <input
                            type="checkbox"
                            disabled={
                              !can(p.key) || p.key === "organisation.view"
                            }
                            checked={rolePermissions.includes(p.key)}
                            onChange={(e) =>
                              setRolePermissions((old) =>
                                e.target.checked
                                  ? [...old, p.key]
                                  : old.filter((k) => k !== p.key),
                              )
                            }
                          />
                          {p.label}
                        </label>
                      ))}
                    </div>
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
                </form>
              )}
            </>
          )}
          {tab === "Team" && (
            <>
              <h3>People and access</h3>
              {!data.members.length && <p>No accepted memberships yet.</p>}
              {data.members.map((m) => (
                <div className="record" key={m.user_id}>
                  <strong>
                    {m.name}
                    {m.user_id === userId ? " (you)" : ""}
                  </strong>
                  <p>
                    {m.email}
                    <br />
                    {m.role_name} · {m.status} ·{" "}
                    {m.scope_type === "organisation"
                      ? "Whole organisation"
                      : `Assigned ${m.scope_type}`}
                  </p>
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
                </div>
              ))}
              {can("members.manage") && (
                <form
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
                </form>
              )}
            </>
          )}
          {tab === "History" && (
            <>
              <h3>Change history</h3>
              <p className="muted">
                Organisation events, newest first. Each page contains up to 50
                events.
              </p>
              {history.map((h) => (
                <div className="record" key={h.id}>
                  <strong>{h.action}</strong>
                  <p>
                    {h.actor_name || "System"} ·{" "}
                    {new Date(h.created_at).toLocaleString()}
                  </p>
                  <details>
                    <summary>Details</summary>
                    <pre>{JSON.stringify(h.details, null, 2)}</pre>
                  </details>
                </div>
              ))}
              {!history.length && <p>No events on this page.</p>}
              <div className="actions">
                <button
                  disabled={!offset}
                  onClick={() => setOffset(Math.max(0, offset - 50))}
                >
                  Previous
                </button>
                <button
                  disabled={history.length < 50}
                  onClick={() => setOffset(offset + 50)}
                >
                  Next
                </button>
                {can("audit.export") && (
                  <button
                    disabled={busy}
                    className="secondary"
                    onClick={() =>
                      void act(async () => {
                        const rows = await api<Audit[]>(
                          `${base}/audit/export?offset=${offset}`,
                        );
                        const url = URL.createObjectURL(
                          new Blob([JSON.stringify(rows, null, 2)], {
                            type: "application/json",
                          }),
                        );
                        const a = document.createElement("a");
                        a.href = url;
                        a.download = `tech4learn-history-${org.slug}-${offset}.json`;
                        a.click();
                        setTimeout(() => URL.revokeObjectURL(url), 1000);
                      }, "History page exported.")
                    }
                  >
                    Export this page
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
