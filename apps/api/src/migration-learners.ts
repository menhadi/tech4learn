export const learnerMigration = `
CREATE TABLE learner_fields (
 id uuid PRIMARY KEY, organisation_id uuid NOT NULL REFERENCES organisations(id), key text NOT NULL,
 label text NOT NULL, kind text NOT NULL CHECK(kind IN ('text','number','date','choice','boolean')),
 required boolean NOT NULL DEFAULT false, options jsonb NOT NULL DEFAULT '[]', archived boolean NOT NULL DEFAULT false,
 UNIQUE(organisation_id,key), UNIQUE(organisation_id,id)
);
CREATE TABLE learners (
 id uuid PRIMARY KEY, organisation_id uuid NOT NULL REFERENCES organisations(id), code text NOT NULL,
 name text NOT NULL, age integer CHECK(age BETWEEN 0 AND 120), class_label text NOT NULL DEFAULT '',
 guardian_name text NOT NULL DEFAULT '', guardian_phone text NOT NULL DEFAULT '',
 custom_values jsonb NOT NULL DEFAULT '{}', group_id uuid NOT NULL,
 archived boolean NOT NULL DEFAULT false, demo boolean NOT NULL DEFAULT false,
 version integer NOT NULL DEFAULT 1, created_at timestamptz NOT NULL DEFAULT now(),
 UNIQUE(organisation_id,id), UNIQUE(organisation_id,code),
 FOREIGN KEY(organisation_id,group_id) REFERENCES learning_groups(organisation_id,id)
);
CREATE INDEX learners_scope ON learners(organisation_id,group_id);
CREATE TABLE learner_enrolments (
 id uuid PRIMARY KEY, organisation_id uuid NOT NULL, learner_id uuid NOT NULL, group_id uuid NOT NULL,
 started_at timestamptz NOT NULL DEFAULT now(), ended_at timestamptz, actor_id uuid REFERENCES users(id),
 reason text NOT NULL DEFAULT '',
 FOREIGN KEY(organisation_id,learner_id) REFERENCES learners(organisation_id,id),
 FOREIGN KEY(organisation_id,group_id) REFERENCES learning_groups(organisation_id,id)
);
CREATE UNIQUE INDEX learner_current_enrolment ON learner_enrolments(learner_id) WHERE ended_at IS NULL;
CREATE TABLE learner_imports (
 id uuid PRIMARY KEY, organisation_id uuid NOT NULL REFERENCES organisations(id), actor_id uuid NOT NULL REFERENCES users(id),
 rows jsonb NOT NULL, expires_at timestamptz NOT NULL DEFAULT now()+interval '15 minutes', result jsonb
);
UPDATE access_roles SET permissions=ARRAY(SELECT DISTINCT p FROM unnest(permissions || ARRAY['learners.view','learners.create','learners.edit','learners.transfer','learners.archive','learners.import','learners.contacts','fields.view','fields.manage']::text[]) p) WHERE protected;
INSERT INTO schema_versions(version) VALUES (3);
`;
