import { test } from 'node:test';
import assert from 'node:assert/strict';
import { randomUUID,generateKeyPairSync,sign } from 'node:crypto';
import { PGlite } from '@electric-sql/pglite';
import { createApp } from '../dist/bootstrap.js';
import { migration } from '../dist/schema.js';
import { accessMigration } from '../dist/migration-access.js';
import { learnerMigration } from '../dist/migration-learners.js';
import { configurationMigration } from '../dist/migration-configuration.js';
import { foundationMigration } from '../dist/migration-foundation.js';
import { foundationLearnerMigration } from '../dist/migration-foundation-learners.js';
import { foundationPlatformMigration } from '../dist/migration-foundation-platform.js';
import { studentDeliveryMigration } from '../dist/migration-student-delivery.js';
import { studentSignersMigration } from '../dist/migration-student-signers.js';
import { registerNativeStudentKey } from '../dist/student-delivery-proof.js';
import { digest,hashPassword } from '../dist/security.js';

test('foundation identity links require explicit accounts, current authority and revocable attendance grants', async t => {
  const pg = new PGlite();
  const orgA = randomUUID(), orgB = randomUUID(), owner = randomUUID(), staff = randomUUID(), outsider = randomUUID();
  await pg.exec(migration);
  for (const [id,slug] of [[orgA,'synthetic-one'],[orgB,'synthetic-two']])
    await pg.query('INSERT INTO organisations(id,name,slug) VALUES($1,$2,$2)',[id,slug]);
  await pg.exec(accessMigration); await pg.exec(learnerMigration); await pg.exec(configurationMigration); await pg.exec(foundationMigration);
  await pg.exec(foundationLearnerMigration); await pg.exec(foundationPlatformMigration);
  await pg.exec(studentDeliveryMigration);
  await pg.exec(studentSignersMigration);
  const learnerA=randomUUID(),learnerB=randomUUID(),centreA=randomUUID(),groupA=randomUUID();
  for(const [org,learner,centre,group] of [[orgA,learnerA,centreA,groupA],[orgB,learnerB,randomUUID(),randomUUID()]]) {
    await pg.query('INSERT INTO centres(id,organisation_id,name) VALUES($1,$2,$3)',[centre,org,'Synthetic centre']);
    await pg.query('INSERT INTO learning_groups(id,organisation_id,centre_id,name) VALUES($1,$2,$3,$4)',[group,org,centre,'Synthetic section']);
    await pg.query('INSERT INTO learners(id,organisation_id,group_id,code,name) VALUES($1,$2,$3,$4,$5)',[learner,org,group,'SYNTHETIC','Synthetic learner']);
  }
  const tokens = { owner:'a'.repeat(64), staff:'b'.repeat(64), outsider:'c'.repeat(64) };
  const password='long synthetic foundation password';
  const hash=await hashPassword(password);
  for (const [id,name,admin] of [[owner,'owner',true],[staff,'staff',false],[outsider,'outsider',false]]) {
    await pg.query('INSERT INTO users(id,email,name,password_hash,is_superadmin) VALUES($1,$2,$3,$4,$5)',[id,`${name}@example.invalid`,name,hash,admin]);
    await pg.query("INSERT INTO sessions(token_hash,user_id,expires_at) VALUES($1,$2,now()+interval '1 hour')",[digest(tokens[name]),id]);
  }
  for (const [user,org] of [[staff,orgA],[outsider,orgB]])
    await pg.query("INSERT INTO memberships(user_id,organisation_id,role,role_id) SELECT $1,$2,'organisation_admin',id FROM access_roles WHERE organisation_id=$2 AND protected",[user,org]);
  await pg.query("INSERT INTO organisation_settings(organisation_id,enabled_modules) VALUES($1,'{\"attendance\":true}')",[orgA]);
  const adapter = {query:(q,p)=>pg.query(q,p),transaction:fn=>pg.transaction(sql=>fn({query:(q,p)=>sql.query(q,p)})),onModuleDestroy:async()=>{}};
  const app = await createApp(undefined, adapter);
  await app.listen(0,'127.0.0.1');
  const base = `${await app.getUrl()}/api/v1`;
  async function request(path, method='GET', body, who='owner', extra={}) {
    return fetch(base+path,{method,headers:{Origin:'http://localhost:5173',...(who?{Cookie:`t4l_session=${tokens[who]}`} : {}),
      ...(body===undefined?{}:{'Content-Type':'application/json','X-Tech4Learn-Request':'1'}),...extra},body:body===undefined?undefined:JSON.stringify(body)});
  }
  const linkPath='/platform/foundation/organisations', staffPath=linkPath+'/7/staff', context='/foundation/organisations/7/staff/9/attendance-context';
  try {
    await t.test('mapping administration rejects tenants, forged authority and hostile origins', async()=>{
      assert.equal((await request(linkPath,'GET',undefined,null)).status,401);
      assert.equal((await request(linkPath,'POST',{nativeOrganisationId:'7',organisationId:orgA,is_superadmin:true},'staff')).status,403);
      assert.equal((await request(linkPath,'POST',{nativeOrganisationId:'7',organisationId:orgA},'owner',{Origin:'https://evil.example.invalid'})).status,403);
      assert.equal((await request(linkPath,'POST',{nativeOrganisationId:7,organisationId:orgA})).status,400);
      assert.equal((await request(linkPath,'POST',{nativeOrganisationId:'7',organisationId:orgA})).status,201);
      assert.equal((await request(linkPath,'POST',{nativeOrganisationId:'7',organisationId:orgB})).status,409);
      assert.equal((await request(linkPath,'POST',{nativeOrganisationId:'8',organisationId:orgA})).status,409);
    });
    await t.test('only active members can be linked; no email merge, account substitution or superadmin bypass', async()=>{
      assert.equal((await request(staffPath,'POST',{nativeUserId:'9',userId:outsider})).status,404);
      assert.equal((await request(staffPath,'POST',{nativeUserId:'9',userId:staff})).status,201);
      assert.equal((await request(staffPath,'POST',{nativeUserId:'10',userId:staff})).status,409);
      assert.equal((await request(context,'GET',undefined,'owner')).status,404);
      assert.equal((await request(context,'GET',undefined,'outsider')).status,404);
      assert.equal((await request(context.replace('/7/','/8/'),'GET',undefined,'staff')).status,404);
      const res=await request(context,'GET',undefined,'staff'); assert.equal(res.status,200);
      assert.equal(res.headers.get('cache-control'),'no-store');
      const body=await res.json(); assert.equal(body.organisation.id,orgA); assert.ok(body.permissions.includes('attendance.view')); assert.ok(body.permissions.includes('groups.create')); assert.ok(body.permissions.includes('centres.view')); assert.ok(body.permissions.includes('learners.view'));
      assert.equal(body.nativeUserId,'9'); assert.equal(body.scope.type,'organisation');
      const enrolment=context.replace('attendance-context','enrolment-context');
      assert.equal((await request(enrolment,'GET',undefined,'outsider')).status,404);
      await pg.query("UPDATE organisation_settings SET enabled_modules='{\"attendance\":false,\"learners\":true}' WHERE organisation_id=$1",[orgA]);
      assert.equal((await request(context,'GET',undefined,'staff')).status,403);
      assert.equal((await request(enrolment,'GET',undefined,'staff')).status,200);
      await pg.query("UPDATE organisation_settings SET enabled_modules='{\"attendance\":true}' WHERE organisation_id=$1",[orgA]);
    });
    await t.test('student delivery snapshots use current actor and learner scope and exclude private profile material',async()=>{
      const path=`/foundation/organisations/7/staff/9/student-deliveries/${learnerA}`;
      assert.equal((await request(path,'GET',undefined,'staff')).status,404);
      await pg.query('UPDATE learners SET age=8,version=version+1 WHERE id=$1',[learnerA]);
      const response=await request(path,'GET',undefined,'staff');assert.equal(response.status,200);
      assert.equal(response.headers.get('cache-control'),'no-store');
      const value=await response.json();assert.equal(value.learnerId,learnerA);assert.equal(value.nativeOrganisationId,'7');assert.equal(value.revision,1);
      for(const field of ['guardian_name','guardian_phone','custom_values','password','photo','nativeStudentId'])assert.equal(value[field],undefined);
      assert.equal((await request(path,'GET',undefined,'outsider')).status,404);
      assert.equal((await request(path.replace(learnerA,learnerB),'GET',undefined,'staff')).status,404);
      await pg.query("UPDATE memberships SET scope_type='groups',scope_ids='{}' WHERE user_id=$1 AND organisation_id=$2",[staff,orgA]);
      assert.equal((await request(path,'GET',undefined,'staff')).status,404);
      await pg.query("UPDATE memberships SET scope_type='organisation',scope_ids='{}' WHERE user_id=$1 AND organisation_id=$2",[staff,orgA]);
      await pg.query("UPDATE organisation_settings SET enabled_modules='{\"attendance\":true,\"learners\":false}' WHERE organisation_id=$1",[orgA]);
      assert.equal((await request(path,'GET',undefined,'staff')).status,403);
      await pg.query("UPDATE organisation_settings SET enabled_modules='{\"attendance\":true}' WHERE organisation_id=$1",[orgA]);
      await pg.query('UPDATE foundation_staff SET active=false WHERE native_organisation_id=7 AND native_user_id=9');
      assert.equal((await request(path,'GET',undefined,'staff')).status,404);
      await pg.query('UPDATE foundation_staff SET active=true WHERE native_organisation_id=7 AND native_user_id=9');
    });
    await t.test('one-login identity uses the stored link and revokes sessions on failed mapping',async()=>{
      const before=(await pg.query('SELECT count(*)::int AS n FROM sessions')).rows[0].n;
      const denied=await request('/foundation/auth/login','POST',{email:'staff@example.invalid',password,nativeOrganisationId:'999'});
      assert.equal(denied.status,404);assert.equal(denied.headers.get('set-cookie'),null);
      assert.equal((await pg.query('SELECT count(*)::int AS n FROM sessions')).rows[0].n,before);
      const logged=await request('/foundation/auth/login','POST',{email:'staff@example.invalid',password,nativeOrganisationId:'7',nativeUserId:'99',is_superadmin:true});
      assert.equal(logged.status,200,await logged.clone().text());
      assert.deepEqual(await logged.json(),{nativeOrganisationId:'7',nativeUserId:'9'});
      assert.match(logged.headers.get('set-cookie'),/HttpOnly/);
      assert.equal((await request('/foundation/auth/login','POST',{email:'staff@example.invalid',password:'incorrect synthetic password',nativeOrganisationId:'7'})).status,401);
    });
    const platformPath='/platform/foundation/platforms/1/staff', platformIdentity='/foundation/platforms/1/staff/11/identity';
    await t.test('platform identities require stored superadmin authority and cannot alias attendance tenants',async()=>{
      assert.equal((await request(platformPath,'POST',{nativeUserId:'11',userId:owner,is_superadmin:true},'staff')).status,403);
      assert.equal((await request(platformPath,'POST',{nativeUserId:'11',userId:staff,is_superadmin:true})).status,403);
      assert.equal((await request(platformPath,'POST',{nativeUserId:'11',userId:owner})).status,201);
      await assert.rejects(pg.query("UPDATE foundation_realms SET kind='organisation' WHERE native_id=1"));
      assert.equal((await request(platformPath,'POST',{nativeUserId:'12',userId:owner})).status,409);
      assert.equal((await request(platformPath.replace('/1/','/7/'),'POST',{nativeUserId:'11',userId:owner})).status,409);
      assert.equal((await request(linkPath,'POST',{nativeOrganisationId:'1',organisationId:orgB})).status,409);
      await assert.rejects(pg.query('INSERT INTO foundation_organisations(native_id,organisation_id) VALUES(1,$1)',[orgB]));
      await assert.rejects(pg.query('INSERT INTO foundation_platform_staff(native_organisation_id,native_user_id,user_id) VALUES(7,11,$1)',[owner]));
      assert.equal((await request(platformPath,'GET',undefined,'staff')).status,403);
      assert.equal((await request('/foundation/organisations/1/staff/11/attendance-context')).status,404);
      assert.equal((await request(platformIdentity,'GET',undefined,'outsider')).status,403);
      const res=await request(platformIdentity);assert.equal(res.status,200);
      assert.equal(res.headers.get('cache-control'),'no-store');
      assert.deepEqual(await res.json(),{nativeOrganisationId:'1',nativeUserId:'11',userId:owner,version:1,realm:'platform'});
    });
    await t.test('platform login uses the explicit root mapping and cleans up denied sessions',async()=>{
      const before=(await pg.query('SELECT count(*)::int AS n FROM sessions')).rows[0].n;
      const denied=await request('/foundation/auth/platform/login','POST',{email:'staff@example.invalid',password,nativeOrganisationId:'1',is_superadmin:true});
      assert.equal(denied.status,403);assert.equal(denied.headers.get('set-cookie'),null);
      assert.equal((await pg.query('SELECT count(*)::int AS n FROM sessions')).rows[0].n,before);
      const logged=await request('/foundation/auth/platform/login','POST',{email:'owner@example.invalid',password,nativeOrganisationId:'1',nativeUserId:'99'});
      assert.equal(logged.status,200,await logged.clone().text());
      assert.deepEqual(await logged.json(),{nativeOrganisationId:'1',nativeUserId:'11',userId:owner,version:1,realm:'platform'});
      assert.match(logged.headers.get('set-cookie'),/HttpOnly/);
      assert.equal((await request('/foundation/auth/platform/login','POST',{email:'owner@example.invalid',password,nativeOrganisationId:'7'})).status,404);
    });
    await t.test('platform identity activation versions and role demotion affect existing sessions',async()=>{
      assert.equal((await request(platformPath+'/11','PATCH',{active:false,version:1})).status,200);
      assert.equal((await request(platformIdentity)).status,404);
      assert.equal((await request(platformPath+'/11','PATCH',{active:true,version:1})).status,409);
      assert.equal((await request(platformPath+'/11','PATCH',{active:true,version:2})).status,200);
      assert.equal((await request(platformIdentity)).status,200);
      await pg.query('UPDATE users SET is_superadmin=false WHERE id=$1',[owner]);
      assert.equal((await request(platformIdentity)).status,403);
      assert.equal((await request(platformPath+'/11','PATCH',{active:true,version:3})).status,403);
      await pg.query('UPDATE users SET is_superadmin=true WHERE id=$1',[owner]);
      assert.equal((await request(platformIdentity)).status,200);
    });
    await t.test('concurrent platform and tenant mapping attempts cannot share a native realm',async()=>{
      const results=await Promise.all([
        request(linkPath,'POST',{nativeOrganisationId:'20',organisationId:orgB}),
        request('/platform/foundation/platforms/20/staff','POST',{nativeUserId:'50',userId:owner}),
      ]);
      assert.deepEqual(results.map(r=>r.status).sort(),[201,409]);
      const count=(await pg.query('SELECT (SELECT count(*) FROM foundation_organisations WHERE native_id=20)+(SELECT count(*) FROM foundation_platform_staff WHERE native_organisation_id=20) AS n')).rows[0].n;
      assert.equal(Number(count),1);
    });
    await t.test('learner links are immutable and reject tenant authority or foreign records',async()=>{
      const path=linkPath+'/7/learners';
      assert.equal((await request(path,'POST',{nativeStudentId:'21',learnerId:learnerA},'staff')).status,403);
      assert.equal((await request(path,'POST',{nativeStudentId:'21',learnerId:learnerB})).status,404);
      assert.equal((await request(path,'POST',{nativeStudentId:'21',learnerId:learnerA})).status,201);
      const initial=await (await request(path,'GET')).json();
      const auditBefore=Number((await pg.query("SELECT count(*) AS n FROM audit_events WHERE action='foundation.learner_linked'")).rows[0].n);
      const retry=await request(path,'POST',{nativeStudentId:'21',learnerId:learnerA});
      assert.equal(retry.status,201);assert.deepEqual(await retry.json(),initial[0]);
      const concurrent=await Promise.all([request(path,'POST',{nativeStudentId:'21',learnerId:learnerA}),request(path,'POST',{nativeStudentId:'21',learnerId:learnerA})]);
      for(const response of concurrent){assert.equal(response.status,201);assert.deepEqual(await response.json(),initial[0]);}
      assert.equal(Number((await pg.query("SELECT count(*) AS n FROM audit_events WHERE action='foundation.learner_linked'")).rows[0].n),auditBefore);
      assert.equal((await request(path,'POST',{nativeStudentId:'22',learnerId:learnerA})).status,409);
      assert.equal((await request(path,'GET')).status,200);
      await assert.rejects(pg.query('INSERT INTO foundation_learners(native_organisation_id,native_student_id,organisation_id,learner_id) VALUES(7,99,$1,$2)',[orgB,learnerB]));
    });
    await t.test('native learner resolution requires exact mapped staff, current permission and location scope',async()=>{
      const path='/foundation/organisations/7/staff/9/students/21/learner-identity';
      const initial=await request(path,'GET',undefined,'staff');assert.equal(initial.status,200,await initial.clone().text());
      assert.deepEqual(await initial.json(),{nativeOrganisationId:'7',nativeUserId:'9',nativeStudentId:'21',organisationId:orgA,learnerId:learnerA,version:1});
      assert.equal((await request(path,'GET')).status,404);
      assert.equal((await request(path,'GET',undefined,'outsider')).status,404);
      await pg.query("UPDATE memberships SET scope_type='groups',scope_ids=$2 WHERE user_id=$1",[staff,[randomUUID()]]);
      assert.equal((await request(path,'GET',undefined,'staff')).status,404);
      await pg.query("UPDATE memberships SET scope_ids=$2 WHERE user_id=$1",[staff,[groupA]]);
      assert.equal((await request(path,'GET',undefined,'staff')).status,200);
      await pg.query('UPDATE learners SET archived=true WHERE id=$1',[learnerA]);
      assert.equal((await request(path,'GET',undefined,'staff')).status,404);
      assert.equal((await request(linkPath+'/7/learners','POST',{nativeStudentId:'21',learnerId:learnerA})).status,404);
      await pg.query('UPDATE learners SET archived=false WHERE id=$1',[learnerA]);
      await pg.query('UPDATE centres SET archived=true WHERE id=$1',[centreA]);
      assert.equal((await request(path,'GET',undefined,'staff')).status,404);
      await pg.query('UPDATE centres SET archived=false WHERE id=$1',[centreA]);
      await pg.query("UPDATE access_roles SET permissions=array_remove(permissions,'learners.view') WHERE organisation_id=$1 AND protected",[orgA]);
      assert.equal((await request(path,'GET',undefined,'staff')).status,403);
      await pg.query("UPDATE access_roles SET permissions=array_append(permissions,'learners.view') WHERE organisation_id=$1 AND protected",[orgA]);
      await pg.query("UPDATE memberships SET scope_type='organisation',scope_ids='{}' WHERE user_id=$1",[staff]);
    });
    await t.test('learner revocation is immediate and reactivation requires an active canonical learner',async()=>{
      const link=linkPath+'/7/learners/21',path='/foundation/organisations/7/staff/9/students/21/learner-identity';
      assert.equal((await request(link,'PATCH',{active:false,version:1})).status,200);
      assert.equal((await request(path,'GET',undefined,'staff')).status,404);
      assert.equal((await request(linkPath+'/7/learners','POST',{nativeStudentId:'21',learnerId:learnerA})).status,409);
      assert.equal((await request(link,'PATCH',{active:true,version:1})).status,409);
      await pg.query('UPDATE learners SET archived=true WHERE id=$1',[learnerA]);
      assert.equal((await request(link,'PATCH',{active:true,version:2})).status,404);
      await pg.query('UPDATE learners SET archived=false WHERE id=$1',[learnerA]);
      assert.equal((await request(link,'PATCH',{active:true,version:2})).status,200);
      assert.equal((await request(path,'GET',undefined,'staff')).status,200);
    });
    await t.test('signed acknowledgement preserves immutable identities and rejects stale or revoked delivery',async()=>{
      const {publicKey,privateKey}=generateKeyPairSync('rsa',{modulusLength:2048});
      const pem=publicKey.export({type:'spki',format:'pem'}).toString();
      await assert.rejects(registerNativeStudentKey(adapter,staff,pem));
      const key=await registerNativeStudentKey(adapter,owner,pem);
      assert.equal(key.created,true);
      assert.equal((await registerNativeStudentKey(adapter,owner,pem)).created,false);
      const learner=randomUUID();
      await pg.query('INSERT INTO learners(id,organisation_id,group_id,code,name) VALUES($1,$2,$3,$4,$5)',[learner,orgA,groupA,'SIGNED-SYNTHETIC','Synthetic delivery learner']);
      const path=`/foundation/organisations/7/staff/9/student-deliveries/${learner}/acknowledge`;
      const statusPath=path.replace('/acknowledge','/status');
      assert.equal((await request(statusPath,'GET',undefined,'outsider')).status,404);
      const pendingStatus=await request(statusPath,'GET',undefined,'staff');
      assert.equal(pendingStatus.headers.get('cache-control'),'no-store');
      assert.equal((await pendingStatus.json()).state,'pending');
      const receipt=(revision=1,extra={})=>{const bytes=Buffer.from(JSON.stringify({issuer:'tech4learn-native-student-v1',nativeOrganisationId:'7',nativeUserId:'9',nativeStudentId:'99',learnerId:learner,revision,issuedAt:Math.floor(Date.now()/1000),...extra}));return {keyId:key.keyId,receipt:bytes.toString('base64url'),signature:sign('sha256',bytes,privateKey).toString('base64url')};};
      assert.equal((await request(path,'POST',{nativeStudentId:'99'},'staff')).status,400);
      assert.equal((await request(path,'POST',receipt(1,{nativeUserId:'10'}),'staff')).status,403);
      assert.equal((await request(path,'POST',receipt(),'outsider')).status,404);
      const first=await request(path,'POST',receipt(),'staff');assert.equal(first.status,200,await first.clone().text());
      assert.equal((await request(path,'POST',receipt(),'staff')).status,200);
      assert.deepEqual(await (await request(statusPath,'GET',undefined,'staff')).json(),{learnerId:learner,revision:1,state:'delivered'});
      assert.equal((await pg.query("SELECT count(*)::int AS n FROM audit_events WHERE action='foundation.student_delivered'")).rows[0].n,1);
      await pg.query('UPDATE learners SET age=9,version=version+1 WHERE id=$1',[learner]);
      assert.equal((await (await request(statusPath,'GET',undefined,'staff')).json()).state,'pending');
      assert.equal((await request(path,'POST',receipt(),'staff')).status,409);
      await pg.query("UPDATE foundation_learners SET active=false WHERE native_organisation_id=7 AND native_student_id=99");
      assert.equal((await (await request(statusPath,'GET',undefined,'staff')).json()).state,'review_required');
      assert.equal((await request(path,'POST',receipt(2),'staff')).status,409);
      assert.equal((await pg.query('SELECT delivered_revision::int AS revision FROM foundation_student_deliveries WHERE learner_id=$1',[learner])).rows[0].revision,1);
      await pg.query('UPDATE foundation_native_signers SET active=false WHERE key_id=$1',[key.keyId]);
      await assert.rejects(registerNativeStudentKey(adapter,owner,pem));
      assert.equal((await request(path,'POST',receipt(2),'staff')).status,403);
    });
    await t.test('mapping versions, module switches, membership and scope changes take effect on existing sessions', async()=>{
      assert.equal((await request(staffPath+'/9','PATCH',{active:false,version:1})).status,200);
      assert.equal((await request(context,'GET',undefined,'staff')).status,404);
      assert.equal((await request(staffPath+'/9','PATCH',{active:true,version:1})).status,409);
      assert.equal((await request(staffPath+'/9','PATCH',{active:true,version:2})).status,200);
      await pg.query("UPDATE memberships SET scope_type='centres',scope_ids=$2 WHERE user_id=$1",[staff,[randomUUID()]]);
      const scope=await (await request(context,'GET',undefined,'staff')).json(); assert.equal(scope.scope.type,'centres'); assert.equal(scope.scope.ids.length,1);
      await pg.query("UPDATE memberships SET status='suspended' WHERE user_id=$1",[staff]);
      assert.equal((await request(context,'GET',undefined,'staff')).status,404);
      await pg.query("UPDATE memberships SET status='active' WHERE user_id=$1",[staff]);
      await pg.query("UPDATE organisation_settings SET enabled_modules='{\"attendance\":false}' WHERE organisation_id=$1",[orgA]);
      assert.equal((await request(context,'GET',undefined,'staff')).status,403);
      await pg.query("UPDATE organisation_settings SET enabled_modules='{\"attendance\":true}' WHERE organisation_id=$1",[orgA]);
      assert.equal((await request(linkPath+'/7','PATCH',{active:false,version:1})).status,200);
      assert.equal((await request(context,'GET',undefined,'staff')).status,404);
      assert.equal((await request(linkPath+'/7','PATCH',{active:true,version:2})).status,200);
      await pg.query('UPDATE users SET is_superadmin=false WHERE id=$1',[owner]);
      assert.equal((await request(linkPath,'GET')).status,403);
      await pg.query('DELETE FROM sessions WHERE user_id=$1',[staff]);
      assert.equal((await request(context,'GET',undefined,'staff')).status,401);
    });
    const audit=(await pg.query("SELECT action,details FROM audit_events WHERE action LIKE 'foundation.%'")).rows;
    assert.ok(audit.length>=6); assert.ok(!JSON.stringify(audit).includes(tokens.owner));
  } finally {await app.close();await pg.close();}
});
