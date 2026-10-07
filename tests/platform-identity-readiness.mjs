import { test } from 'node:test';
import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { PGlite } from '@electric-sql/pglite';
import { migration } from '../apps/api/dist/schema.js';
import { foundationMigration } from '../apps/api/dist/migration-foundation.js';
import { foundationPlatformMigration } from '../apps/api/dist/migration-foundation-platform.js';
import { checkPlatformIdentity } from '../deploy/virtualmin/check-platform-identity.mjs';

test('readiness refuses missing schema, unprovisioned realms, deactivated links and demoted accounts', async () => {
  const db = new PGlite();
  try {
    await db.exec(migration);
    await db.exec(foundationMigration);
    assert.equal((await checkPlatformIdentity(db, '1'))[0].ready, false);
    await db.exec(foundationPlatformMigration);
    assert.equal((await checkPlatformIdentity(db, '1')).every(c => c.ready), false);
    const user = randomUUID();
    await db.query("INSERT INTO users(id,email,name,password_hash,is_superadmin) VALUES($1,'fixture@example.invalid','Synthetic','unused',true)", [user]);
    await db.query('INSERT INTO foundation_platform_staff(native_organisation_id,native_user_id,user_id) VALUES(1,2,$1)', [user]);
    await db.exec('BEGIN TRANSACTION READ ONLY');
    assert.equal((await checkPlatformIdentity(db, '1')).every(c => c.ready), true);
    await db.exec('ROLLBACK');
    await db.exec('UPDATE foundation_platform_staff SET active=false');
    assert.equal((await checkPlatformIdentity(db, '1')).at(-1).ready, false);
    await db.exec('UPDATE foundation_platform_staff SET active=true; UPDATE users SET is_superadmin=false');
    assert.equal((await checkPlatformIdentity(db, '1')).at(-1).ready, false);
    const organisation = randomUUID();
    await db.query("INSERT INTO organisations(id,name,slug) VALUES($1,'Synthetic tenant','synthetic-readiness')", [organisation]);
    await db.query('INSERT INTO foundation_organisations(native_id,organisation_id) VALUES(7,$1)', [organisation]);
    const tenantChecks = await checkPlatformIdentity(db, '7');
    assert.equal(tenantChecks[1].ready, false);
    assert.equal(tenantChecks[2].ready, false);
    await assert.rejects(checkPlatformIdentity(db, '1 OR 1=1'));
  } finally { await db.close(); }
});
