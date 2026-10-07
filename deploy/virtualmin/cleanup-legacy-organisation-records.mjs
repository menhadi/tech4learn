// Internal transactional core, intentionally no live CLI. Requires separately verified backup.
import { createHash } from 'node:crypto';
import { planLegacyCleanup } from './plan-legacy-cleanup.mjs';
import { retireLegacyAccounts } from './retire-legacy-accounts.mjs';

const digest = value => createHash('sha256').update(value).digest('hex');
const uuid = value => typeof value==='string' && /^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/.test(value);

export async function captureLegacyCleanupManifest(sql) {
  const roots=await sql.query('SELECT id,password_hash FROM users WHERE is_superadmin');
  if(roots.rows.length!==1)throw new Error('Review the retained administrator');
  const organisationIds=(await sql.query('SELECT id FROM organisations ORDER BY id')).rows.map(r=>r.id);
  const oldUserIds=(await sql.query('SELECT DISTINCT u.id FROM users u JOIN memberships m ON m.user_id=u.id WHERE m.organisation_id=ANY($1::uuid[]) AND NOT u.is_superadmin ORDER BY u.id',[organisationIds])).rows.map(r=>r.id);
  const scoped=await planLegacyCleanup(sql,organisationIds);
  return {organisationIds,oldUserIds,administratorId:roots.rows[0].id,administratorPasswordDigest:digest(roots.rows[0].password_hash),
    plan:scoped.plan,media:scoped.media};
}

export async function cleanupLegacyOrganisationRecords(database,manifest) {
  if(!manifest || !uuid(manifest.administratorId) || !Array.isArray(manifest.organisationIds)
    || !manifest.organisationIds.length || manifest.organisationIds.some(id=>!uuid(id))
    || new Set(manifest.organisationIds).size!==manifest.organisationIds.length
    || !Array.isArray(manifest.oldUserIds) || manifest.oldUserIds.some(id=>!uuid(id) || id===manifest.administratorId)
    || !Array.isArray(manifest.media) || !Array.isArray(manifest.plan) || !/^[a-f0-9]{64}$/.test(manifest.administratorPasswordDigest))throw new Error('Invalid private cleanup manifest');
  return database.transaction(async sql=>{
    await sql.query("SET LOCAL lock_timeout='5s'");
    // Protect discovery and identity from concurrent writes/schema changes.
    await sql.query('LOCK TABLE public.organisations,public.users,public.sessions IN ACCESS EXCLUSIVE MODE');
    const current=await planLegacyCleanup(sql,manifest.organisationIds);
    if(current.organisationCount!==manifest.organisationIds.length || JSON.stringify(current.plan)!==JSON.stringify(manifest.plan) || JSON.stringify(current.media)!==JSON.stringify(manifest.media))throw new Error('Cleanup manifest changed; review again');
    const names=current.plan.map(p=>'public."'+p.table.replaceAll('"','""')+'"');
    await sql.query('LOCK TABLE '+names.join(',')+' IN ACCESS EXCLUSIVE MODE');
    // Recount after all child locks are held: no writer can race deletion checks.
    const locked=await planLegacyCleanup(sql,manifest.organisationIds);
    if(JSON.stringify(locked.plan)!==JSON.stringify(manifest.plan) || JSON.stringify(locked.media)!==JSON.stringify(manifest.media))throw new Error('Cleanup records changed');
    const roots=await sql.query('SELECT id,password_hash FROM users WHERE is_superadmin');
    if(roots.rows.length!==1 || roots.rows[0].id!==manifest.administratorId
      || digest(roots.rows[0].password_hash)!==manifest.administratorPasswordDigest)throw new Error('Administrator identity changed');
    const native=(await sql.query('SELECT native_id::text FROM foundation_organisations WHERE organisation_id=ANY($1::uuid[])',[manifest.organisationIds])).rows.map(r=>r.native_id);
    const users=(await sql.query('SELECT DISTINCT user_id FROM memberships WHERE organisation_id=ANY($1::uuid[])',[manifest.organisationIds])).rows.map(r=>r.user_id);
    // Revoke sessions belonging to previous organisation members, including the admin.
    await sql.query('DELETE FROM sessions WHERE user_id=ANY($1::uuid[])',[[...new Set([...users,manifest.administratorId])]]);
    const columns=await sql.query("SELECT table_name,column_name FROM information_schema.columns WHERE table_schema='public'");
    for(const item of current.plan) {
      const table='public."'+item.table.replaceAll('"','""')+'"';
      let predicate,values;
      if(item.table==='organisations'){predicate='id=ANY($1::uuid[])';values=[manifest.organisationIds];}
      else if(columns.rows.some(c=>c.table_name===item.table && c.column_name==='organisation_id')){predicate='organisation_id=ANY($1::uuid[])';values=[manifest.organisationIds];}
      else if(item.table==='foundation_staff'){predicate='native_organisation_id=ANY($1::bigint[])';values=[native];}
      else if(item.table==='exam_student_sessions'){predicate='grant_id IN (SELECT id FROM public.exam_student_grants WHERE organisation_id=ANY($1::uuid[]))';values=[manifest.organisationIds];}
      else throw new Error('Unclassified deletion scope');
      await sql.query('DELETE FROM '+table+' WHERE '+predicate,values);
      const remaining=await sql.query('SELECT count(*)::text AS count FROM '+table+' WHERE '+predicate,values);
      if(remaining.rows[0]?.count!=='0')throw new Error('Scoped deletion incomplete: '+item.table);
    }
    const retiredAccounts=await retireLegacyAccounts(sql,manifest.oldUserIds,manifest.administratorId);
    const root=await sql.query('SELECT id,password_hash,is_superadmin FROM users WHERE id=$1',[manifest.administratorId]);
    if(!root.rows[0]?.is_superadmin || digest(root.rows[0].password_hash)!==manifest.administratorPasswordDigest)throw new Error('Administrator preservation failed');
    if((await sql.query('SELECT id FROM organisations WHERE id=ANY($1::uuid[])',[manifest.organisationIds])).rows.length)throw new Error('Organisation cleanup incomplete');
    return {removedOrganisations:manifest.organisationIds.length,retiredAccounts,administratorPreserved:true};
  });
}
