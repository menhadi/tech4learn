// Read-only plan. There is deliberately no deletion executor in this module.
import { inventoryLegacyOrganisations } from './inventory-legacy-organisations.mjs';

export function childFirstOrder(tables, edges) {
  const remaining = new Set(tables), order = [];
  if (remaining.size !== tables.length) throw new Error('Duplicate table');
  while (remaining.size) {
    const leaves = [...remaining].filter(parent => !edges.some(([child, referenced]) => referenced === parent && remaining.has(child))).sort();
    if (!leaves.length) throw new Error('Dependency cycle requires explicit review');
    for (const table of leaves) { remaining.delete(table); order.push(table); }
  }
  return order;
}

export async function planLegacyCleanup(sql, selectedOrganisationIds) {
  const inventory = await inventoryLegacyOrganisations(sql);
  if (inventory.canonicalSuperadmins !== '1') throw new Error('Exactly one retained administrator must be reviewed');
  const tables = inventory.organisationDependentTables.map(t => t.table);
  const links = await sql.query(`SELECT child.relname AS child,parent.relname AS parent
    FROM pg_constraint f JOIN pg_class child ON child.oid=f.conrelid JOIN pg_class parent ON parent.oid=f.confrelid
    JOIN pg_namespace cn ON cn.oid=child.relnamespace JOIN pg_namespace pn ON pn.oid=parent.relnamespace
    WHERE f.contype='f' AND cn.nspname='public' AND pn.nspname='public'
    AND child.relname=ANY($1::text[]) AND parent.relname=ANY($1::text[])`,[tables]);
  const order = childFirstOrder(tables, links.rows.map(r=>[r.child,r.parent]));
  const columns = await sql.query("SELECT table_name,column_name FROM information_schema.columns WHERE table_schema='public' AND table_name=ANY($1::text[])",[tables]);
  const organisations = (await sql.query('SELECT id FROM organisations'+(selectedOrganisationIds ? ' WHERE id=ANY($1::uuid[])' : '')+' ORDER BY id',selectedOrganisationIds ? [selectedOrganisationIds] : [])).rows.map(r=>r.id);
  const native = (await sql.query('SELECT native_id::text FROM foundation_organisations WHERE organisation_id=ANY($1::uuid[])',[organisations])).rows.map(r=>r.native_id);
  const plan = [];
  for (const table of order) {
    const quote = '"'+table.replaceAll('"','""')+'"';
    let predicate, values, scope;
    if (table === 'organisations') {
      predicate='id=ANY($1::uuid[])';values=[organisations];scope='reviewed previous organisation IDs';
    } else if (columns.rows.some(c=>c.table_name===table && c.column_name==='organisation_id')) {
      predicate='organisation_id=ANY($1::uuid[])';values=[organisations];scope='previous organisation IDs; global rows excluded';
    } else if (table === 'foundation_staff') {
      predicate='native_organisation_id=ANY($1::bigint[])';values=[native];scope='native IDs mapped to previous organisations';
    } else if (table === 'exam_student_sessions') {
      predicate='grant_id IN (SELECT id FROM public.exam_student_grants WHERE organisation_id=ANY($1::uuid[]))';
      values=[organisations];scope='grants owned by previous organisations';
    } else throw new Error('Unclassified dependency requires explicit scope review: '+table);
    const count = await sql.query(`SELECT count(*)::text AS count FROM public.${quote} WHERE ${predicate}`,values);
    plan.push({table, scopedRows:count.rows[0].count, scope});
  }
  return {organisationCount:organisations.length, plan,
    pending:'Backup/restore verification, writes paused, exact private ID snapshot, sessions/non-admin users, global connector records and final transaction checks remain required. This plan performs no deletion.'};
}
