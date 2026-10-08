// Local-only cross-runtime check. All records/tokens are synthetic and ephemeral.
import { PGlite } from '@electric-sql/pglite';
import { spawn } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import { createApp } from '../apps/api/dist/bootstrap.js';
import { migration } from '../apps/api/dist/schema.js';
import { accessMigration } from '../apps/api/dist/migration-access.js';
import { learnerMigration } from '../apps/api/dist/migration-learners.js';
import { configurationMigration } from '../apps/api/dist/migration-configuration.js';
import { attendanceMigration } from '../apps/api/dist/migration-attendance.js';
import { academicMigration } from '../apps/api/dist/migration-academic.js';
import { visionMigration } from '../apps/api/dist/migration-vision.js';
import { photoMigration } from '../apps/api/dist/migration-photos.js';
import { bulkAttendanceMigration } from '../apps/api/dist/migration-bulk-attendance.js';
import { faceControlMigration } from '../apps/api/dist/migration-face-control.js';
import { photoNamesMigration } from '../apps/api/dist/migration-photo-names.js';
import { attendanceTestingMigration } from '../apps/api/dist/migration-attendance-testing.js';
import { foundationMigration } from '../apps/api/dist/migration-foundation.js';
import { foundationLearnerMigration } from '../apps/api/dist/migration-foundation-learners.js';
import { foundationPlatformMigration } from '../apps/api/dist/migration-foundation-platform.js';
import { foundationProvisioningMigration } from '../apps/api/dist/migration-foundation-provisioning.js';
import { provisionAttendanceOrganisation } from '../apps/api/dist/foundation-organisation-provision.js';
import { provisionAttendanceAdministrator } from '../apps/api/dist/foundation-staff-provision.js';
import { RecordsService } from '../apps/api/dist/records.service.js';
import { AcademicService } from '../apps/api/dist/academic.service.js';
import { AccessService } from '../apps/api/dist/access.service.js';
import { LearnersService } from '../apps/api/dist/learners.service.js';
import { FoundationService } from '../apps/api/dist/foundation.service.js';
import { digest,hashPassword } from '../apps/api/dist/security.js';
if (!process.argv[2]) throw new Error('Provide the prepared local foundation dependency directory');
process.env.NODE_ENV='test';
// Exercise the replacement attendance support runtime, including retired-route removal.
process.env.TECH4LEARN_API_MODE='attendance';
process.env.ADMIN_ORIGIN='https://one.example.invalid';
const pg=new PGlite();
let app;
try {
  await pg.exec(migration);
  const foreign='22222222-2222-4222-8222-222222222222', platformUser=randomUUID();
  await pg.query('INSERT INTO organisations(id,name,slug) VALUES($1,$2,$2)',[foreign,'synthetic-foreign']);
  for(const sql of [accessMigration,learnerMigration,configurationMigration,attendanceMigration,visionMigration,academicMigration,photoMigration,bulkAttendanceMigration,faceControlMigration,photoNamesMigration,attendanceTestingMigration,foundationMigration,foundationLearnerMigration,foundationPlatformMigration,foundationProvisioningMigration]) await pg.exec(sql);
  const adapter={query:(q,p)=>pg.query(q,p),transaction:fn=>pg.transaction(sql=>fn({query:(q,p)=>sql.query(q,p)})),onModuleDestroy:async()=>{}};
  await pg.query('INSERT INTO users(id,email,name,password_hash,is_superadmin) VALUES($1,$2,$3,$4,true)',[platformUser,'synthetic-platform@example.invalid','Synthetic platform administrator',await hashPassword('long synthetic foundation password')]);
  const companion=await provisionAttendanceOrganisation(adapter,platformUser,'2','Synthetic fresh native organisation');
  const org=companion.organisationId;
  const staff=await provisionAttendanceAdministrator(adapter,platformUser,'2','2','synthetic@example.invalid','Synthetic staff','long synthetic foundation password');
  const actor=staff.userId;
  const retryOrg=await provisionAttendanceOrganisation(adapter,platformUser,'2','Synthetic fresh native organisation');
  const retryStaff=await provisionAttendanceAdministrator(adapter,platformUser,'2','2','synthetic@example.invalid','Synthetic staff','different synthetic retry password');
  if(!companion.created || !staff.created || retryOrg.created || retryStaff.created || retryOrg.organisationId!==org || retryStaff.userId!==actor)throw new Error('Fresh onboarding retry changed identity');
  if(Number((await pg.query("SELECT count(*) AS n FROM audit_events WHERE action IN ('foundation.organisation_provisioned','foundation.staff_provisioned')")).rows[0].n)!==2)throw new Error('Provisioning retry duplicated audit events');
  await pg.query("INSERT INTO sessions(token_hash,user_id,expires_at) VALUES($1,$2,now()+interval '1 hour')",[digest('d'.repeat(64)),actor]);
  await pg.query("INSERT INTO organisation_domains(organisation_id,hostname,challenge,verified_at,active) VALUES($1,'two.example.invalid','synthetic-challenge',now(),true)",[org]);
  for (const [organisation,label] of [[org,'Synthetic authorised section'],[foreign,'Synthetic foreign section']]) {
    const centre=randomUUID(),group=randomUUID();
    await pg.query('INSERT INTO centres(id,organisation_id,name) VALUES($1,$2,$3)',[centre,organisation,'Synthetic centre']);
    await pg.query('INSERT INTO learning_groups(id,organisation_id,centre_id,name) VALUES($1,$2,$3,$4)',[group,organisation,centre,label]);
    await pg.query("INSERT INTO attendance_sessions(id,organisation_id,centre_id,group_id,attendance_date,status,snapshot) VALUES($1,$2,$3,$4,'2026-10-07','pending',$5)",[randomUUID(),organisation,centre,group,JSON.stringify({group_name:label,centre_name:'Synthetic centre'})]);
  }
  const access=new AccessService(adapter), learners=new LearnersService(adapter,access), links=new FoundationService(adapter,access,learners);
  const records=new RecordsService(adapter,access), academic=new AcademicService(adapter,access,records);
  const staffAccount=(await pg.query('SELECT id,email,name,is_superadmin FROM users WHERE id=$1',[actor])).rows[0];
  const platformAccount=(await pg.query('SELECT id,email,name,is_superadmin FROM users WHERE id=$1',[platformUser])).rows[0];
  const centre=await records.saveCentre(staffAccount,org,{name:'Synthetic capture centre',address:'Synthetic address',latitude:0,longitude:0,radius:100});
  if(centre.location_approved)throw new Error('Centre coordinates were automatically approved');
  await records.centreAction(staffAccount,org,centre.id,'approve');
  const year=await academic.createYear(staffAccount,org,{name:'Synthetic year',starts_on:'2026-04-01',ends_on:'2027-03-31'});
  const academicClass=await academic.createClass(staffAccount,org,{name:'Synthetic class',centre_id:centre.id,academic_year_id:year.id});
  const section=await records.saveGroup(staffAccount,org,{name:'Synthetic capture section',centre_id:centre.id,class_id:academicClass.id});
  const captureCentre=centre.id,captureGroup=section.id;
  const learner=(await learners.save(staffAccount,org,{group_id:captureGroup,code:'SYNTHETIC-01',name:'Synthetic learner'})).id;
  await links.linkLearner(platformAccount,'2',{nativeStudentId:'10',learnerId:learner});
  if(Number((await pg.query('SELECT count(*) AS n FROM learner_enrolments WHERE organisation_id=$1 AND learner_id=$2 AND group_id=$3 AND ended_at IS NULL',[org,learner,captureGroup])).rows[0].n)!==1)throw new Error('Learner creation did not establish current enrolment');
  await pg.query('INSERT INTO foundation_platform_staff(native_organisation_id,native_user_id,user_id) VALUES(1,1,$1)',[platformUser]);
  app=await createApp(undefined,adapter); await app.listen(0,'127.0.0.1');
  const api=`${await app.getUrl()}/api/v1`;
  const onboardingUrl=api+'/platform/foundation/attendance-onboarding';
  const onboardingBody={nativeOrganisationId:'321',name:'Synthetic HTTP onboarding'};
  const sendOnboarding=(body,cookie)=>fetch(onboardingUrl,{method:'POST',headers:{'content-type':'application/json',origin:process.env.ADMIN_ORIGIN,'x-tech4learn-request':'1',...(cookie?{cookie}:{})},body:JSON.stringify(body)});
  if(![401,403].includes((await sendOnboarding(onboardingBody)).status))throw new Error('Anonymous onboarding was not denied');
  const login=await fetch(api+'/auth/login',{method:'POST',headers:{'content-type':'application/json',origin:process.env.ADMIN_ORIGIN,'x-tech4learn-request':'1'},body:JSON.stringify({email:'synthetic-platform@example.invalid',password:'long synthetic foundation password'})});
  if(!login.ok)throw new Error('Synthetic platform onboarding login failed');
  const platformCookie=login.headers.get('set-cookie')?.split(';')[0];
  if(!platformCookie)throw new Error('Synthetic onboarding session missing');
  const firstOnboarding=await sendOnboarding(onboardingBody,platformCookie);
  if(!firstOnboarding.ok)throw new Error('Authenticated onboarding failed');
  const firstOnboardingBody=await firstOnboarding.json();
  const repeatedOnboarding=await sendOnboarding(onboardingBody,platformCookie);
  const repeatedOnboardingBody=await repeatedOnboarding.json();
  if(!repeatedOnboarding.ok||!firstOnboardingBody.created||repeatedOnboardingBody.created||firstOnboardingBody.organisationId!==repeatedOnboardingBody.organisationId)throw new Error('HTTP onboarding retry changed identity');
  if((await sendOnboarding({nativeOrganisationId:'1',name:'Synthetic platform'},platformCookie)).ok)throw new Error('Platform realm onboarding was accepted');
  if((await sendOnboarding({nativeOrganisationId:'2',name:'Conflicting synthetic name'},platformCookie)).ok)throw new Error('Conflicting onboarding mapping was accepted');
  await pg.query('UPDATE users SET is_superadmin=false WHERE id=$1',[platformUser]);
  if((await sendOnboarding({nativeOrganisationId:'322',name:'Synthetic revoked operator'},platformCookie)).status!==403)throw new Error('Revoked platform authority granted onboarding');
  await pg.query('UPDATE users SET is_superadmin=true WHERE id=$1',[platformUser]);
  await fetch(api+'/auth/logout',{method:'POST',headers:{'content-type':'application/json',origin:process.env.ADMIN_ORIGIN,'x-tech4learn-request':'1',cookie:platformCookie},body:'{}'});
  console.log('PASS: authenticated HTTP fresh companion provisioning, exact retry, realm/conflict denial and stored authority revocation.');
  await new Promise((resolve,reject)=>{
    const child=spawn('php',['tests/foundation-attendance-connected.php',process.argv[2]],{
      cwd:process.cwd(),env:{...process.env,FOUNDATION_TEST_API_URL:api,FOUNDATION_TEST_ORGANISATION:org,FOUNDATION_TEST_GROUP:captureGroup,FOUNDATION_TEST_LEARNER:learner},stdio:['ignore','pipe','pipe'],timeout:60000
    });
    child.on('error',reject);
    child.stdout.on('data',data=>process.stdout.write(data)); child.stderr.on('data',data=>process.stderr.write(data));
    child.on('exit',code=>code===0?resolve():reject(new Error('Connected PHP fixture failed')));
  });
  await new Promise((resolve,reject)=>{
    const child=spawn('php',['tests/foundation-platform-connected.php',process.argv[2]],{
      cwd:process.cwd(),env:{...process.env,FOUNDATION_TEST_API_URL:api,FOUNDATION_TEST_PLATFORM_USER:platformUser},stdio:['ignore','pipe','pipe'],timeout:60000
    });
    child.on('error',reject);
    child.stdout.on('data',data=>process.stdout.write(data));child.stderr.on('data',data=>process.stderr.write(data));
    child.on('exit',code=>code===0?resolve():reject(new Error('Connected platform PHP fixture failed')));
  });
  {
    const state=(await pg.query('SELECT active,version FROM foundation_platform_staff WHERE user_id=$1',[platformUser])).rows[0];
    if(state.active!==false||state.version!==2)throw new Error('Connected platform revocation did not persist');
    if(Number((await pg.query('SELECT count(*) AS n FROM sessions WHERE user_id=$1',[platformUser])).rows[0].n)!==0)throw new Error('Connected platform logout left a session');
  }
} finally {if(app) await app.close();await pg.close();}
