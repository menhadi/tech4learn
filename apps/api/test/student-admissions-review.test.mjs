import test from 'node:test';
import assert from 'node:assert/strict';
import {randomUUID,generateKeyPairSync,sign} from 'node:crypto';
import {PGlite} from '@electric-sql/pglite';
import {migration} from '../dist/schema.js';
import {accessMigration} from '../dist/migration-access.js';
import {learnerMigration} from '../dist/migration-learners.js';
import {configurationMigration} from '../dist/migration-configuration.js';
import {academicMigration} from '../dist/migration-academic.js';
import {LearnersService} from '../dist/learners.service.js';
import {AccessService} from '../dist/access.service.js';
import {foundationMigration} from '../dist/migration-foundation.js';
import {foundationLearnerMigration} from '../dist/migration-foundation-learners.js';
import {studentSignersMigration} from '../dist/migration-student-signers.js';
import {studentAdmissionsMigration} from '../dist/migration-student-admissions.js';
import {StudentAdmissionsService} from '../dist/student-admissions.service.js';
import {registerNativeStudentKey} from '../dist/student-delivery-proof.js';
import {studentDeliveryMigration} from '../dist/migration-student-delivery.js';
import {FoundationService} from '../dist/foundation.service.js';


test('signed admission review is tenant-bound, immutable, idempotent and rejects revoked origins',async()=>{
 const pg=new PGlite();
 try {
  for(const migrationSql of [migration,accessMigration,learnerMigration,configurationMigration,academicMigration,foundationMigration,foundationLearnerMigration,studentSignersMigration,studentDeliveryMigration,studentAdmissionsMigration])await pg.exec(migrationSql);
  const db={query:(s,p)=>pg.query(s,p),transaction:run=>pg.transaction(sql=>run({query:(s,p)=>p?.length?sql.query(s,p):sql.exec(s).then(r=>r.at(-1))}))};
  const actor={id:randomUUID(),is_superadmin:true},org=randomUUID(),other=randomUUID(),centre=randomUUID(),group=randomUUID(),otherCentre=randomUUID(),otherGroup=randomUUID();
  await pg.query("INSERT INTO users(id,email,name,password_hash,is_superadmin) VALUES($1,'synthetic@example.invalid','Synthetic reviewer','unused',true)",[actor.id]);
  for(const [id,slug] of [[org,'synthetic'],[other,'other']])await pg.query('INSERT INTO organisations(id,name,slug) VALUES($1,$2,$2)',[id,slug]);
  for(const [c,o,g] of [[centre,org,group],[otherCentre,other,otherGroup]]){
   await pg.query("INSERT INTO centres(id,organisation_id,name) VALUES($1,$2,'Synthetic centre')",[c,o]);
   await pg.query("INSERT INTO learning_groups(id,organisation_id,centre_id,name) VALUES($1,$2,$3,'Synthetic section')",[g,o,c]);
  }
  await pg.query('INSERT INTO foundation_organisations(native_id,organisation_id) VALUES(7,$1)',[org]);
  await pg.query('INSERT INTO foundation_staff(native_organisation_id,native_user_id,user_id) VALUES(7,3,$1)',[actor.id]);
  const access=new AccessService(db),learners=new LearnersService(db,access),service=new StudentAdmissionsService(db,access,learners);
  const {privateKey,publicKey}=generateKeyPairSync('rsa',{modulusLength:2048});
  const pem=publicKey.export({type:'spki',format:'pem'}).toString(),registered=await registerNativeStudentKey(db,actor.id,pem);
  const role=randomUUID();
  await pg.query("INSERT INTO access_roles(id,organisation_id,name,permissions) VALUES($1,$2,'Synthetic admissions reviewer',ARRAY['learners.create','learners.view','learners.edit','learners.contacts'])",[role,org]);
  await pg.query("INSERT INTO memberships(user_id,organisation_id,role,role_id) VALUES($1,$2,'staff',$3)",[actor.id,org,role]);
  actor.is_superadmin=false;await pg.query('UPDATE users SET is_superadmin=false WHERE id=$1',[actor.id]);
  const admission=randomUUID(),fields={name:'Synthetic applicant',code:'ADMIT-001',group_id:group,custom_values:{}};
  const proof=(changes={})=>{
   const payload={issuer:'tech4learn-native-admission-v1',nativeOrganisationId:'7',nativeUserId:'3',nativeStudentId:'99',
    admissionId:admission,version:1,fingerprint:'a'.repeat(64),issuedAt:Math.floor(Date.now()/1000),...changes};
   const bytes=Buffer.from(JSON.stringify(payload));return {keyId:registered.keyId,receipt:bytes.toString('base64url'),signature:sign('sha256',bytes,privateKey).toString('base64url')};
  };
  const review=(p=proof(),f=fields)=>service.review(actor,'7','3',{proof:p,fields:f,reviewConfirmed:true});
  assert.deepEqual(await review(),{learnerId:admission,admissionId:admission});
  const canonical=new FoundationService(db,access,learners);
  assert.equal((await canonical.studentDeliverySnapshot(actor,'7','3',admission)).reviewRequired,true);
  const binding=await service.bindingSnapshot(actor,'7','3',admission);
  assert.equal(binding.nativeStudentId,'99');assert.equal(binding.learnerId,admission);assert.equal(binding.state,'awaiting_native');
  assert.ok(!Object.hasOwn(binding,'email'));assert.ok(!Object.hasOwn(binding,'password'));

  assert.deepEqual(await review(proof(),{custom_values:{},group_id:group,code:'ADMIT-001',name:'Synthetic applicant'}),{learnerId:admission,admissionId:admission});
  await assert.rejects(()=>review(proof(),{...fields,name:'Changed applicant'}));
  await assert.rejects(()=>review(proof({nativeStudentId:'100'})));
  await assert.rejects(()=>review(proof({nativeOrganisationId:'8'})));
  await assert.rejects(()=>review(proof({nativeUserId:'4'})));
  await assert.rejects(()=>review(proof({admissionId:randomUUID(),nativeStudentId:'100'}),{...fields,code:'ADMIT-002',group_id:otherGroup}));
  assert.equal((await pg.query('SELECT count(*)::int AS n FROM learners')).rows[0].n,1);
  assert.equal((await pg.query('SELECT count(*)::int AS n FROM foundation_student_admissions')).rows[0].n,1);
  const deliveryProof=(nativeStudentId)=>{
   const payload={issuer:'tech4learn-native-student-v1',nativeOrganisationId:'7',nativeUserId:'3',nativeStudentId,
    learnerId:admission,revision:1,issuedAt:Math.floor(Date.now()/1000)};
   const bytes=Buffer.from(JSON.stringify(payload));return {keyId:registered.keyId,receipt:bytes.toString('base64url'),signature:sign('sha256',bytes,privateKey).toString('base64url')};
  };
  await assert.rejects(()=>canonical.acknowledgeStudentDelivery(actor,'7','3',admission,deliveryProof('100')));
  assert.equal((await pg.query('SELECT count(*)::int AS n FROM foundation_learners')).rows[0].n,0);
  assert.equal((await canonical.acknowledgeStudentDelivery(actor,'7','3',admission,deliveryProof('99'))).delivered,true);
  assert.equal((await canonical.studentDeliverySnapshot(actor,'7','3',admission)).reviewRequired,false);
  const ledger=(await pg.query('SELECT * FROM foundation_student_admissions')).rows[0];
  assert.equal(ledger.state,'linked');assert.equal(ledger.learner_id,admission);
  assert.ok(!JSON.stringify(ledger).includes('Synthetic applicant'));
  await pg.query("UPDATE access_roles SET permissions=ARRAY['learners.view','learners.edit'] WHERE id=$1",[role]);
  await assert.rejects(()=>review());
  await pg.query("UPDATE access_roles SET permissions=ARRAY['learners.create','learners.view','learners.edit','learners.contacts'] WHERE id=$1",[role]);
  await pg.query("UPDATE memberships SET scope_type='groups',scope_ids=$2::uuid[] WHERE user_id=$1",[actor.id,[otherGroup]]);
  await assert.rejects(()=>service.bindingSnapshot(actor,'7','3',admission));
  await pg.query("UPDATE memberships SET scope_type='organisation',scope_ids='{}' WHERE user_id=$1",[actor.id]);
  await pg.query('UPDATE foundation_native_signers SET active=false WHERE key_id=$1',[registered.keyId]);
  await assert.rejects(()=>review());
  await pg.query('UPDATE foundation_native_signers SET active=true WHERE key_id=$1',[registered.keyId]);
  await pg.query('UPDATE foundation_staff SET active=false');
  await assert.rejects(()=>review());
 } finally {await pg.close();}
});
