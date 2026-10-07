// Separate platform identities never stand in for attendance organisations.
export const foundationPlatformMigration = `
CREATE TABLE foundation_realms (
 native_id bigint PRIMARY KEY CHECK(native_id>0 AND native_id<1000000000000000),
 kind text NOT NULL CHECK(kind IN ('organisation','platform'))
);
INSERT INTO foundation_realms(native_id,kind) SELECT native_id,'organisation' FROM foundation_organisations;
CREATE FUNCTION foundation_keep_realm_kind() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF NEW.kind<>OLD.kind THEN RAISE EXCEPTION 'Native realm kind is immutable' USING ERRCODE='23514'; END IF;
 RETURN NEW;
END;
$$;
CREATE TRIGGER foundation_realm_kind_immutable BEFORE UPDATE OF kind ON foundation_realms
 FOR EACH ROW EXECUTE FUNCTION foundation_keep_realm_kind();
ALTER TABLE foundation_organisations ADD CONSTRAINT foundation_organisation_realm FOREIGN KEY(native_id) REFERENCES foundation_realms(native_id);
CREATE TABLE foundation_platform_staff (
 native_organisation_id bigint NOT NULL REFERENCES foundation_realms(native_id),
 native_user_id bigint NOT NULL CHECK(native_user_id>0 AND native_user_id<1000000000000000),
 user_id uuid NOT NULL REFERENCES users(id),
 active boolean NOT NULL DEFAULT true,
 version integer NOT NULL DEFAULT 1 CHECK(version>0),
 PRIMARY KEY(native_organisation_id,native_user_id),
 UNIQUE(native_organisation_id,user_id)
);
CREATE FUNCTION foundation_register_realm() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE native bigint; registered text;
BEGIN
 IF TG_TABLE_NAME='foundation_organisations' THEN native:=NEW.native_id;
 ELSE native:=NEW.native_organisation_id; END IF;
 INSERT INTO foundation_realms(native_id,kind) VALUES(native,TG_ARGV[0])
 ON CONFLICT(native_id) DO UPDATE SET native_id=EXCLUDED.native_id
 RETURNING kind INTO registered;
 IF registered<>TG_ARGV[0] THEN RAISE EXCEPTION 'Native realm cannot change kind' USING ERRCODE='23514'; END IF;
 RETURN NEW;
END;
$$;
CREATE TRIGGER foundation_organisation_realm_registration BEFORE INSERT OR UPDATE OF native_id ON foundation_organisations
 FOR EACH ROW EXECUTE FUNCTION foundation_register_realm('organisation');
CREATE TRIGGER foundation_platform_realm_registration BEFORE INSERT OR UPDATE OF native_organisation_id ON foundation_platform_staff
 FOR EACH ROW EXECUTE FUNCTION foundation_register_realm('platform');
INSERT INTO schema_versions(version) VALUES(19);
`;
