// Read-only dependency inventory, not a deletion script. Never outputs record values.
import pg from 'pg';
import { pathToFileURL } from 'node:url';

export async function inventoryLegacyOrganisations(sql) {
  const result = await sql.query(`WITH RECURSIVE dependent(oid) AS (
    SELECT 'public.organisations'::regclass::oid
    UNION
    SELECT c.conrelid FROM pg_constraint c JOIN dependent d ON c.confrelid=d.oid
    WHERE c.contype='f'
  ) SELECT n.nspname AS schema,c.relname AS name
    FROM dependent d JOIN pg_class c ON c.oid=d.oid JOIN pg_namespace n ON n.oid=c.relnamespace
    ORDER BY n.nspname,c.relname`);
  const tables = [];
  const quote = value => '"'+value.replaceAll('"','""')+'"';
  for (const table of result.rows) {
    if (table.schema !== 'public' || ['users','schema_versions'].includes(table.name))
      throw new Error('Unexpected dependency requires review');
    const count = await sql.query(`SELECT count(*)::text AS count FROM ${quote(table.schema)}.${quote(table.name)}`);
    tables.push({ table: table.name, totalRows: count.rows[0].count });
  }
  const administrator = await sql.query('SELECT count(*)::text AS count FROM users WHERE is_superadmin');
  const mediaColumns = await sql.query(`SELECT table_name,column_name FROM information_schema.columns
    WHERE table_schema='public' AND data_type='bytea' ORDER BY table_name,column_name`);
  return { canonicalSuperadmins: administrator.rows[0].count, organisationDependentTables: tables,
    databaseMediaColumns: mediaColumns.rows,
    limitation: 'Whole-table counts only. Shared rows, users, sessions, global connector records and private media require separate review. No records were deleted.' };
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  let client;
  try {
    if (!process.env.DATABASE_URL || process.argv.length !== 2) throw new Error('Private API configuration required');
    client = new pg.Client({ connectionString: process.env.DATABASE_URL, connectionTimeoutMillis: 5000 });
    await client.connect();
    await client.query('BEGIN TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
    await client.query("SET LOCAL statement_timeout='10s'");
    console.log(JSON.stringify(await inventoryLegacyOrganisations(client),null,2));
    await client.query('ROLLBACK');
  } catch {
    console.error('Legacy inventory blocked; verify private database configuration and dependencies.');
    process.exitCode = 1;
  } finally { if (client) await client.end().catch(()=>{}); }
}
