import test from 'node:test';
import assert from 'node:assert/strict';
import {randomUUID} from 'node:crypto';
import {PGlite} from '@electric-sql/pglite';
import {migration} from '../dist/schema.js';
import {accessMigration} from '../dist/migration-access.js';
import {learnerMigration} from '../dist/migration-learners.js';
import {configurationMigration} from '../dist/migration-configuration.js';
import {academicMigration} from '../dist/migration-academic.js';
import {LearnersService} from '../dist/learners.service.js';
import {AccessService} from '../dist/access.service.js';

test('reviewed admission creation uses canonical validation, fixed identity and atomic enrolment',async()=>{
 const pg=new PGlite();
 try {
  for(const migrationSql of [migration,accessMigration,learnerMigration,configurationMigration,academicMigration])await pg.exec(migrationSql);
  const db={query:(s,p)=>pg.query(s,p),transaction:run=>pg.transaction(sql=>run({query:(s,p)=>p?.length?sql.query(s,p):sql.exec(s).then(r=>r.at(-1))}))};
  const actor={id:randomUUID(),is_superadmin:true},org=randomUUID(),other=randomUUID(),centre=randomUUID(),group=randomUUID(),otherCentre=randomUUID(),otherGroup=randomUUID();
  await pg.query("INSERT INTO users(id,email,name,password_hash,is_superadmin) VALUES($1,'synthetic@example.invalid','Synthetic reviewer','unused',true)",[actor.id]);
  for(const [id,slug] of [[org,'synthetic'],[other,'other']])await pg.query('INSERT INTO organisations(id,name,slug) VALUES($1,$2,$2)',[id,slug]);
  for(const [c,o,g] of [[centre,org,group],[otherCentre,other,otherGroup]]){
   await pg.query("INSERT INTO centres(id,organisation_id,name) VALUES($1,$2,'Synthetic centre')",[c,o]);
   await pg.query("INSERT INTO learning_groups(id,organisation_id,centre_id,name) VALUES($1,$2,$3,'Synthetic section')",[g,o,c]);
  }
  const service=new LearnersService(db,new AccessService(db)),admission=randomUUID();
  const fields={name:'Synthetic applicant',code:'ADMIT-001',group_id:group,custom_values:{}};
  const create=(id,b=fields)=>db.transaction(sql=>service.createReviewedAdmission(sql,actor,org,b,id));
  assert.deepEqual(await create(admission),{id:admission});
  assert.equal((await pg.query('SELECT learner_id FROM learner_enrolments')).rows[0].learner_id,admission);
  await assert.rejects(()=>create(admission,{...fields,code:'ADMIT-002'}));
  await assert.rejects(()=>create(randomUUID(),{...fields,code:'ADMIT-002',group_id:otherGroup}));
  await assert.rejects(()=>db.transaction(async sql=>{await service.createReviewedAdmission(sql,actor,org,{...fields,code:'ADMIT-003',name:'Another synthetic'},randomUUID());throw new Error('rollback fixture');}));
  assert.equal((await pg.query('SELECT count(*)::int AS n FROM learners')).rows[0].n,1);
  assert.equal((await pg.query('SELECT count(*)::int AS n FROM learner_enrolments')).rows[0].n,1);
  await pg.query("INSERT INTO organisation_settings(organisation_id,enabled_modules) VALUES($1,'{\"learners\":false}')",[org]);
  await assert.rejects(()=>create(randomUUID(),{...fields,name:'Disabled synthetic',code:'ADMIT-004'}));
 } finally {await pg.close();}
});
