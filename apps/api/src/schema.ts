// Explicit migration only: the web process never creates or changes schema.
export const migration = `
CREATE TABLE IF NOT EXISTS schema_versions (version integer PRIMARY KEY, applied_at timestamptz NOT NULL DEFAULT now());
CREATE TABLE users (
  id uuid PRIMARY KEY, email text UNIQUE NOT NULL CHECK (email = lower(email)),
  name text NOT NULL, password_hash text NOT NULL, is_superadmin boolean NOT NULL DEFAULT false,
  created_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE organisations (
  id uuid PRIMARY KEY, name text NOT NULL, slug text UNIQUE NOT NULL,
  colour text NOT NULL DEFAULT '#175d50', centre_label text NOT NULL DEFAULT 'Centre',
  created_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE memberships (
  user_id uuid NOT NULL REFERENCES users(id), organisation_id uuid NOT NULL REFERENCES organisations(id),
  role text NOT NULL CHECK (role = 'organisation_admin'), PRIMARY KEY(user_id, organisation_id)
);
CREATE TABLE sessions (
  token_hash text PRIMARY KEY, user_id uuid NOT NULL REFERENCES users(id),
  expires_at timestamptz NOT NULL, created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX sessions_user ON sessions(user_id);
CREATE TABLE invitations (
  id uuid PRIMARY KEY, organisation_id uuid NOT NULL REFERENCES organisations(id), email text NOT NULL,
  token_hash text UNIQUE NOT NULL, expires_at timestamptz NOT NULL,
  accepted_at timestamptz, created_by uuid NOT NULL REFERENCES users(id), created_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE audit_events (
  id uuid PRIMARY KEY, actor_id uuid REFERENCES users(id), organisation_id uuid REFERENCES organisations(id),
  action text NOT NULL, created_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE auth_limits (key text PRIMARY KEY, count integer NOT NULL, expires_at timestamptz NOT NULL);
CREATE INDEX auth_limits_expiry ON auth_limits(expires_at);
INSERT INTO schema_versions(version) VALUES (1);
`;
