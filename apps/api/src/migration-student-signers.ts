export const studentSignersMigration=`
CREATE TABLE foundation_native_signers (
 key_id text PRIMARY KEY CHECK(key_id ~ '^[a-f0-9]{64}$'),
 public_key text NOT NULL CHECK(length(public_key)<=8192),
 active boolean NOT NULL DEFAULT true,
 created_by uuid NOT NULL REFERENCES users(id),
 created_at timestamptz NOT NULL DEFAULT now()
);
INSERT INTO schema_versions(version) VALUES(22);
`;
