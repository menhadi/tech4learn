export const foundationProvisioningMigration = `
ALTER TABLE foundation_organisations ADD COLUMN origin text NOT NULL DEFAULT 'reviewed'
 CHECK(origin IN ('reviewed','native_companion'));
INSERT INTO schema_versions(version) VALUES(20);
`;
