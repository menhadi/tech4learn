import { test } from 'node:test';
import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { PGlite } from '@electric-sql/pglite';
import { migration } from '../dist/schema.js';
import { accessMigration } from '../dist/migration-access.js';
import { foundationMigration } from '../dist/migration-foundation.js';
import { foundationPlatformMigration } from '../dist/migration-foundation-platform.js';
import { provisionPlatformAdministrator } from '../dist/foundation-platform-provision.js';

test('explicit server provisioning is audited, idempotent and rejects role, realm and identity conflicts', async()=>{
  const pg=new PGlite();
  const db={transaction:fn=>pg.transaction(sql=>fn({query:(q,p)=>sql.query(q,p)}))};
  const root=randomUUID(),ordinary=randomUUID(),other=randomUUID(),org=randomUUID();
  try {
    await pg.exec(migration);await pg.exec(accessMigration);await pg.exec(foundationMigration);
    for(const [id,admin] of [[root,true],[ordinary,false],[other,true]]) await pg.query('INSERT INTO users(id,email,name,password_hash,is_superadmin) VALUES($1,$2,$3,$4,$5)',[id,id+'@example.invalid','Synthetic','unused',admin]);
    await assert.rejects(provisionPlatformAdministrator(db,root,'1','1'));
    await pg.exec(foundationPlatformMigration);
    await assert.rejects(provisionPlatformAdministrator(db,ordinary,'1','1'));
    await assert.rejects(provisionPlatformAdministrator(db,root,'0','1'));
    assert.deepEqual(await provisionPlatformAdministrator(db,root,'1','1'),{created:true});
    assert.deepEqual(await provisionPlatformAdministrator(db,root,'1','1'),{created:false});
    assert.equal((await pg.query("SELECT count(*)::integer AS count FROM audit_events WHERE action='foundation.platform_staff_linked' AND organisation_id IS NULL")).rows[0].count,1);
    await assert.rejects(provisionPlatformAdministrator(db,other,'1','1'));
    await assert.rejects(provisionPlatformAdministrator(db,root,'1','2'));
    await pg.exec('UPDATE foundation_platform_staff SET active=false');
    await assert.rejects(provisionPlatformAdministrator(db,root,'1','1'));
    assert.equal((await pg.query('SELECT active FROM foundation_platform_staff')).rows[0].active,false);
    await pg.query("INSERT INTO organisations(id,name,slug) VALUES($1,'Synthetic','synthetic-provision')",[org]);
    await pg.query('INSERT INTO foundation_organisations(native_id,organisation_id) VALUES(2,$1)',[org]);
    await assert.rejects(provisionPlatformAdministrator(db,other,'2','2'));
    await pg.exec('UPDATE users SET is_superadmin=false');
    await assert.rejects(provisionPlatformAdministrator(db,root,'1','1'));
  } finally {await pg.close();}
});
