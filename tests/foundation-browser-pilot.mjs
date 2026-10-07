// Local-only cross-runtime check. All records/tokens are synthetic and ephemeral.
import { PGlite } from '@electric-sql/pglite';
import { readFileSync } from 'node:fs';
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
import assert from 'node:assert/strict';
import { hashPassword } from '../apps/api/dist/security.js';
const credentials=JSON.parse(readFileSync(new URL('../.local/tech4learn-foundation/local-pilot-credentials.json',import.meta.url),'utf8'));
if(process.env.NODE_ENV==='production') throw new Error('Local synthetic pilot only');
process.env.NODE_ENV='test';
// Exercise the replacement attendance support runtime, including retired-route removal.
process.env.TECH4LEARN_API_MODE='attendance';
process.env.ADMIN_ORIGIN='http://127.0.0.1:8001';
const pg=new PGlite();
let app;
try {
  await pg.exec(migration);
  const org='11111111-1111-4111-8111-111111111111', foreign='22222222-2222-4222-8222-222222222222', actor=randomUUID();
  for (const [id,slug] of [[org,'synthetic-own'],[foreign,'synthetic-foreign']])
    await pg.query('INSERT INTO organisations(id,name,slug) VALUES($1,$2,$2)',[id,slug]);
  for(const sql of [accessMigration,learnerMigration,configurationMigration,attendanceMigration,visionMigration,academicMigration,photoMigration,bulkAttendanceMigration,faceControlMigration,photoNamesMigration,attendanceTestingMigration,foundationMigration,foundationLearnerMigration,foundationPlatformMigration]) await pg.exec(sql);
  await pg.query('INSERT INTO users(id,email,name,password_hash) VALUES($1,$2,$3,$4)',[actor,'synthetic@example.invalid','Synthetic staff',await hashPassword(credentials.password)]);
  await pg.query("INSERT INTO memberships(user_id,organisation_id,role,role_id) SELECT $1,$2,'organisation_admin',id FROM access_roles WHERE organisation_id=$2 AND protected",[actor,org]);
  await pg.query("INSERT INTO organisation_settings(organisation_id,enabled_modules) VALUES($1,'{\"attendance\":true}')",[org]);
  await pg.query("INSERT INTO organisation_domains(organisation_id,hostname,challenge,verified_at,active) VALUES($1,'two.example.invalid','synthetic-challenge',now(),true)",[org]);
  await pg.query('INSERT INTO foundation_organisations(native_id,organisation_id) VALUES(2,$1)',[org]);
  await pg.query('INSERT INTO foundation_staff(native_organisation_id,native_user_id,user_id) VALUES(2,2,$1)',[actor]);
  const platformUser=randomUUID();
  await pg.query('INSERT INTO users(id,email,name,password_hash,is_superadmin) VALUES($1,$2,$3,$4,true)',[platformUser,'synthetic-platform@example.invalid','Synthetic platform administrator',await hashPassword(credentials.password)]);
  await pg.query('INSERT INTO foundation_platform_staff(native_organisation_id,native_user_id,user_id) VALUES(1,1,$1)',[platformUser]);
  for (const [organisation,label] of [[org,'Synthetic authorised section'],[foreign,'Synthetic foreign section']]) {
    const centre=randomUUID(),group=randomUUID();
    await pg.query('INSERT INTO centres(id,organisation_id,name) VALUES($1,$2,$3)',[centre,organisation,'Synthetic centre']);
    await pg.query('INSERT INTO learning_groups(id,organisation_id,centre_id,name) VALUES($1,$2,$3,$4)',[group,organisation,centre,label]);
    await pg.query("INSERT INTO attendance_sessions(id,organisation_id,centre_id,group_id,attendance_date,status,snapshot) VALUES($1,$2,$3,$4,'2026-10-07','pending',$5)",[randomUUID(),organisation,centre,group,JSON.stringify({group_name:label,centre_name:'Synthetic centre'})]);
  }
  const captureCentre=randomUUID(),captureGroup=randomUUID(),learner=randomUUID();
  await pg.query("INSERT INTO centres(id,organisation_id,name,latitude,longitude,location_approved) VALUES($1,$2,'Synthetic capture centre',0,0,true)",[captureCentre,org]);
  await pg.query("INSERT INTO learning_groups(id,organisation_id,centre_id,name) VALUES($1,$2,$3,'Synthetic capture section')",[captureGroup,org,captureCentre]);
  await pg.query("INSERT INTO learners(id,organisation_id,group_id,code,name) VALUES($1,$2,$3,'SYNTHETIC-01','Synthetic learner')",[learner,org,captureGroup]);
  const adapter={query:(q,p)=>pg.query(q,p),transaction:fn=>pg.transaction(sql=>fn({query:(q,p)=>sql.query(q,p)})),onModuleDestroy:async()=>{}};
  app=await createApp(undefined,adapter); await app.listen(process.argv.includes('--check') ? 0 : 8010,'127.0.0.1');
  const api=`${await app.getUrl()}/api/v1`;
  if (process.argv.includes('--check')) {
    const login=await fetch(api+'/foundation/auth/platform/login',{method:'POST',headers:{Origin:process.env.ADMIN_ORIGIN,'Content-Type':'application/json','X-Tech4Learn-Request':'1'},body:JSON.stringify({email:'synthetic-platform@example.invalid',password:credentials.password,nativeOrganisationId:'1'})});
    assert.equal(login.status,200);
    const identity=await login.json();
    assert.equal(identity.realm,'platform');assert.equal(identity.nativeOrganisationId,'1');assert.equal(identity.nativeUserId,'1');
    const rootCookie=login.headers.get('set-cookie').split(';')[0];
    const globalAttendance=await fetch(api+'/foundation/organisations/1/staff/1/attendance-context',{headers:{Cookie:rootCookie}});
    assert.equal(globalAttendance.status,404);
    const staffLogin=await fetch(api+'/foundation/auth/login',{method:'POST',headers:{Origin:process.env.ADMIN_ORIGIN,'Content-Type':'application/json','X-Tech4Learn-Request':'1'},body:JSON.stringify({email:'synthetic@example.invalid',password:credentials.password,nativeOrganisationId:'2'})});
    assert.equal(staffLogin.status,200);
    assert.equal((await staffLogin.json()).nativeUserId,'2');
    const staffCookie=staffLogin.headers.get('set-cookie').split(';')[0];
    const staffAttendance=await fetch(api+'/foundation/organisations/2/staff/2/attendance-context',{headers:{Cookie:staffCookie,Host:'two.example.invalid'}});
    assert.equal(staffAttendance.status,200);
    assert.equal((await pg.query('SELECT count(*)::integer AS total FROM foundation_organisations WHERE native_id=1')).rows[0].total,0);
    assert.equal((await pg.query('SELECT native_organisation_id::text,native_user_id::text FROM foundation_staff')).rows[0].native_organisation_id,'2');
    await app.close();await pg.close();
    console.log('PASS: browser pilot has separate platform and attendance identities and working platform password login.');
  } else console.log('Local synthetic foundation API ready on 127.0.0.1:8010; platform and tenant staff have separate identities; passwords remain in ignored local credentials.');
} catch(error) { await app?.close();await pg.close();throw error; }
