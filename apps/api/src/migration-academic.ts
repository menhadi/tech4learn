export const academicMigration = `
ALTER TABLE centres ADD COLUMN centre_type text NOT NULL DEFAULT 'learning_centre'
 CHECK(centre_type IN ('school','college','coaching','community_centre','learning_centre','training_centre','other'));
CREATE TABLE academic_years (
 id uuid PRIMARY KEY, organisation_id uuid NOT NULL REFERENCES organisations(id), name text NOT NULL,
 starts_on date NOT NULL, ends_on date NOT NULL, archived boolean NOT NULL DEFAULT false,
 UNIQUE(organisation_id,id), CHECK(ends_on>=starts_on)
);
CREATE UNIQUE INDEX academic_year_name ON academic_years(organisation_id,lower(name));
CREATE TABLE learning_classes (
 id uuid PRIMARY KEY, organisation_id uuid NOT NULL, centre_id uuid NOT NULL, academic_year_id uuid NOT NULL,
 name text NOT NULL, archived boolean NOT NULL DEFAULT false,
 UNIQUE(organisation_id,id,centre_id),
 FOREIGN KEY(organisation_id,centre_id) REFERENCES centres(organisation_id,id),
 FOREIGN KEY(organisation_id,academic_year_id) REFERENCES academic_years(organisation_id,id)
);
CREATE UNIQUE INDEX learning_class_name ON learning_classes(organisation_id,centre_id,academic_year_id,lower(name));
ALTER TABLE learning_groups ADD COLUMN class_id uuid;
ALTER TABLE learning_groups ADD FOREIGN KEY(organisation_id,class_id,centre_id) REFERENCES learning_classes(organisation_id,id,centre_id);
CREATE UNIQUE INDEX learning_section_name ON learning_groups(organisation_id,class_id,lower(name)) WHERE class_id IS NOT NULL;
CREATE INDEX sections_class ON learning_groups(organisation_id,class_id);
INSERT INTO schema_versions(version) VALUES(7);
`;
