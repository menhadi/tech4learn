export const attendanceTestingMigration = `
DROP INDEX attendance_one_daily;
CREATE UNIQUE INDEX attendance_one_daily ON attendance_sessions(organisation_id,group_id,attendance_date)
WHERE status IN ('pending','confirmed') AND COALESCE(snapshot->>'test_run','false') <> 'true';
INSERT INTO schema_versions(version) VALUES(12);
`;
