export const examWorkspaceMigration = `
CREATE TABLE examelite_workspaces (
 organisation_id uuid PRIMARY KEY REFERENCES organisations(id),
 restrictions text[] NOT NULL DEFAULT '{}',
 revision integer NOT NULL DEFAULT 0,
 updated_at timestamptz NOT NULL DEFAULT now()
);
UPDATE access_roles SET permissions = array_append(permissions,'exams.manage')
 WHERE protected AND NOT ('exams.manage'=ANY(permissions));
UPDATE access_roles SET permissions = array_append(permissions,'exams.view')
 WHERE protected AND NOT ('exams.view'=ANY(permissions));
INSERT INTO schema_versions(version) VALUES(14);
`;
