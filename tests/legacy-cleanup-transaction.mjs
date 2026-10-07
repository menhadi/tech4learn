import {test} from 'node:test';
import assert from 'node:assert/strict';
import {randomUUID} from 'node:crypto';
import {PGlite} from '@electric-sql/pglite';
import {migration} from '../apps/api/dist/schema.js';
import {foundationMigration} from '../apps/api/dist/migration-foundation.js';
import {captureLegacyCleanupManifest,cleanupLegacyOrganisationRecords} from '../deploy/virtualmin/cleanup-legacy-organisation-records.mjs';

test('cleanup removes only frozen old organisations, revokes sessions and preserves global history and administrator',async()=>{
 const pg=new PGlite(),admin=randomUUID(),old=randomUUID(),fresh=randomUUID();
 const db={transaction:fn=>pg.transaction(sql=>fn({query:(q,p)=>sql.query(q,p)}))};
 try{
  await pg.exec(migration);await pg.exec(foundationMigration);
  await pg.query("INSERT INTO users(id,email,name,password_hash,is_superadmin) VALUES($1,'synthetic@example.invalid','Synthetic','unchanged',true)",[admin]);
  await pg.query("INSERT INTO organisations(id,name,slug) VALUES($1,'Old synthetic','old-synthetic')",[old]);
  await pg.query('INSERT INTO foundation_organisations(native_id,organisation_id) VALUES(2,$1)',[old]);
  await pg.query('INSERT INTO foundation_staff(native_organisation_id,native_user_id,user_id) VALUES(2,2,$1)',[admin]);
  await pg.query("INSERT INTO audit_events(id,actor_id,organisation_id,action) VALUES($1,$2,$3,'old'),($4,$2,NULL,'global')",[randomUUID(),admin,old,randomUUID()]);
  await pg.query("INSERT INTO sessions(token_hash,user_id,expires_at) VALUES('synthetic',$1,now()+interval '1 hour')",[admin]);
  const manifest=await captureLegacyCleanupManifest(pg);
  await pg.query("INSERT INTO organisations(id,name,slug) VALUES($1,'Fresh synthetic','fresh-synthetic')",[fresh]);
  const stale=structuredClone(manifest);stale.plan[0].scopedRows='999';
  await assert.rejects(cleanupLegacyOrganisationRecords(db,stale),/manifest changed/);
  assert.equal((await pg.query('SELECT count(*)::integer AS total FROM sessions')).rows[0].total,1);
  const failing={transaction:fn=>pg.transaction(sql=>fn({query:(q,p)=>{
    if(q.startsWith('DELETE FROM public."foundation_organisations"'))throw new Error('Synthetic mid-cleanup failure');
    return sql.query(q,p);
  }}))};
  await assert.rejects(cleanupLegacyOrganisationRecords(failing,manifest),/mid-cleanup/);
  assert.equal((await pg.query('SELECT count(*)::integer AS total FROM sessions')).rows[0].total,1);
  assert.equal((await pg.query('SELECT count(*)::integer AS total FROM foundation_staff')).rows[0].total,1);
  assert.deepEqual(await cleanupLegacyOrganisationRecords(db,manifest),{removedOrganisations:1,administratorPreserved:true});
  assert.deepEqual((await pg.query('SELECT id FROM organisations')).rows,[{id:fresh}]);
  assert.equal((await pg.query('SELECT password_hash FROM users')).rows[0].password_hash,'unchanged');
  assert.equal((await pg.query('SELECT count(*)::integer AS total FROM sessions')).rows[0].total,0);
  assert.deepEqual((await pg.query('SELECT action FROM audit_events')).rows,[{action:'global'}]);
 }finally{await pg.close();}
});
