import test from 'node:test';
import assert from 'node:assert/strict';
import {randomUUID} from 'node:crypto';
import {PGlite} from '@electric-sql/pglite';
import {migration} from '../dist/schema.js';
import {accessMigration} from '../dist/migration-access.js';
import {registrationDocumentsMigration} from '../dist/migration-registration-documents.js';
import {RegistrationDocumentsService} from '../dist/registration-documents.service.js';

test('registration documents enforce section/contact authority, quota and post-call revocation without storing text',async()=>{
  const pg=new PGlite(), org=randomUUID(), foreign=randomUUID(), user={id:randomUUID()}, group=randomUUID(), other=randomUUID(), centre=randomUUID();
  const previousFetch=globalThis.fetch, key=process.env.T4L_OPENAI_API_KEY, model=process.env.T4L_OPENAI_VISION_MODEL;
  try {
    await pg.exec(migration);await pg.exec(accessMigration);await pg.exec(registrationDocumentsMigration);
    for(const id of [org,foreign])await pg.query('INSERT INTO organisations(id,name,slug) VALUES($1,$2,$2)',[id,id]);
    await pg.query("INSERT INTO users(id,email,name,password_hash) VALUES($1,'synthetic@example.invalid','Synthetic','unused')",[user.id]);
    for(const [o,g,c] of [[org,group,centre],[foreign,other,randomUUID()]]) {
      await pg.query("INSERT INTO centres(id,organisation_id,name) VALUES($1,$2,'Synthetic')",[c,o]);
      await pg.query("INSERT INTO learning_groups(id,organisation_id,centre_id,name) VALUES($1,$2,$3,'Synthetic')",[g,o,c]);
    }
    const db={query:(q,p)=>pg.query(q,p),transaction:fn=>pg.transaction(sql=>fn({query:(q,p)=>sql.query(q,p)}))};
    let contacts=true,revoked=false,calls=0,revokeDuringCall=false;
    const access={lock:async()=>{},audit:async()=>{},require:async(u,o,p)=>{
      if(o!==org||revoked||(p==='learners.contacts'&&!contacts))throw new Error('Denied');
      return {scope_type:'groups',scope_ids:[group]};
    }};
    const service=new RegistrationDocumentsService(db,access);
    process.env.T4L_OPENAI_API_KEY='synthetic';process.env.T4L_OPENAI_VISION_MODEL='synthetic';
    globalThis.fetch=async()=>{
      calls++;if(revokeDuringCall)revoked=true;
      return Response.json({output:[{content:[{text:JSON.stringify({fields:{code:'SYNTHETIC',name:'Synthetic',age:8,guardian_name:'Synthetic guardian',guardian_phone:''},warnings:[]})}]}]});
    };
    const document=Buffer.from([255,216,255,192,0,8,8,1,224,2,128,1,255,218,0,6,1,1,0,0,11,22,33,44,255,217]).toString('base64');
    const body={group_id:group,provider:'openai',document,attested:true};
    const readiness=await service.providers(user,org,group);
    assert.deepEqual(Object.keys(readiness[0]).sort(),['configured','id','label']);
    contacts=false;await assert.rejects(()=>service.providers(user,org,group));await assert.rejects(()=>service.extract(user,org,body));assert.equal(calls,0);
    contacts=true;await assert.rejects(()=>service.extract(user,org,{...body,group_id:other}));assert.equal(calls,0);
    await assert.rejects(()=>service.extract(user,org,{...body,student_id:randomUUID()}));assert.equal(calls,0);
    const draft=await service.extract(user,org,body);assert.equal(draft.requiresReview,true);assert.equal(calls,1);
    const stored=(await pg.query('SELECT * FROM registration_document_runs')).rows[0];
    assert.equal(stored.status,'completed');assert.equal(stored.document,undefined);assert.equal(stored.result,undefined);
    revokeDuringCall=true;await assert.rejects(()=>service.extract(user,org,body),e=>!e.message.includes('Synthetic guardian'));
    assert.equal(Number((await pg.query("SELECT count(*) n FROM registration_document_runs WHERE status='failed'")).rows[0].n),1);
    revoked=false;revokeDuringCall=false;
    for(let i=0;i<18;i++)await pg.query("INSERT INTO registration_document_runs(id,organisation_id,actor_id,group_id,provider,status) VALUES($1,$2,$3,$4,'openai','failed')",[randomUUID(),org,user.id,group]);
    await assert.rejects(()=>service.extract(user,org,body),e=>e.getStatus()===409);assert.equal(calls,2);
  } finally {
    globalThis.fetch=previousFetch;
    if(key===undefined)delete process.env.T4L_OPENAI_API_KEY;else process.env.T4L_OPENAI_API_KEY=key;
    if(model===undefined)delete process.env.T4L_OPENAI_VISION_MODEL;else process.env.T4L_OPENAI_VISION_MODEL=model;
    await pg.close();
  }
});
