import { Database } from "./database.js";
import { migration } from "./schema.js";
import { randomUUID } from "node:crypto";
import { emailValue, field, hashPassword, passwordValue } from "./security.js";
import { createInterface } from "node:readline/promises";
import { stdin, stdout } from "node:process";

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
    });
    console.log("Database migration 1 is applied.");
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
