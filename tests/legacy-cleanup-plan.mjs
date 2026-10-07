import { test } from 'node:test';
import assert from 'node:assert/strict';
import { PGlite } from '@electric-sql/pglite';
import { randomUUID } from 'node:crypto';
import { migration } from '../apps/api/dist/schema.js';
import { foundationMigration } from '../apps/api/dist/migration-foundation.js';
import { childFirstOrder,planLegacyCleanup } from '../deploy/virtualmin/plan-legacy-cleanup.mjs';

test('cleanup plan orders indirect children and scopes tenant audit while leaving administrator and data unchanged',async()=>{
  const db=new PGlite(),org=randomUUID(),admin=randomUUID();
  try {
    await db.exec(migration);await db.exec(foundationMigration);
    await db.exec('CREATE TABLE exam_student_grants(id uuid PRIMARY KEY,organisation_id uuid REFERENCES organisations(id)); CREATE TABLE exam_student_sessions(token_hash text PRIMARY KEY,grant_id uuid REFERENCES exam_student_grants(id))');
    await db.query("INSERT INTO users(id,email,name,password_hash,is_superadmin) VALUES($1,'synthetic@example.invalid','Synthetic','unchanged',true)",[admin]);
    await db.query("INSERT INTO organisations(id,name,slug) VALUES($1,'Synthetic','synthetic-plan')",[org]);
    await db.query('INSERT INTO foundation_organisations(native_id,organisation_id) VALUES(2,$1)',[org]);
    await db.query('INSERT INTO foundation_staff(native_organisation_id,native_user_id,user_id) VALUES(2,2,$1)',[admin]);
    const grant=randomUUID();
    await db.query('INSERT INTO exam_student_grants(id,organisation_id) VALUES($1,$2)',[grant,org]);
    await db.query("INSERT INTO exam_student_sessions(token_hash,grant_id) VALUES('unused-synthetic-token',$1)",[grant]);
    await db.query("INSERT INTO audit_events(id,actor_id,organisation_id,action) VALUES($1,$2,$3,'synthetic.tenant'),($4,$2,NULL,'synthetic.platform')",[randomUUID(),admin,org,randomUUID()]);
    await db.exec('CREATE TABLE synthetic_media(id integer PRIMARY KEY,organisation_id uuid REFERENCES organisations(id),content bytea)');
    const fresh=randomUUID();
    await db.query("INSERT INTO organisations(id,name,slug) VALUES($1,'Fresh','fresh-media')",[fresh]);
    await db.query("INSERT INTO synthetic_media VALUES(1,$1,decode('010203','hex')),(2,$1,NULL),(3,$2,decode('04050607','hex')),(4,NULL,decode('0809','hex'))",[org,fresh]);
    await db.exec('BEGIN TRANSACTION READ ONLY');
    const result=await planLegacyCleanup(db,[org]);
    const order=result.plan.map(p=>p.table);
    assert.ok(order.indexOf('foundation_staff')<order.indexOf('foundation_organisations'));
    assert.ok(order.indexOf('foundation_organisations')<order.indexOf('organisations'));
    assert.equal(result.plan.find(p=>p.table==='audit_events').scopedRows,'1');
    assert.equal(result.plan.find(p=>p.table==='exam_student_sessions').scopedRows,'1');
    assert.ok(order.indexOf('exam_student_sessions')<order.indexOf('exam_student_grants'));
    assert.equal(result.organisationCount,1);
    assert.deepEqual(result.media,[{table:'synthetic_media',column:'content',objects:'1',bytes:'3',scope:'previous organisation IDs; global rows excluded'}]);
    assert.equal(JSON.stringify(result).includes('010203'),false);
    assert.equal(JSON.stringify(result).includes(admin),false);
    await db.exec('ROLLBACK');
    assert.equal((await db.query('SELECT password_hash FROM users')).rows[0].password_hash,'unchanged');
    assert.equal((await db.query('SELECT count(*)::integer AS total FROM audit_events')).rows[0].total,2);
    await db.exec('CREATE TABLE unclassified_child(id integer PRIMARY KEY,tenant uuid REFERENCES organisations(id))');
    await assert.rejects(planLegacyCleanup(db),/Unclassified/);
  } finally {await db.close();}
});

test('cycles and duplicate tables block cleanup ordering',()=>{
  assert.throws(()=>childFirstOrder(['a','b'],[['a','b'],['b','a']]),/cycle/);
  assert.throws(()=>childFirstOrder(['a'],[['a','a']]),/cycle/);
  assert.throws(()=>childFirstOrder(['a','a'],[]),/Duplicate/);
});
