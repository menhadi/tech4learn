import {test} from 'node:test';
import assert from 'node:assert/strict';
import {randomUUID} from 'node:crypto';
import {PGlite} from '@electric-sql/pglite';
import {migration} from '../apps/api/dist/schema.js';
import {foundationMigration} from '../apps/api/dist/migration-foundation.js';
import {captureLegacyCleanupManifest,cleanupLegacyOrganisationRecords} from '../deploy/virtualmin/cleanup-legacy-organisation-records.mjs';

test('cleanup removes only frozen old organisations, revokes sessions and preserves global history and administrator',async()=>{
 const pg=new PGlite(),admin=randomUUID(),teacher=randomUUID(),old=randomUUID(),fresh=randomUUID();
 const timestamp=new Date().toISOString(),archive='a'.repeat(64);
 const evidence={backupManifest:{createdAt:timestamp,bytes:1234,sha256:archive,archiveListed:true,archiveDecoded:true,restoreTested:false,liveDatabaseChanged:false},restoreReceipt:{verifiedAt:timestamp,database:'tech4learn_cleanup_restore',archiveSha256:archive,counts:{admins:1,organisations:1,learners:0,attendance_sessions:0},administratorPreserved:true,liveDatabaseChanged:false}};
 const cleanup=(database,manifest)=>cleanupLegacyOrganisationRecords(database,manifest,evidence);
 const db={transaction:fn=>pg.transaction(sql=>fn({query:(q,p)=>sql.query(q,p)}))};
 try{
  await pg.exec(migration);await pg.exec(foundationMigration);
  await pg.query("INSERT INTO users(id,email,name,password_hash,is_superadmin) VALUES($1,'synthetic@example.invalid','Synthetic','unchanged',true)",[admin]);
  await pg.query("INSERT INTO users(id,email,name,password_hash,is_superadmin) VALUES($1,'teacher@example.invalid','Synthetic teacher','unused',false)",[teacher]);
  await pg.query("INSERT INTO organisations(id,name,slug) VALUES($1,'Old synthetic','old-synthetic')",[old]);
  await pg.query('INSERT INTO foundation_organisations(native_id,organisation_id) VALUES(2,$1)',[old]);
  await pg.query('INSERT INTO foundation_staff(native_organisation_id,native_user_id,user_id) VALUES(2,2,$1)',[admin]);
  await pg.query("INSERT INTO memberships(user_id,organisation_id,role) VALUES($1,$2,'organisation_admin')",[teacher,old]);
  await pg.query("INSERT INTO audit_events(id,actor_id,organisation_id,action) VALUES($1,$2,$3,'old'),($4,$5,NULL,'global')",[randomUUID(),admin,old,randomUUID(),teacher]);
  await pg.query("INSERT INTO sessions(token_hash,user_id,expires_at) VALUES('synthetic',$1,now()+interval '1 hour')",[admin]);
  const initialTimeouts=(await pg.query("SELECT current_setting('statement_timeout') AS statement,current_setting('idle_in_transaction_session_timeout') AS idle")).rows[0];
  const manifest=await captureLegacyCleanupManifest(pg,archive);
  await pg.query("INSERT INTO organisations(id,name,slug) VALUES($1,'Fresh synthetic','fresh-synthetic')",[fresh]);
  await assert.rejects(cleanupLegacyOrganisationRecords(db,manifest),/restore receipt/);
  const wrongArchive=structuredClone(manifest);wrongArchive.archiveSha256='b'.repeat(64);
  await assert.rejects(cleanup(db,wrongArchive),/not bound/);
  assert.equal((await pg.query('SELECT count(*)::integer AS total FROM sessions')).rows[0].total,1);
  const stale=structuredClone(manifest);stale.plan[0].scopedRows='999';
  await assert.rejects(cleanup(db,stale),/manifest changed/);
  assert.equal((await pg.query('SELECT count(*)::integer AS total FROM sessions')).rows[0].total,1);
  const staleMedia=structuredClone(manifest);
  staleMedia.media.push({table:'synthetic_media',column:'content',objects:'1',bytes:'1'});
  await assert.rejects(cleanup(db,staleMedia),/manifest changed/);
  assert.equal((await pg.query('SELECT count(*)::integer AS total FROM sessions')).rows[0].total,1);
  // Replacing a member leaves scoped row counts unchanged, but changes deletion ownership.
  const replacement=randomUUID();
  await pg.query("INSERT INTO users(id,email,name,password_hash,is_superadmin) VALUES($1,'replacement@example.invalid','Replacement','unused',false)",[replacement]);
  await pg.query('UPDATE memberships SET user_id=$1 WHERE user_id=$2 AND organisation_id=$3',[replacement,teacher,old]);
  await assert.rejects(cleanup(db,manifest),/account snapshot changed/);
  assert.equal((await pg.query('SELECT count(*)::integer AS total FROM sessions')).rows[0].total,1);
  assert.equal((await pg.query('SELECT user_id FROM memberships WHERE organisation_id=$1',[old])).rows[0].user_id,replacement);
  await pg.query('UPDATE memberships SET user_id=$1 WHERE user_id=$2 AND organisation_id=$3',[teacher,replacement,old]);
  await pg.query('DELETE FROM users WHERE id=$1',[replacement]);
  const failing={transaction:fn=>pg.transaction(sql=>fn({query:(q,p)=>{
    if(q.startsWith('DELETE FROM public."foundation_organisations"'))throw new Error('Synthetic mid-cleanup failure');
    return sql.query(q,p);
  }}))};
  await assert.rejects(cleanup(failing,manifest),/mid-cleanup/);
  assert.equal((await pg.query('SELECT count(*)::integer AS total FROM sessions')).rows[0].total,1);
  assert.equal((await pg.query('SELECT count(*)::integer AS total FROM foundation_staff')).rows[0].total,1);
  // A trigger can suppress DELETE without raising an error, including on non-FK audit/media rows.
  await pg.exec(`CREATE FUNCTION suppress_old_audit_delete() RETURNS trigger LANGUAGE plpgsql AS $$
    BEGIN RETURN NULL; END $$;
    CREATE TRIGGER suppress_old_audit_delete BEFORE DELETE ON audit_events
    FOR EACH ROW EXECUTE FUNCTION suppress_old_audit_delete()`);
  await assert.rejects(cleanup(db,manifest),/Scoped deletion incomplete: audit_events/);
  assert.equal((await pg.query('SELECT count(*)::integer AS total FROM sessions')).rows[0].total,1);
  assert.equal((await pg.query('SELECT count(*)::integer AS total FROM audit_events')).rows[0].total,2);
  assert.equal((await pg.query('SELECT count(*)::integer AS total FROM foundation_staff')).rows[0].total,1);
  await pg.exec('DROP TRIGGER suppress_old_audit_delete ON audit_events; DROP FUNCTION suppress_old_audit_delete()');
  await pg.exec('CREATE TABLE retained_account_records(id integer PRIMARY KEY,actor_id uuid REFERENCES users(id))');
  await pg.query('INSERT INTO retained_account_records(id,actor_id) VALUES(1,$1)',[teacher]);
  await assert.rejects(cleanup(db,manifest),/Retained account records/);
  assert.equal((await pg.query('SELECT count(*)::integer AS total FROM sessions')).rows[0].total,1);
  assert.equal((await pg.query('SELECT count(*)::integer AS total FROM memberships')).rows[0].total,1);
  await pg.exec('DELETE FROM retained_account_records');
  assert.deepEqual(await cleanup(db,manifest),{removedOrganisations:1,retiredAccounts:1,administratorPreserved:true});
  assert.deepEqual((await pg.query("SELECT current_setting('statement_timeout') AS statement,current_setting('idle_in_transaction_session_timeout') AS idle")).rows[0],initialTimeouts);
  assert.deepEqual((await pg.query('SELECT id FROM organisations')).rows,[{id:fresh}]);
  assert.equal((await pg.query('SELECT password_hash FROM users')).rows[0].password_hash,'unchanged');
  assert.equal((await pg.query('SELECT count(*)::integer AS total FROM sessions')).rows[0].total,0);
  assert.deepEqual((await pg.query('SELECT action FROM audit_events')).rows,[{action:'global'}]);
  assert.equal((await pg.query('SELECT actor_id FROM audit_events')).rows[0].actor_id,null);
 }finally{await pg.close();}
});
