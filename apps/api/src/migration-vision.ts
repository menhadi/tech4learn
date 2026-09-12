export const visionMigration = `
CREATE TABLE attendance_ai_runs (
 id uuid PRIMARY KEY, organisation_id uuid NOT NULL, session_id uuid NOT NULL,
 actor_id uuid REFERENCES users(id), provider text NOT NULL, mode text NOT NULL,
 model text NOT NULL, status text NOT NULL CHECK(status IN ('processing','completed','failed')),
 created_at timestamptz NOT NULL DEFAULT now(), result jsonb,
 FOREIGN KEY(organisation_id,session_id) REFERENCES attendance_sessions(organisation_id,id) ON DELETE CASCADE
);
CREATE INDEX attendance_ai_budget ON attendance_ai_runs(organisation_id,created_at);
UPDATE access_roles SET permissions=ARRAY(SELECT DISTINCT p FROM unnest(permissions||ARRAY['attendance.analyse']::text[]) p) WHERE protected;
INSERT INTO schema_versions(version) VALUES(6);
`;
