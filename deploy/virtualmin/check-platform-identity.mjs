// Read-only deployment gate. Output contains no account IDs, credentials or records.
import { pathToFileURL } from 'node:url';
import pg from 'pg';

export async function checkPlatformIdentity(sql, nativeOrganisationId) {
  if (!/^[1-9][0-9]{0,14}$/.test(nativeOrganisationId)) throw new Error('Invalid native realm');
  const checks = [];
  const schema = await sql.query('SELECT EXISTS(SELECT 1 FROM schema_versions WHERE version=19) AS ready');
  checks.push({ ready: schema.rows[0].ready, label: 'platform identity migration 19 applied' });
  if (!schema.rows[0].ready) return checks;
  const realm = await sql.query('SELECT kind FROM foundation_realms WHERE native_id=$1', [nativeOrganisationId]);
  checks.push({ ready: realm.rows.length === 1 && realm.rows[0].kind === 'platform', label: 'primary native realm explicitly registered as platform' });
  const tenant = await sql.query('SELECT EXISTS(SELECT 1 FROM foundation_organisations WHERE native_id=$1) AS linked', [nativeOrganisationId]);
  checks.push({ ready: !tenant.rows[0].linked, label: 'primary platform has no attendance tenant alias' });
  const staff = await sql.query(`SELECT count(*)::integer AS total FROM foundation_platform_staff s
    JOIN users u ON u.id=s.user_id WHERE s.native_organisation_id=$1 AND s.active AND u.is_superadmin`, [nativeOrganisationId]);
  checks.push({ ready: staff.rows[0].total > 0, label: 'active explicit mapping to a current canonical superadmin' });
  return checks;
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  let client;
  try {
    if (!process.env.DATABASE_URL || process.argv.length !== 3) throw new Error('Configuration required');
    client = new pg.Client({ connectionString: process.env.DATABASE_URL, connectionTimeoutMillis: 5000 });
    await client.connect();
    await client.query('BEGIN TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
    await client.query("SET LOCAL statement_timeout='5s'");
    const checks = await checkPlatformIdentity(client, process.argv[2]);
    for (const check of checks) console.log(`${check.ready ? 'PASS' : 'BLOCKED'}: ${check.label}`);
    await client.query('ROLLBACK');
    console.log('PENDING: matching native user flags and authenticated browser acceptance require separate verification.');
    process.exitCode = checks.every(check => check.ready) ? 0 : 1;
  } catch {
    console.error('BLOCKED: read-only platform identity check failed; verify private configuration and schema.');
    process.exitCode = 1;
  } finally {
    if (client) await client.end().catch(() => {});
  }
}
