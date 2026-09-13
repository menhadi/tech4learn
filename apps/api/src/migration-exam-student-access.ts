export const examStudentAccessMigration = `
CREATE TABLE exam_student_grants (
 id uuid PRIMARY KEY, organisation_id uuid NOT NULL REFERENCES organisations(id),
 learner_id uuid NOT NULL, external_exam_id bigint NOT NULL CHECK(external_exam_id>0),
 exam_name text NOT NULL, token_hash text NOT NULL UNIQUE,
 expires_at timestamptz NOT NULL, consumed_at timestamptz, revoked_at timestamptz,
 created_by uuid NOT NULL REFERENCES users(id), created_at timestamptz NOT NULL DEFAULT now(),
 FOREIGN KEY(organisation_id,learner_id) REFERENCES learners(organisation_id,id)
);
CREATE INDEX exam_student_grants_learner ON exam_student_grants(organisation_id,learner_id);
CREATE TABLE exam_student_sessions (
 token_hash text PRIMARY KEY, grant_id uuid NOT NULL REFERENCES exam_student_grants(id),
 expires_at timestamptz NOT NULL, created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX exam_student_sessions_grant ON exam_student_sessions(grant_id);
INSERT INTO schema_versions(version) VALUES(15);
`;
