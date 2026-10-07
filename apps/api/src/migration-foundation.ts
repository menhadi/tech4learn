// Additive, explicit mapping only; existing identities and records are untouched.
export const foundationMigration = `
CREATE TABLE foundation_organisations (
  native_id bigint PRIMARY KEY CHECK(native_id > 0 AND native_id < 1000000000000000),
  organisation_id uuid NOT NULL UNIQUE REFERENCES organisations(id),
  active boolean NOT NULL DEFAULT true,
  version integer NOT NULL DEFAULT 1 CHECK(version > 0)
);
CREATE TABLE foundation_staff (
  native_organisation_id bigint NOT NULL REFERENCES foundation_organisations(native_id),
  native_user_id bigint NOT NULL CHECK(native_user_id > 0 AND native_user_id < 1000000000000000),
  user_id uuid NOT NULL REFERENCES users(id),
  active boolean NOT NULL DEFAULT true,
  version integer NOT NULL DEFAULT 1 CHECK(version > 0),
  PRIMARY KEY(native_organisation_id,native_user_id),
  UNIQUE(native_organisation_id,user_id)
);
INSERT INTO schema_versions(version) VALUES(17);
`;
