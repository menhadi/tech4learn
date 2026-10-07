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
import { foundationMigration } from '../apps/api/dist/migration-foundation.js';
import { digest } from '../apps/api/dist/security.js';
if (!process.argv[2]) throw new Error('Provide the prepared local foundation dependency directory');
process.env.NODE_ENV='test';
process.env.ADMIN_ORIGIN='http://localhost:5173';
const pg=new PGlite();
let app;
try {
  await pg.exec(migration);
  const org='11111111-1111-4111-8111-111111111111', foreign='22222222-2222-4222-8222-222222222222', actor=randomUUID();
  for (const [id,slug] of [[org,'synthetic-own'],[foreign,'synthetic-foreign']])
    await pg.query('INSERT INTO organisations(id,name,slug) VALUES($1,$2,$2)',[id,slug]);
  for(const sql of [accessMigration,learnerMigration,configurationMigration,attendanceMigration,foundationMigration]) await pg.exec(sql);
  await pg.query('INSERT INTO users(id,email,name,password_hash) VALUES($1,$2,$3,$4)',[actor,'synthetic@example.invalid','Synthetic staff','unused synthetic hash']);
  await pg.query("INSERT INTO sessions(token_hash,user_id,expires_at) VALUES($1,$2,now()+interval '1 hour')",[digest('d'.repeat(64)),actor]);
  await pg.query("INSERT INTO memberships(user_id,organisation_id,role,role_id) SELECT $1,$2,'organisation_admin',id FROM access_roles WHERE organisation_id=$2 AND protected",[actor,org]);
  await pg.query("INSERT INTO organisation_settings(organisation_id,enabled_modules) VALUES($1,'{\"attendance\":true}')",[org]);
  await pg.query('INSERT INTO foundation_organisations(native_id,organisation_id) VALUES(2,$1)',[org]);
  await pg.query('INSERT INTO foundation_staff(native_organisation_id,native_user_id,user_id) VALUES(2,2,$1)',[actor]);
  for (const [organisation,label] of [[org,'Synthetic authorised section'],[foreign,'Synthetic foreign section']]) {
    const centre=randomUUID(),group=randomUUID();
    await pg.query('INSERT INTO centres(id,organisation_id,name) VALUES($1,$2,$3)',[centre,organisation,'Synthetic centre']);
    await pg.query('INSERT INTO learning_groups(id,organisation_id,centre_id,name) VALUES($1,$2,$3,$4)',[group,organisation,centre,label]);
    await pg.query("INSERT INTO attendance_sessions(id,organisation_id,centre_id,group_id,attendance_date,status,snapshot) VALUES($1,$2,$3,$4,'2026-10-07','pending',$5)",[randomUUID(),organisation,centre,group,JSON.stringify({group_name:label,centre_name:'Synthetic centre'})]);
  }
  const adapter={query:(q,p)=>pg.query(q,p),transaction:fn=>pg.transaction(sql=>fn({query:(q,p)=>sql.query(q,p)})),onModuleDestroy:async()=>{}};
  app=await createApp(undefined,adapter); await app.listen(0,'127.0.0.1');
  const api=`${await app.getUrl()}/api/v1`;
  await new Promise((resolve,reject)=>{
    const child=spawn('php',['tests/foundation-attendance-connected.php',process.argv[2]],{
      cwd:process.cwd(),env:{...process.env,FOUNDATION_TEST_API_URL:api},stdio:['ignore','pipe','pipe'],timeout:60000
    });
    child.on('error',reject);
    child.stdout.on('data',data=>process.stdout.write(data)); child.stderr.on('data',data=>process.stderr.write(data));
    child.on('exit',code=>code===0?resolve():reject(new Error('Connected PHP fixture failed')));
  });
} finally {if(app) await app.close();await pg.close();}
