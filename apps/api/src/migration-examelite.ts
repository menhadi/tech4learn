export const examEliteMigration = `
CREATE TABLE examelite_sharing (
 organisation_id uuid PRIMARY KEY REFERENCES organisations(id),
 enabled boolean NOT NULL DEFAULT false,
 exam_ids bigint[] NOT NULL DEFAULT '{}',
 revision integer NOT NULL DEFAULT 1,
 updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE examelite_students (
 organisation_id uuid NOT NULL, learner_id uuid NOT NULL,
 external_organisation_id bigint NOT NULL, external_student_id bigint NOT NULL,
 created_at timestamptz NOT NULL DEFAULT now(),
 PRIMARY KEY(organisation_id,learner_id),
 FOREIGN KEY(organisation_id,learner_id) REFERENCES learners(organisation_id,id),
 UNIQUE(external_organisation_id,external_student_id)
);
INSERT INTO schema_versions(version) VALUES(13);
`;
