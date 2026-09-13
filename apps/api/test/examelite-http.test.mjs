import 'reflect-metadata';
import {test} from 'node:test';
import assert from 'node:assert/strict';
import {ExamEliteService} from '../dist/examelite.service.js';
import {PGlite} from '@electric-sql/pglite';
import {createApp} from '../dist/bootstrap.js';
import {migration} from '../dist/schema.js';
import {accessMigration} from '../dist/migration-access.js';
import {FaceJobsService} from '../dist/face-jobs.service.js';
import {digest} from '../dist/security.js';
import {randomUUID,randomBytes} from 'node:crypto';

test('ExamElite HTTP uses persisted membership, permission and session',async()=>{
  const pg=new PGlite();await pg.exec(migration);
  const own=randomUUID(),other=randomUUID(),user=randomUUID(),token=randomBytes(32).toString('hex');
  await pg.query("INSERT INTO organisations(id,name,slug) VALUES($1,'First','first'),($2,'Second','second')",[own,other]);
  await pg.exec(accessMigration);
  await pg.query("INSERT INTO users(id,email,name,password_hash) VALUES($1,'connector@example.test','Fixture','unused')",[user]);
  await pg.query("INSERT INTO memberships(user_id,organisation_id,role,role_id) SELECT $1,$2,'organisation_admin',id FROM access_roles WHERE organisation_id=$2 AND protected",[user,own]);
  await pg.query("INSERT INTO sessions(token_hash,user_id,expires_at) VALUES($1,$2,now()+interval '1 hour')",[digest(token),user]);
  const db={query:(q,p)=>pg.query(q,p),transaction:fn=>pg.transaction(tx=>fn({query:(q,p)=>tx.query(q,p)})),onModuleDestroy:async()=>{}};
  const app=await createApp(undefined,db);
  app.get(FaceJobsService).onModuleInit=()=>{};
  let calls=0;
  app.get(ExamEliteService).configuration=async()=>{calls++;return null};
  await app.listen(0,'127.0.0.1');
  const url=await app.getUrl();
  const read=(id,cookie=token)=>fetch(`${url}/api/v1/organisations/${id}/examelite/status`,{headers:{Cookie:`t4l_session=${cookie}`}});
  try {
    assert.equal((await read(own)).status,200);assert.equal(calls,1);
    assert.equal((await read(other)).status,404);
    assert.equal((await read(own,'invalid')).status,401);assert.equal(calls,1);
    await pg.query("UPDATE memberships SET status='suspended' WHERE user_id=$1",[user]);
    assert.equal((await read(own)).status,404);assert.equal(calls,1);
    await pg.query("UPDATE memberships SET status='active' WHERE user_id=$1",[user]);
    await pg.query("UPDATE access_roles SET permissions='{}' WHERE organisation_id=$1",[own]);
    assert.equal((await read(own)).status,403);assert.equal(calls,1);
  } finally {await app.close();await pg.close();}
});
