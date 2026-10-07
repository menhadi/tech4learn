import { test } from 'node:test';
import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { PGlite } from '@electric-sql/pglite';
import { createApp } from '../dist/bootstrap.js';
import { migration } from '../dist/schema.js';
import { accessMigration } from '../dist/migration-access.js';
import { learnerMigration } from '../dist/migration-learners.js';
import { configurationMigration } from '../dist/migration-configuration.js';
import { foundationMigration } from '../dist/migration-foundation.js';
import { digest,hashPassword } from '../dist/security.js';

test('foundation identity links require explicit accounts, current authority and revocable attendance grants', async t => {
  const pg = new PGlite();
  const orgA = randomUUID(), orgB = randomUUID(), owner = randomUUID(), staff = randomUUID(), outsider = randomUUID();
  await pg.exec(migration);
  for (const [id,slug] of [[orgA,'synthetic-one'],[orgB,'synthetic-two']])
    await pg.query('INSERT INTO organisations(id,name,slug) VALUES($1,$2,$2)',[id,slug]);
  await pg.exec(accessMigration); await pg.exec(learnerMigration); await pg.exec(configurationMigration); await pg.exec(foundationMigration);
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
      const body=await res.json(); assert.equal(body.organisation.id,orgA); assert.ok(body.permissions.includes('attendance.view'));
      assert.equal(body.nativeUserId,'9'); assert.equal(body.scope.type,'organisation');
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
