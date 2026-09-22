export const omrMigration = `
CREATE TABLE exam_omr_scans (
 id uuid PRIMARY KEY, organisation_id uuid NOT NULL REFERENCES organisations(id) ON DELETE CASCADE,
 exam_id integer NOT NULL, learner_id uuid NOT NULL,
 content bytea NOT NULL CHECK(octet_length(content)<=10485760), content_type text NOT NULL CHECK(content_type IN ('image/jpeg','image/png','application/pdf')),
 content_hash text NOT NULL, answers jsonb NOT NULL DEFAULT '{}'::jsonb, status text NOT NULL DEFAULT 'uploaded' CHECK(status IN ('uploaded','reviewed')),
 revision integer NOT NULL DEFAULT 1, uploaded_by uuid NOT NULL REFERENCES users(id), reviewed_by uuid REFERENCES users(id), created_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz NOT NULL DEFAULT now(),
 FOREIGN KEY(organisation_id,learner_id) REFERENCES learners(organisation_id,id) ON DELETE RESTRICT
);
CREATE INDEX exam_omr_scans_exam_owner ON exam_omr_scans(organisation_id,exam_id,created_at DESC);
INSERT INTO schema_versions(version) VALUES(16);
`;
