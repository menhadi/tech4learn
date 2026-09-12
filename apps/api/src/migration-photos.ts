export const photoMigration = `
CREATE TABLE learner_photo_consent (
 organisation_id uuid NOT NULL, learner_id uuid NOT NULL, purpose text NOT NULL CHECK(purpose IN ('profile','reference')),
 granted boolean NOT NULL, version integer NOT NULL DEFAULT 1, actor_id uuid NOT NULL REFERENCES users(id), recorded_at timestamptz NOT NULL DEFAULT now(),
 PRIMARY KEY(organisation_id,learner_id,purpose),
 FOREIGN KEY(organisation_id,learner_id) REFERENCES learners(organisation_id,id) ON DELETE CASCADE
);
CREATE TABLE learner_photos (
 id uuid PRIMARY KEY, organisation_id uuid NOT NULL, learner_id uuid NOT NULL,
 purpose text NOT NULL CHECK(purpose IN ('profile','reference')), content bytea NOT NULL CHECK(octet_length(content)<=262144),
 content_hash text NOT NULL, width integer NOT NULL, height integer NOT NULL,
 checked boolean NOT NULL DEFAULT false, check_engine text, created_at timestamptz NOT NULL DEFAULT now(), actor_id uuid NOT NULL REFERENCES users(id),
 FOREIGN KEY(organisation_id,learner_id) REFERENCES learners(organisation_id,id) ON DELETE CASCADE,
 UNIQUE(organisation_id,learner_id,purpose,content_hash)
);
CREATE UNIQUE INDEX one_profile_photo ON learner_photos(organisation_id,learner_id) WHERE purpose='profile';
CREATE INDEX learner_photos_owner ON learner_photos(organisation_id,learner_id);
UPDATE access_roles SET permissions=ARRAY(SELECT DISTINCT p FROM unnest(permissions || ARRAY['learners.photos','learners.photo_manage','attendance.match']::text[]) p) WHERE protected;
INSERT INTO schema_versions(version) VALUES(8);
`;
