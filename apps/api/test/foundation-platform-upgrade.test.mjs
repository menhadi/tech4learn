import { test } from 'node:test';
import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { PGlite } from '@electric-sql/pglite';
import { migration } from '../dist/schema.js';
import { foundationMigration } from '../dist/migration-foundation.js';
import { foundationPlatformMigration } from '../dist/migration-foundation-platform.js';

test('platform realm upgrade preserves existing tenant identities and prevents realm reassignment',async()=>{
  const pg=new PGlite(),org=randomUUID(),user=randomUUID();
  try {
    await pg.exec(migration);await pg.exec(foundationMigration);
    await pg.query("INSERT INTO users(id,email,name,password_hash,is_superadmin) VALUES($1,'synthetic@example.invalid','Synthetic administrator','unused-synthetic-value',true)",[user]);
    await pg.query("INSERT INTO organisations(id,name,slug) VALUES($1,'Synthetic','synthetic')",[org]);
    await pg.query('INSERT INTO foundation_organisations(native_id,organisation_id,active,version) VALUES(7,$1,false,3)',[org]);
    await pg.query('INSERT INTO foundation_staff(native_organisation_id,native_user_id,user_id,active,version) VALUES(7,9,$1,false,4)',[user]);
    await pg.exec(foundationPlatformMigration);
    assert.deepEqual((await pg.query('SELECT native_id::text,kind FROM foundation_realms')).rows,[{native_id:'7',kind:'organisation'}]);
    assert.deepEqual((await pg.query('SELECT active,version FROM foundation_organisations')).rows,[{active:false,version:3}]);
    assert.deepEqual((await pg.query('SELECT active,version FROM foundation_staff')).rows,[{active:false,version:4}]);
    await pg.query('INSERT INTO foundation_platform_staff(native_organisation_id,native_user_id,user_id) VALUES(1,11,$1)',[user]);
    await assert.rejects(pg.query("UPDATE foundation_realms SET kind='platform' WHERE native_id=7"));
    await assert.rejects(pg.query('UPDATE foundation_organisations SET native_id=1 WHERE native_id=7'));
    await assert.rejects(pg.query('UPDATE foundation_platform_staff SET native_organisation_id=7 WHERE native_organisation_id=1'));
    await assert.rejects(pg.query('DELETE FROM foundation_realms WHERE native_id=7'));
    await assert.rejects(pg.query('INSERT INTO foundation_platform_staff(native_organisation_id,native_user_id,user_id) VALUES(0,11,$1)',[user]));
    assert.equal((await pg.query('SELECT max(version) AS version FROM schema_versions')).rows[0].version,19);
  } finally {await pg.close();}
});
