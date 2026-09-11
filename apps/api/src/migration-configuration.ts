export const configurationMigration = `
CREATE TABLE organisation_settings (
 organisation_id uuid PRIMARY KEY REFERENCES organisations(id) ON DELETE CASCADE,
 kind text NOT NULL DEFAULT 'other', template text NOT NULL DEFAULT 'community',
 welcome text NOT NULL DEFAULT '', logo text NOT NULL DEFAULT '',
 enabled_modules jsonb NOT NULL DEFAULT '{"learners":true,"attendance":false,"fln":false,"exams":false}',
 version integer NOT NULL DEFAULT 1
);
CREATE TABLE organisation_domains (
 organisation_id uuid PRIMARY KEY REFERENCES organisations(id) ON DELETE CASCADE,
 hostname text NOT NULL UNIQUE, challenge text NOT NULL,
 verified_at timestamptz, active boolean NOT NULL DEFAULT false
);
ALTER TABLE learner_fields RENAME TO custom_fields;
ALTER TABLE custom_fields DROP CONSTRAINT learner_fields_organisation_id_key_key;
ALTER TABLE custom_fields ADD COLUMN module text NOT NULL DEFAULT 'learners';
ALTER TABLE custom_fields ADD UNIQUE(organisation_id,module,key);
CREATE VIEW learner_fields AS SELECT id,organisation_id,key,label,kind,required,options,archived FROM custom_fields WHERE module='learners' WITH LOCAL CHECK OPTION;
CREATE TABLE record_field_values (
 organisation_id uuid NOT NULL REFERENCES organisations(id) ON DELETE CASCADE,
 module text NOT NULL, record_id uuid NOT NULL, values jsonb NOT NULL DEFAULT '{}',
 version integer NOT NULL DEFAULT 1, PRIMARY KEY(organisation_id,module,record_id)
);
UPDATE access_roles SET permissions=ARRAY(SELECT DISTINCT p FROM unnest(permissions || ARRAY['configuration.view','configuration.manage']::text[]) p) WHERE protected;
INSERT INTO schema_versions(version) VALUES (4);
`;
