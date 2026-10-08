// Reviewed native origin only. No backfill, contact matching or student account creation.
export const studentAdmissionsMigration = `
CREATE TABLE foundation_student_admissions (
 native_organisation_id bigint NOT NULL,
 organisation_id uuid NOT NULL,
 admission_id uuid NOT NULL,
 learner_id uuid NOT NULL,
 native_student_id bigint NOT NULL CHECK(native_student_id>0 AND native_student_id<1000000000000000),
 origin_version bigint NOT NULL CHECK(origin_version>0 AND origin_version<1000000000000000),
 origin_fingerprint char(64) NOT NULL CHECK(origin_fingerprint ~ '^[a-f0-9]{64}$'),
 review_fingerprint char(64) NOT NULL CHECK(review_fingerprint ~ '^[a-f0-9]{64}$'),
 key_id char(64) NOT NULL REFERENCES foundation_native_signers(key_id),
 reviewed_by uuid NOT NULL REFERENCES users(id),
 state text NOT NULL DEFAULT 'awaiting_native' CHECK(state IN ('awaiting_native','linked')),
 created_at timestamptz NOT NULL DEFAULT now(),
 PRIMARY KEY(native_organisation_id,admission_id),
 UNIQUE(native_organisation_id,native_student_id),
 UNIQUE(native_organisation_id,learner_id),
 CHECK(admission_id=learner_id),
 FOREIGN KEY(native_organisation_id,organisation_id) REFERENCES foundation_organisations(native_id,organisation_id),
 FOREIGN KEY(organisation_id,learner_id) REFERENCES learners(organisation_id,id)
);
INSERT INTO schema_versions(version) VALUES(24);
`;
