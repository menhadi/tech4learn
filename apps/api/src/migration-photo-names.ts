export const photoNamesMigration = `
ALTER TABLE learner_photos ADD COLUMN name text NOT NULL DEFAULT 'Student portrait' CHECK(length(name) BETWEEN 1 AND 80);
INSERT INTO schema_versions(version) VALUES(11);
`;
