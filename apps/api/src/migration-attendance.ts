export const attendanceMigration = `
CREATE TABLE attendance_policy (
 organisation_id uuid PRIMARY KEY REFERENCES organisations(id) ON DELETE CASCADE,
 accuracy_limit integer NOT NULL DEFAULT 50 CHECK(accuracy_limit BETWEEN 5 AND 1000),
 timezone text NOT NULL DEFAULT 'Asia/Kolkata',
 self_review boolean NOT NULL DEFAULT true,
 version integer NOT NULL DEFAULT 1
);
CREATE TABLE attendance_sessions (
 id uuid PRIMARY KEY, organisation_id uuid NOT NULL REFERENCES organisations(id),
 group_id uuid NOT NULL, centre_id uuid NOT NULL,
 actor_id uuid REFERENCES users(id),
 created_at timestamptz NOT NULL DEFAULT now(), received_at timestamptz,
 attendance_date text,
 status text NOT NULL DEFAULT 'draft' CHECK(status IN ('draft','pending','confirmed','rejected')),
 snapshot jsonb NOT NULL, evidence jsonb, payload_hash text,
 marks jsonb NOT NULL DEFAULT '{}', version integer NOT NULL DEFAULT 1,
 UNIQUE(organisation_id,id),
 FOREIGN KEY(organisation_id,group_id) REFERENCES learning_groups(organisation_id,id),
 FOREIGN KEY(organisation_id,centre_id) REFERENCES centres(organisation_id,id)
);
CREATE UNIQUE INDEX attendance_one_daily ON attendance_sessions(organisation_id,group_id,attendance_date) WHERE status IN ('pending','confirmed');
CREATE INDEX attendance_by_date ON attendance_sessions(organisation_id,attendance_date,created_at);
CREATE TABLE attendance_photos (
 organisation_id uuid NOT NULL, session_id uuid PRIMARY KEY,
 content bytea NOT NULL CHECK(octet_length(content)<=262144),
 FOREIGN KEY(organisation_id,session_id) REFERENCES attendance_sessions(organisation_id,id) ON DELETE CASCADE
);
CREATE TABLE attendance_reviews (
 id uuid PRIMARY KEY, organisation_id uuid NOT NULL, session_id uuid NOT NULL,
 actor_id uuid REFERENCES users(id), created_at timestamptz NOT NULL DEFAULT now(),
 decision text NOT NULL, reason text NOT NULL, marks jsonb NOT NULL,
 FOREIGN KEY(organisation_id,session_id) REFERENCES attendance_sessions(organisation_id,id) ON DELETE CASCADE
);
UPDATE access_roles SET permissions=ARRAY(SELECT DISTINCT p FROM unnest(permissions || ARRAY['attendance.view','attendance.capture','attendance.review','attendance.photos','attendance.policy']::text[]) p) WHERE protected;
INSERT INTO schema_versions(version) VALUES (5);
`;
