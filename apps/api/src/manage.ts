import { faceControlMigration } from "./migration-face-control.js";
import { bulkAttendanceMigration } from "./migration-bulk-attendance.js";
import { Database } from "./database.js";
import { photoMigration } from "./migration-photos.js";
import { seedAcademicDemo } from "./demo-academic.js";
import { attendanceMigration } from "./migration-attendance.js";
import { visionMigration } from "./migration-vision.js";
import { academicMigration } from "./migration-academic.js";
import { migration } from "./schema.js";
import { accessMigration } from "./migration-access.js";
import { configurationMigration } from "./migration-configuration.js";
import { learnerMigration } from "./migration-learners.js";
import { randomUUID } from "node:crypto";
import { emailValue, field, hashPassword, passwordValue } from "./security.js";
import { createInterface } from "node:readline/promises";
import { stdin, stdout } from "node:process";
import {
  createDemo,
  inspectDemo,
  removeDemo,
  seedDemoLearners,
} from "./demo.js";

async function secretPrompt(): Promise<string> {
  if (!stdin.isTTY)
    throw new Error(
      "Run bootstrap in an interactive terminal. Passwords are never accepted as command arguments.",
    );
  stdout.write("Password (15–128 characters; hidden): ");
  stdin.setRawMode(true);
  stdin.resume();
  return new Promise((resolve, reject) => {
    let value = "";
    function done() {
      stdin.setRawMode(false);
      stdin.pause();
      stdin.off("data", input);
      stdout.write("\n");
    }
    function input(chunk: Buffer) {
      for (const char of chunk.toString()) {
        if (char === "\u0003") {
          done();
          reject(new Error("Cancelled"));
          return;
        }
        if (char === "\r" || char === "\n") {
          done();
          resolve(value);
          return;
        }
        if (char === "\u007f" || char === "\b") value = value.slice(0, -1);
        else if (char >= " ") value += char;
      }
    }
    stdin.on("data", input);
  });
}

const db = new Database();
try {
  if (!process.env.DATABASE_URL)
    throw new Error("Set DATABASE_URL in the server environment file first.");
  const command = process.argv[2];
  if (command === "migrate") {
    await db.transaction(async (sql) => {
      await sql.query("SELECT pg_advisory_xact_lock(74041001)");
      await sql.query(
        "CREATE TABLE IF NOT EXISTS schema_versions (version integer PRIMARY KEY, applied_at timestamptz NOT NULL DEFAULT now())",
      );
      const { rows } = await sql.query(
        "SELECT version FROM schema_versions WHERE version=1",
      );
      if (!rows.length) await sql.query(migration);
      if (
        !(
          await sql.query("SELECT version FROM schema_versions WHERE version=2")
        ).rows.length
      )
        await sql.query(accessMigration);
      if (
        !(
          await sql.query("SELECT version FROM schema_versions WHERE version=3")
        ).rows.length
      )
        await sql.query(learnerMigration);
      if (
        !(
          await sql.query("SELECT version FROM schema_versions WHERE version=4")
        ).rows.length
      )
        await sql.query(configurationMigration);
      if (
        !(
          await sql.query("SELECT version FROM schema_versions WHERE version=5")
        ).rows.length
      )
        await sql.query(attendanceMigration);
      if (
        !(
          await sql.query("SELECT version FROM schema_versions WHERE version=6")
        ).rows.length
      )
        await sql.query(visionMigration);
      if (
        !(
          await sql.query("SELECT version FROM schema_versions WHERE version=7")
        ).rows.length
      )
        await sql.query(academicMigration);
      if (
        !(
          await sql.query("SELECT version FROM schema_versions WHERE version=8")
        ).rows.length
      )
        await sql.query(photoMigration);
      if (
        !(
          await sql.query("SELECT version FROM schema_versions WHERE version=9")
        ).rows.length
      )
        await sql.query(bulkAttendanceMigration);
      if (
        !(
          await sql.query(
            "SELECT version FROM schema_versions WHERE version=10",
          )
        ).rows.length
      )
        await sql.query(faceControlMigration);
    });
    console.log("Database migrations through version 10 are applied.");
  } else if (command === "demo-academic") {
    const confirmation = process.argv[4] || "";
    if (!confirmation.startsWith("--confirm-name="))
      throw new Error(
        "Use demo-academic ORG_ADDRESS --confirm-name=exact display name.",
      );
    console.log(
      JSON.stringify(
        await seedAcademicDemo(
          db,
          process.argv[3] || "",
          confirmation.slice(15),
        ),
        null,
        2,
      ),
    );
  } else if (command === "demo-create") {
    const result = await createDemo(db);
    const origin = process.env.ADMIN_ORIGIN || "http://localhost:5173";
    console.log(
      JSON.stringify(
        {
          ...result,
          invitations: result.invitations.map((i) => ({
            email: i.email,
            state: i.state,
            url: `${origin}/#invite=${i.token}`,
          })),
        },
        null,
        2,
      ),
    );
  } else if (command === "demo-learners") {
    console.log(
      JSON.stringify(
        await seedDemoLearners(db, process.argv[3] || ""),
        null,
        2,
      ),
    );
  } else if (command === "demo-inspect") {
    console.log(
      JSON.stringify(await inspectDemo(db, process.argv[3] || ""), null, 2),
    );
  } else if (command === "demo-remove") {
    const id = process.argv[3];
    if (!id || process.argv[4] !== `--confirm=${id}`)
      throw new Error(
        "First run demo-inspect DATASET_ID. To permanently remove that demo dataset, run demo-remove DATASET_ID --confirm=DATASET_ID.",
      );
    console.log(JSON.stringify(await removeDemo(db, id), null, 2));
  } else if (command === "bootstrap") {
    const rl = createInterface({ input: stdin, output: stdout });
    const email = emailValue(await rl.question("Superadmin email: "));
    const name = field(await rl.question("Superadmin name: "), "Name");
    rl.close();
    const password = passwordValue(await secretPrompt());
    const hash = await hashPassword(password);
    await db.transaction(async (sql) => {
      await sql.query("SELECT pg_advisory_xact_lock(74041002)");
      if (
        (
          await sql.query(
            "SELECT id FROM users WHERE is_superadmin=true LIMIT 1",
          )
        ).rows.length
      )
        throw new Error("A superadmin already exists. Bootstrap is closed.");
      const id = randomUUID();
      await sql.query(
        "INSERT INTO users(id,email,name,password_hash,is_superadmin) VALUES ($1,$2,$3,$4,true)",
        [id, email, name, hash],
      );
      await sql.query(
        "INSERT INTO audit_events(id,actor_id,action) VALUES ($1,$2,'superadmin.bootstrapped')",
        [randomUUID(), id],
      );
    });
    console.log(
      "Superadmin created. Sign in through the administration website.",
    );
  } else throw new Error("Use manage.js migrate or manage.js bootstrap.");
} catch (error) {
  console.error(
    error instanceof Error ? error.message : "Management command failed",
  );
  process.exitCode = 1;
} finally {
  await db.onModuleDestroy();
}
