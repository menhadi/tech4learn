// ID-only delivery intent, committed atomically with the canonical student write.
// No backfill, matching by email/name, student login or remote delivery is performed here.
export const studentDeliveryMigration = `
CREATE TABLE foundation_student_deliveries (
 native_organisation_id bigint NOT NULL,
 organisation_id uuid NOT NULL,
 learner_id uuid NOT NULL,
 revision bigint NOT NULL DEFAULT 1 CHECK(revision>0),
 source_version integer NOT NULL CHECK(source_version>0),
 delivered_revision bigint NOT NULL DEFAULT 0 CHECK(delivered_revision>=0 AND delivered_revision<=revision),
 attempts integer NOT NULL DEFAULT 0 CHECK(attempts>=0),
 updated_at timestamptz NOT NULL DEFAULT now(),
 PRIMARY KEY(native_organisation_id,learner_id),
 FOREIGN KEY(native_organisation_id,organisation_id) REFERENCES foundation_organisations(native_id,organisation_id),
 FOREIGN KEY(organisation_id,learner_id) REFERENCES learners(organisation_id,id)
);
CREATE INDEX foundation_student_delivery_pending ON foundation_student_deliveries(native_organisation_id,updated_at)
 WHERE delivered_revision<revision;

CREATE FUNCTION request_foundation_student_delivery() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 INSERT INTO foundation_student_deliveries(native_organisation_id,organisation_id,learner_id,source_version)
 SELECT native_id,NEW.organisation_id,NEW.id,NEW.version FROM foundation_organisations
 WHERE organisation_id=NEW.organisation_id AND active
 ON CONFLICT(native_organisation_id,learner_id) DO UPDATE SET
 revision=foundation_student_deliveries.revision+1,
 source_version=EXCLUDED.source_version,updated_at=now();
 RETURN NEW;
END;
$$;
CREATE TRIGGER foundation_student_created AFTER INSERT ON learners
 FOR EACH ROW EXECUTE FUNCTION request_foundation_student_delivery();
CREATE TRIGGER foundation_student_changed AFTER UPDATE OF code,name,age,class_label,guardian_name,guardian_phone,custom_values,group_id,archived,demo ON learners
 FOR EACH ROW WHEN (ROW(OLD.code,OLD.name,OLD.age,OLD.class_label,OLD.guardian_name,OLD.guardian_phone,OLD.custom_values,OLD.group_id,OLD.archived,OLD.demo)
 IS DISTINCT FROM ROW(NEW.code,NEW.name,NEW.age,NEW.class_label,NEW.guardian_name,NEW.guardian_phone,NEW.custom_values,NEW.group_id,NEW.archived,NEW.demo))
 EXECUTE FUNCTION request_foundation_student_delivery();
INSERT INTO schema_versions(version) VALUES(21);
`;
