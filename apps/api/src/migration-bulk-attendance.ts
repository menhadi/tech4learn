export const bulkAttendanceMigration = `
CREATE TABLE attendance_extra_photos (
 id uuid PRIMARY KEY, organisation_id uuid NOT NULL, session_id uuid NOT NULL,
 actor_id uuid NOT NULL REFERENCES users(id), created_at timestamptz NOT NULL DEFAULT now(),
 received_at timestamptz, evidence jsonb, content bytea CHECK(octet_length(content)<=262144), payload_hash text,
 FOREIGN KEY(organisation_id,session_id) REFERENCES attendance_sessions(organisation_id,id) ON DELETE CASCADE
);
CREATE INDEX attendance_extra_session ON attendance_extra_photos(organisation_id,session_id);
CREATE TABLE attendance_face_jobs (
 id uuid PRIMARY KEY, organisation_id uuid NOT NULL, session_id uuid NOT NULL,
 actor_id uuid NOT NULL REFERENCES users(id), created_at timestamptz NOT NULL DEFAULT now(),
 status text NOT NULL CHECK(status IN ('queued','processing','completed','failed')),
 completed integer NOT NULL DEFAULT 0, total integer NOT NULL DEFAULT 0,
 signature text NOT NULL, result jsonb, error text,
 FOREIGN KEY(organisation_id,session_id) REFERENCES attendance_sessions(organisation_id,id) ON DELETE CASCADE
);
CREATE INDEX attendance_face_created ON attendance_face_jobs(created_at);
CREATE UNIQUE INDEX attendance_face_active ON attendance_face_jobs(organisation_id,session_id) WHERE status IN ('queued','processing');
CREATE TABLE attendance_face_worker (id integer PRIMARY KEY CHECK(id=1), job_id uuid, lease_until timestamptz);
INSERT INTO attendance_face_worker(id) VALUES(1);
INSERT INTO schema_versions(version) VALUES(9);
`;
