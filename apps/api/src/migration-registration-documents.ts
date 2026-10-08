export const registrationDocumentsMigration = `
CREATE TABLE registration_document_runs (
 id uuid PRIMARY KEY,
 organisation_id uuid NOT NULL REFERENCES organisations(id),
 actor_id uuid NOT NULL REFERENCES users(id),
 group_id uuid NOT NULL,
 provider text NOT NULL CHECK(provider IN ('openai','claude','gemini','deepseek')),
 status text NOT NULL CHECK(status IN ('processing','completed','failed')),
 created_at timestamptz NOT NULL DEFAULT now(),
 FOREIGN KEY(organisation_id,group_id) REFERENCES learning_groups(organisation_id,id)
);
CREATE INDEX registration_document_runs_quota ON registration_document_runs(organisation_id,created_at);
INSERT INTO schema_versions(version) VALUES(23);
`;
