import { randomUUID } from "node:crypto";
import type { Database } from "./database.js";
import { uuid } from "./security.js";

// Trusted server CLI only. Native IDs must be independently reviewed before use.
export async function provisionPlatformAdministrator(db: Database, accountId: string, nativeOrganisationId: string, nativeUserId: string) {
  const account = uuid(accountId);
  for (const id of [nativeOrganisationId, nativeUserId]) {
    if (!/^[1-9][0-9]{0,14}$/.test(id)) throw new Error("Provide reviewed native numeric IDs.");
  }
  return db.transaction(async sql => {
    await sql.query("SELECT pg_advisory_xact_lock(74041019)");
    const schema = await sql.query("SELECT version FROM schema_versions WHERE version=19");
    if (!schema.rows.length) throw new Error("Apply checked migration 19 explicitly first.");
    const user = await sql.query<{ is_superadmin: boolean }>("SELECT is_superadmin FROM users WHERE id=$1 FOR SHARE", [account]);
    if (!user.rows[0]?.is_superadmin) throw new Error("The selected account must already be a stored superadmin.");
    const realm = await sql.query<{ kind: string }>("SELECT kind FROM foundation_realms WHERE native_id=$1", [nativeOrganisationId]);
    if (realm.rows.length && realm.rows[0].kind !== "platform") throw new Error("An attendance tenant cannot become a platform realm.");
    const created = await sql.query("INSERT INTO foundation_platform_staff(native_organisation_id,native_user_id,user_id) VALUES($1,$2,$3) ON CONFLICT DO NOTHING RETURNING version", [nativeOrganisationId, nativeUserId, account]);
    if (!created.rows.length) {
      const existing = await sql.query<{ user_id: string; active: boolean }>("SELECT user_id,active FROM foundation_platform_staff WHERE native_organisation_id=$1 AND native_user_id=$2 FOR UPDATE", [nativeOrganisationId, nativeUserId]);
      if (existing.rows[0]?.user_id !== account || !existing.rows[0].active) throw new Error("An existing identity conflicts or is revoked; no changes made.");
      return { created: false };
    }
    await sql.query("INSERT INTO audit_events(id,actor_id,organisation_id,action,details) VALUES($1,$2,NULL,'foundation.platform_staff_linked',$3)", [randomUUID(), account, JSON.stringify({ nativeOrganisationId, nativeUserId, userId: account, source: "server_cli" })]);
    return { created: true };
  });
}
