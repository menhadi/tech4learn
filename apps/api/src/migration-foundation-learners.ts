// Explicit identity links only. No learner or exam records are copied.
export const foundationLearnerMigration = `
ALTER TABLE foundation_organisations ADD CONSTRAINT foundation_organisation_pair UNIQUE(native_id,organisation_id);
CREATE TABLE foundation_learners (
 native_organisation_id bigint NOT NULL,
 native_student_id bigint NOT NULL CHECK(native_student_id>0 AND native_student_id<1000000000000000),
 organisation_id uuid NOT NULL,
 learner_id uuid NOT NULL,
 active boolean NOT NULL DEFAULT true,
 version integer NOT NULL DEFAULT 1 CHECK(version>0),
 PRIMARY KEY(native_organisation_id,native_student_id),
 UNIQUE(native_organisation_id,learner_id),
 FOREIGN KEY(native_organisation_id,organisation_id) REFERENCES foundation_organisations(native_id,organisation_id),
 FOREIGN KEY(organisation_id,learner_id) REFERENCES learners(organisation_id,id)
);
INSERT INTO schema_versions(version) VALUES(18);
`;
