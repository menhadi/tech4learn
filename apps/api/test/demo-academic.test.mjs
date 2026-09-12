import { test } from "node:test";
import assert from "node:assert/strict";
import { randomUUID } from "node:crypto";
import { PGlite } from "@electric-sql/pglite";
import { migration } from "../dist/schema.js";
import { accessMigration } from "../dist/migration-access.js";
import { learnerMigration } from "../dist/migration-learners.js";
import { configurationMigration } from "../dist/migration-configuration.js";
import { academicMigration } from "../dist/migration-academic.js";
import { seedAcademicDemo } from "../dist/demo-academic.js";

test("academic demo targets an existing confirmed organisation and is atomic and repeatable", async () => {
  const pg = new PGlite();
  try {
    for (const sql of [
      migration,
      accessMigration,
      learnerMigration,
      configurationMigration,
      academicMigration,
    ])
      await pg.exec(sql);
    const db = {
      transaction: (run) =>
        pg.transaction((c) => run({ query: (s, p) => c.query(s, p) })),
    };
    const org = randomUUID(),
      other = randomUUID();
    await pg.query(
      "INSERT INTO organisations(id,name,slug) VALUES($1,'Vecotrial Career Academy','vector-academy'),($2,'Other organisation','other')",
      [org, other],
    );
    await assert.rejects(
      seedAcademicDemo(db, "vector-academy", "Wrong name"),
      /did not match/,
    );
    await assert.rejects(
      seedAcademicDemo(db, "missing", "Vecotrial Career Academy"),
      /did not match/,
    );
    // A late learner-code conflict must roll back even the newly inserted centres/year.
    const centre = randomUUID(),
      group = randomUUID();
    await pg.query(
      "INSERT INTO centres(id,organisation_id,name) VALUES($1,$2,'Existing centre')",
      [centre, org],
    );
    await pg.query(
      "INSERT INTO learning_groups(id,organisation_id,centre_id,name) VALUES($1,$2,$3,'Existing group')",
      [group, org, centre],
    );
    await pg.query(
      "INSERT INTO learners(id,organisation_id,code,name,group_id) VALUES($1,$2,'DEMO-ACADEMIC-001','Existing learner',$3)",
      [randomUUID(), org, group],
    );
    await assert.rejects(
      seedAcademicDemo(db, "vector-academy", "Vecotrial Career Academy"),
    );
    assert.equal(
      (await pg.query("SELECT count(*)::int AS n FROM academic_years")).rows[0]
        .n,
      0,
    );
    assert.equal(
      (await pg.query("SELECT count(*)::int AS n FROM centres")).rows[0].n,
      1,
    );
    await pg.query(
      "UPDATE learners SET code='EXISTING-001' WHERE organisation_id=$1",
      [org],
    );
    await pg.query(
      "INSERT INTO learner_fields(id,organisation_id,key,label,kind,required) VALUES($1,$2,'test_note','Test note','text',true)",
      [randomUUID(), org],
    );
    const seeded = await seedAcademicDemo(
      db,
      "vector-academy",
      "Vecotrial Career Academy",
    );
    assert.equal(seeded.centres.length, 2);
    assert.equal(seeded.sections.length, 4);
    assert.equal(seeded.learners.length, 8);
    const repeated = await seedAcademicDemo(
      db,
      "vector-academy",
      "Vecotrial Career Academy",
    );
    assert.equal(repeated.alreadyCreated, true);
    assert.deepEqual(repeated.learners, seeded.learners);
    for (const table of [
      "centres",
      "academic_years",
      "learning_classes",
      "learning_groups",
      "learners",
      "learner_enrolments",
      "audit_events",
    ]) {
      assert.equal(
        (
          await pg.query(
            `SELECT count(*)::int AS n FROM ${table} WHERE organisation_id=$1`,
            [other],
          )
        ).rows[0].n,
        0,
      );
    }
    assert.equal(
      (await pg.query("SELECT count(*)::int AS n FROM organisations")).rows[0]
        .n,
      2,
    );
    const students = (
      await pg.query(
        "SELECT custom_values,guardian_phone FROM learners WHERE organisation_id=$1 AND demo",
        [org],
      )
    ).rows;
    assert.equal(students.length, 8);
    assert.ok(
      students.every(
        (s) =>
          s.custom_values.test_note === "DEMO value" && s.guardian_phone === "",
      ),
    );
    assert.equal(
      (
        await pg.query(
          "SELECT count(*)::int AS n FROM centres WHERE location_approved",
        )
      ).rows[0].n,
      0,
    );
  } finally {
    await pg.close();
  }
});
