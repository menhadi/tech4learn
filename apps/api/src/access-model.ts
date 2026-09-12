export const permissionCatalogue = [
  ["attendance.analyse", "Run AI photo or register analysis"],
  ["attendance.view", "View attendance and learner roster"],
  ["attendance.capture", "Capture attendance evidence"],
  ["attendance.review", "Confirm and correct attendance"],
  ["attendance.photos", "View private attendance photos"],
  ["attendance.policy", "Manage attendance policy"],
  ["configuration.view", "View organisation setup"],
  ["configuration.manage", "Manage branding and domains"],
  ["learners.view", "View learners"],
  ["learners.create", "Add learners"],
  ["learners.edit", "Edit learners"],
  ["learners.transfer", "Transfer enrolment"],
  ["learners.archive", "Archive learners"],
  ["learners.import", "Import learners"],
  ["learners.contacts", "View and edit guardian contacts"],
  ["fields.view", "View custom field definitions"],
  ["fields.manage", "Configure custom fields"],
  ["organisation.view", "View organisation profile"],
  ["organisation.edit", "Edit organisation profile"],
  ["centres.view", "View centres"],
  ["centres.create", "Create centres"],
  ["centres.edit", "Edit centres"],
  ["centres.archive", "Archive centres"],
  ["centres.approve", "Approve centre coordinates"],
  ["groups.view", "View groups"],
  ["groups.create", "Create groups"],
  ["groups.edit", "Edit groups"],
  ["groups.archive", "Archive groups"],
  ["roles.view", "View roles"],
  ["roles.manage", "Create and edit roles"],
  ["members.view", "View staff access"],
  ["members.manage", "Invite staff and change access"],
  ["audit.view", "View access history"],
  ["audit.export", "Export access history"],
] as const;
export type Permission = (typeof permissionCatalogue)[number][0];
export const allPermissions = permissionCatalogue.map(([key]) => key);
export function permissionDependencies(key: Permission): Permission[] {
  const result = new Set<Permission>(["organisation.view"]);
  const view = (key.split(".")[0] + ".view") as Permission;
  if (key !== view && allPermissions.includes(view)) result.add(view);
  if (key.startsWith("learners.") || key.startsWith("attendance."))
    result.add("groups.view");
  if (key === "attendance.analyse") result.add("attendance.photos");
  if (key === "learners.import") result.add("learners.create");
  if (key === "members.manage")
    for (const p of [
      "roles.view",
      "centres.view",
      "groups.view",
    ] as Permission[])
      result.add(p);
  if (key.startsWith("groups.") && key !== "groups.view")
    result.add("centres.view");
  result.delete(key);
  return [...result];
}
export const widePermissions: Permission[] = [
  "attendance.policy",
  "configuration.view",
  "configuration.manage",
  "fields.manage",
  "organisation.edit",
  "roles.view",
  "roles.manage",
  "members.view",
  "members.manage",
  "audit.view",
  "audit.export",
  "centres.create",
];
export const templates: {
  name: string;
  permissions: Permission[];
  protected: boolean;
}[] = [
  { name: "Organisation admin", permissions: allPermissions, protected: true },
  {
    name: "Programme manager",
    permissions: [
      "organisation.view",
      "centres.view",
      "centres.create",
      "centres.edit",
      "centres.archive",
      "centres.approve",
      "groups.view",
      "groups.create",
      "groups.edit",
      "groups.archive",
    ],
    protected: false,
  },
  {
    name: "Centre coordinator",
    permissions: [
      "organisation.view",
      "centres.view",
      "groups.view",
      "groups.create",
      "groups.edit",
      "groups.archive",
    ],
    protected: false,
  },
  {
    name: "Teacher",
    permissions: ["organisation.view", "centres.view", "groups.view"],
    protected: false,
  },
  {
    name: "Assessor",
    permissions: ["organisation.view", "centres.view", "groups.view"],
    protected: false,
  },
  {
    name: "Viewer",
    permissions: ["organisation.view", "centres.view", "groups.view"],
    protected: false,
  },
];
export interface AccessRole {
  id: string;
  organisation_id: string;
  name: string;
  permissions: Permission[];
  protected: boolean;
}
export interface Scope {
  scope_type: "organisation" | "centres" | "groups";
  scope_ids: string[];
}
export interface Grant extends Scope {
  role_id: string;
  status: "active" | "suspended";
}
export interface Access extends Scope {
  roleId: string | null;
  roleName: string;
  permissions: Permission[];
  owner: boolean;
}
