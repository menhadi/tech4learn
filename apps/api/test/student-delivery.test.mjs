import test from 'node:test';
import assert from 'node:assert/strict';
import {randomUUID} from 'node:crypto';
import {PGlite} from '@electric-sql/pglite';
import {migration} from '../dist/schema.js';
import {accessMigration} from '../dist/migration-access.js';
import {learnerMigration} from '../dist/migration-learners.js';
import {foundationMigration} from '../dist/migration-foundation.js';
import {foundationLearnerMigration} from '../dist/migration-foundation-learners.js';
import {studentDeliveryMigration} from '../dist/migration-student-delivery.js';

test('student delivery intent is tenant-bound, ID-only, coalesced and atomic with enrolment changes',async()=>{
 const pg=new PGlite();
 try {
  for(const sql of [migration,accessMigration,learnerMigration,foundationMigration,foundationLearnerMigration,studentDeliveryMigration])await pg.exec(sql);
  const org=randomUUID(),other=randomUUID(),centre=randomUUID(),group=randomUUID(),learner=randomUUID();
  for(const [id,slug] of [[org,'synthetic'],[other,'unlinked']])await pg.query('INSERT INTO organisations(id,name,slug) VALUES($1,$2,$2)',[id,slug]);
  await pg.query('INSERT INTO foundation_organisations(native_id,organisation_id) VALUES(7,$1)',[org]);
  await pg.query("INSERT INTO centres(id,organisation_id,name) VALUES($1,$2,'Synthetic centre')",[centre,org]);
  await pg.query("INSERT INTO learning_groups(id,organisation_id,centre_id,name) VALUES($1,$2,$3,'Synthetic section')",[group,org,centre]);
  await pg.query("INSERT INTO learners(id,organisation_id,group_id,code,name) VALUES($1,$2,$3,'TEST-001','Synthetic Student')",[learner,org,group]);
  const delivery=async()=> (await pg.query('SELECT * FROM foundation_student_deliveries')).rows[0];
  assert.equal((await delivery()).revision,1);
  assert.equal((await delivery()).native_organisation_id,7);
  assert.equal((await delivery()).organisation_id,org);
  assert.equal((await delivery()).learner_id,learner);
  assert.ok(!JSON.stringify(await delivery()).includes('Synthetic Student'));
  await pg.query('UPDATE learners SET name=name WHERE id=$1',[learner]);
  assert.equal((await delivery()).revision,1);
  await pg.query("UPDATE learners SET name='Synthetic Updated',version=version+1 WHERE id=$1",[learner]);
  assert.equal((await delivery()).revision,2);assert.equal((await delivery()).source_version,2);
  await assert.rejects(()=>pg.transaction(async sql=>{
   await sql.query('UPDATE learners SET archived=true,version=version+1 WHERE id=$1',[learner]);
   throw new Error('synthetic rollback');
  }));
  assert.equal((await delivery()).revision,2);
  assert.equal((await pg.query('SELECT archived FROM learners WHERE id=$1',[learner])).rows[0].archived,false);
  const otherCentre=randomUUID(),otherGroup=randomUUID(),otherLearner=randomUUID();
  await pg.query("INSERT INTO centres(id,organisation_id,name) VALUES($1,$2,'Other synthetic centre')",[otherCentre,other]);
  await pg.query("INSERT INTO learning_groups(id,organisation_id,centre_id,name) VALUES($1,$2,$3,'Other section')",[otherGroup,other,otherCentre]);
  await pg.query("INSERT INTO learners(id,organisation_id,group_id,code,name) VALUES($1,$2,$3,'TEST-001','Synthetic Student')",[otherLearner,other,otherGroup]);
  assert.equal((await pg.query('SELECT count(*)::int AS n FROM foundation_student_deliveries')).rows[0].n,1);
  await pg.query('INSERT INTO foundation_organisations(native_id,organisation_id) VALUES(8,$1)',[other]);
  assert.equal((await pg.query('SELECT count(*)::int AS n FROM foundation_student_deliveries')).rows[0].n,1);
  await pg.query('UPDATE learners SET age=9,version=version+1 WHERE id=$1',[otherLearner]);
  const otherDelivery=(await pg.query('SELECT * FROM foundation_student_deliveries WHERE learner_id=$1',[otherLearner])).rows[0];
  assert.equal(otherDelivery.native_organisation_id,8);
  assert.equal(otherDelivery.organisation_id,other);
  assert.equal(otherDelivery.revision,1);
  await pg.query('UPDATE foundation_organisations SET active=false WHERE native_id=7');
  await pg.query('UPDATE learners SET archived=true,version=version+1 WHERE id=$1',[learner]);
  assert.equal((await delivery()).revision,2);
  await assert.rejects(()=>pg.query('INSERT INTO foundation_student_deliveries(native_organisation_id,organisation_id,learner_id,source_version) VALUES(7,$1,$2,1)',[other,otherLearner]));
  await assert.rejects(()=>pg.query('UPDATE foundation_student_deliveries SET delivered_revision=revision+1'));
  assert.equal((await pg.query('SELECT count(*)::int AS n FROM foundation_student_deliveries')).rows[0].n,2);
 } finally {await pg.close();}
});
