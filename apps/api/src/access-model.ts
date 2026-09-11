export const permissionCatalogue = [
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
export const widePermissions: Permission[] = [
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
