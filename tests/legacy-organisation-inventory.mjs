import { test } from 'node:test';
import assert from 'node:assert/strict';
import { PGlite } from '@electric-sql/pglite';
import { randomUUID } from 'node:crypto';
import { migration } from '../apps/api/dist/schema.js';
import { foundationMigration } from '../apps/api/dist/migration-foundation.js';
import { inventoryLegacyOrganisations } from '../deploy/virtualmin/inventory-legacy-organisations.mjs';

test('read-only inventory follows indirect tenant dependencies while preserving administrator identity',async()=>{
  const db=new PGlite(),org=randomUUID(),admin=randomUUID();
  try {
    await db.exec(migration);await db.exec(foundationMigration);
    await db.query("INSERT INTO users(id,email,name,password_hash,is_superadmin) VALUES($1,'synthetic@example.invalid','Synthetic','unused',true)",[admin]);
    await db.query("INSERT INTO organisations(id,name,slug) VALUES($1,'Synthetic','synthetic-inventory')",[org]);
    await db.query('INSERT INTO foundation_organisations(native_id,organisation_id) VALUES(2,$1)',[org]);
    await db.query('INSERT INTO foundation_staff(native_organisation_id,native_user_id,user_id) VALUES(2,2,$1)',[admin]);
    await db.exec('BEGIN TRANSACTION READ ONLY');
    const inventory=await inventoryLegacyOrganisations(db);
    assert.equal(inventory.canonicalSuperadmins,'1');
    const counts=Object.fromEntries(inventory.organisationDependentTables.map(t=>[t.table,t.totalRows]));
    assert.equal(counts.organisations,'1');assert.equal(counts.foundation_staff,'1');
    assert.equal(counts.users,undefined);assert.equal(counts.sessions,undefined);
    await db.exec('ROLLBACK');
    assert.equal((await db.query('SELECT password_hash FROM users WHERE id=$1',[admin])).rows[0].password_hash,'unused');
    assert.equal((await db.query('SELECT count(*)::integer AS total FROM organisations')).rows[0].total,1);
  } finally {await db.close();}
});
