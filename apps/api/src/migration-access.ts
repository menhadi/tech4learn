import { templates } from "./access-model.js";
// Template values are source-controlled constants, never user input.
const values = templates
  .map(
    (t) =>
      `('${t.name}', ARRAY[${t.permissions.map((p) => `'${p}'`).join(",")}]::text[], ${t.protected})`,
  )
  .join(",");
export const accessMigration = `
CREATE TABLE access_roles (
 id uuid PRIMARY KEY, organisation_id uuid NOT NULL REFERENCES organisations(id), name text NOT NULL,
 permissions text[] NOT NULL, protected boolean NOT NULL DEFAULT false, UNIQUE(organisation_id,id)
);
CREATE UNIQUE INDEX access_role_name ON access_roles(organisation_id,lower(name));
CREATE UNIQUE INDEX access_one_owner_role ON access_roles(organisation_id) WHERE protected;
INSERT INTO access_roles(id,organisation_id,name,permissions,protected)
 SELECT gen_random_uuid(),o.id,t.name,t.permissions,t.protected FROM organisations o CROSS JOIN (VALUES ${values}) AS t(name,permissions,protected);
ALTER TABLE memberships DROP CONSTRAINT memberships_role_check;
ALTER TABLE memberships ADD COLUMN role_id uuid;
ALTER TABLE memberships ADD COLUMN status text NOT NULL DEFAULT 'active' CHECK(status IN ('active','suspended'));
ALTER TABLE memberships ADD COLUMN scope_type text NOT NULL DEFAULT 'organisation' CHECK(scope_type IN ('organisation','centres','groups'));
ALTER TABLE memberships ADD COLUMN scope_ids uuid[] NOT NULL DEFAULT '{}';
UPDATE memberships m SET role_id=r.id FROM access_roles r WHERE r.organisation_id=m.organisation_id AND r.protected;
ALTER TABLE memberships ALTER COLUMN role_id SET NOT NULL;
ALTER TABLE memberships ADD FOREIGN KEY (organisation_id,role_id) REFERENCES access_roles(organisation_id,id);
ALTER TABLE invitations ADD COLUMN role_id uuid;
ALTER TABLE invitations ADD COLUMN scope_type text NOT NULL DEFAULT 'organisation' CHECK(scope_type IN ('organisation','centres','groups'));
ALTER TABLE invitations ADD COLUMN scope_ids uuid[] NOT NULL DEFAULT '{}';
UPDATE invitations i SET role_id=r.id FROM access_roles r WHERE r.organisation_id=i.organisation_id AND r.protected;
ALTER TABLE invitations ALTER COLUMN role_id SET NOT NULL;
ALTER TABLE invitations ADD FOREIGN KEY (organisation_id,role_id) REFERENCES access_roles(organisation_id,id);
CREATE TABLE centres (
 id uuid PRIMARY KEY, organisation_id uuid NOT NULL REFERENCES organisations(id), name text NOT NULL, address text NOT NULL DEFAULT '',
 latitude double precision CHECK(latitude BETWEEN -90 AND 90), longitude double precision CHECK(longitude BETWEEN -180 AND 180),
 radius integer NOT NULL DEFAULT 100 CHECK(radius BETWEEN 10 AND 10000), location_approved boolean NOT NULL DEFAULT false,
 archived boolean NOT NULL DEFAULT false, UNIQUE(organisation_id,id), CHECK((latitude IS NULL) = (longitude IS NULL)),
 CHECK(NOT location_approved OR latitude IS NOT NULL)
);
CREATE TABLE learning_groups (
 id uuid PRIMARY KEY, organisation_id uuid NOT NULL REFERENCES organisations(id), centre_id uuid NOT NULL,
 name text NOT NULL, archived boolean NOT NULL DEFAULT false, UNIQUE(organisation_id,id),
 FOREIGN KEY(organisation_id,centre_id) REFERENCES centres(organisation_id,id)
);
CREATE INDEX groups_centre ON learning_groups(organisation_id,centre_id);
ALTER TABLE audit_events ADD COLUMN details jsonb NOT NULL DEFAULT '{}';
CREATE INDEX audit_org_date ON audit_events(organisation_id,created_at DESC,id);
INSERT INTO schema_versions(version) VALUES (2);
`;
