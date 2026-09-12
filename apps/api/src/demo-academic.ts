import { randomUUID } from "node:crypto";
import type { Database } from "./database.js";

// Privileged CLI only; never mounted as an HTTP endpoint.
export async function seedAcademicDemo(
  db: Database,
  slug: string,
  confirmedName: string,
) {
  if (!slug || !confirmedName)
    throw new Error(
      "Provide the organisation address and --confirm-name=exact display name.",
    );
  return db.transaction(async (sql) => {
    const org = (
      await sql.query<{ id: string; name: string }>(
        "SELECT id,name FROM organisations WHERE slug=$1 FOR UPDATE",
        [slug],
      )
    ).rows[0];
    if (!org || org.name !== confirmedName)
      throw new Error(
        "Organisation address/name did not match. Nothing added.",
      );
    const previous = (
      await sql.query<{ details: unknown }>(
        "SELECT details FROM audit_events WHERE organisation_id=$1 AND action='demo.academic_seeded'",
        [org.id],
      )
    ).rows[0];
    if (previous)
      return {
        alreadyCreated: true,
        organisation: org.name,
        ...(previous.details as object),
      };
    const yearName = "DEMO Academic Year 2026–27";
    if (
      (
        await sql.query(
          "SELECT id FROM academic_years WHERE organisation_id=$1 AND lower(name)=lower($2)",
          [org.id, yearName],
        )
      ).rows.length
    )
      throw new Error("The reserved demo year already exists. Nothing added.");
    const definitions = (
      await sql.query<{ key: string; kind: string; options: unknown[] }>(
        "SELECT key,kind,options FROM learner_fields WHERE organisation_id=$1 AND required AND NOT archived",
        [org.id],
      )
    ).rows;
    const custom: Record<string, unknown> = {};
    for (const d of definitions) {
      if (d.kind === "choice" && !d.options.length)
        throw new Error(
          "A required choice field has no options. Nothing added.",
        );
      custom[d.key] =
        d.kind === "number"
          ? 0
          : d.kind === "boolean"
            ? false
            : d.kind === "date"
              ? "2018-01-01"
              : d.kind === "choice"
                ? d.options[0]
                : "DEMO value";
    }
    const yearId = randomUUID();
    const centres: string[] = [],
      classes: string[] = [],
      sections: string[] = [],
      learners: string[] = [];
    await sql.query(
      "INSERT INTO academic_years(id,organisation_id,name,starts_on,ends_on) VALUES($1,$2,$3,'2026-04-01','2027-03-31')",
      [yearId, org.id, yearName],
    );
    for (let c = 0; c < 2; c++) {
      const centreId = randomUUID(),
        classId = randomUUID(),
        className = c === 0 ? "DEMO Class 3" : "DEMO Foundation";
      centres.push(centreId);
      classes.push(classId);
      await sql.query(
        "INSERT INTO centres(id,organisation_id,name,address,centre_type) VALUES($1,$2,$3,$4,$5)",
        [
          centreId,
          org.id,
          c === 0 ? "DEMO School Centre" : "DEMO Coaching Centre",
          "Synthetic test centre — set real coordinates before testing location verification",
          c === 0 ? "school" : "coaching",
        ],
      );
      await sql.query(
        "INSERT INTO learning_classes(id,organisation_id,centre_id,academic_year_id,name) VALUES($1,$2,$3,$4,$5)",
        [classId, org.id, centreId, yearId, className],
      );
      for (let s = 0; s < 2; s++) {
        const groupId = randomUUID();
        sections.push(groupId);
        await sql.query(
          "INSERT INTO learning_groups(id,organisation_id,centre_id,class_id,name) VALUES($1,$2,$3,$4,$5)",
          [groupId, org.id, centreId, classId, s === 0 ? "DEMO A" : "DEMO B"],
        );
        for (let n = 0; n < 2; n++) {
          const id = randomUUID(),
            index = c * 4 + s * 2 + n + 1,
            code = `DEMO-ACADEMIC-${String(index).padStart(3, "0")}`;
          await sql.query(
            "INSERT INTO learners(id,organisation_id,code,name,age,class_label,group_id,custom_values,demo) VALUES($1,$2,$3,$4,$5,$6,$7,$8,true)",
            [
              id,
              org.id,
              code,
              `DEMO — Student ${index}`,
              8 + n,
              className,
              groupId,
              JSON.stringify(custom),
            ],
          );
          await sql.query(
            "INSERT INTO learner_enrolments(id,organisation_id,learner_id,group_id,reason) VALUES($1,$2,$3,$4,'Synthetic academic test enrolment')",
            [randomUUID(), org.id, id, groupId],
          );
          learners.push(id);
        }
      }
    }
    const manifest = {
      yearId,
      centres,
      classes,
      sections,
      learners,
      synthetic: true,
    };
    await sql.query(
      "INSERT INTO audit_events(id,organisation_id,action,details) VALUES($1,$2,'demo.academic_seeded',$3)",
      [randomUUID(), org.id, JSON.stringify(manifest)],
    );
    return { alreadyCreated: false, organisation: org.name, ...manifest };
  });
}
