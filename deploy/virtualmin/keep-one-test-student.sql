BEGIN;
SET LOCAL lock_timeout = '10s';
SELECT id FROM organisations WHERE slug=:'org' FOR UPDATE;
CREATE TEMP TABLE keep_student ON COMMIT DROP AS
SELECT l.id,l.organisation_id FROM learners l JOIN organisations o ON o.id=l.organisation_id
WHERE o.slug=:'org' AND l.code=:'code' AND l.name=:'name' AND NOT l.archived;
DO $$ BEGIN
 IF (SELECT count(*) FROM keep_student) <> 1 THEN
  RAISE EXCEPTION 'Stopped: expected exactly one active student matching the organisation, code and name. Nothing deleted.';
 END IF;
 IF NOT EXISTS (SELECT 1 FROM learner_photos p JOIN keep_student k ON k.id=p.learner_id AND k.organisation_id=p.organisation_id WHERE p.purpose='reference' AND p.checked) THEN
  RAISE EXCEPTION 'Stopped: the student to keep has no checked attendance reference photo. Nothing deleted.';
 END IF;
END $$;
CREATE TEMP TABLE remove_students ON COMMIT DROP AS
SELECT l.id,l.organisation_id FROM learners l JOIN keep_student k ON k.organisation_id=l.organisation_id WHERE l.id<>k.id;
SELECT count(*) AS students_to_delete FROM remove_students;
DELETE FROM record_field_values v USING remove_students d WHERE v.organisation_id=d.organisation_id AND v.record_id=d.id AND v.module IN ('learner','learners');
DELETE FROM learner_enrolments e USING remove_students d WHERE e.organisation_id=d.organisation_id AND e.learner_id=d.id;
-- Photo records and consent cascade from the deleted learner. Kept learner is untouched.
DELETE FROM learners l USING remove_students d WHERE l.organisation_id=d.organisation_id AND l.id=d.id;
DO $$ BEGIN
 IF (SELECT count(*) FROM learners l JOIN keep_student k ON l.organisation_id=k.organisation_id) <> 1 THEN
  RAISE EXCEPTION 'Cleanup verification failed; transaction rolled back.';
 END IF;
END $$;
COMMIT;
