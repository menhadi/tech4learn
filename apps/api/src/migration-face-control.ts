export const faceControlMigration = `
ALTER TABLE attendance_face_worker ADD COLUMN paused boolean NOT NULL DEFAULT false;
INSERT INTO schema_versions(version) VALUES(10);
`;
