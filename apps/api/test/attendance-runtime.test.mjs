import {test} from 'node:test';
import assert from 'node:assert/strict';
import {mkdtempSync,writeFileSync,rmSync} from 'node:fs';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {createApp} from '../dist/bootstrap.js';
import {apiRuntimeMode} from '../dist/runtime-mode.js';

test('attendance runtime retains protected supporting APIs and excludes legacy exam routes and shell',async()=>{
 const before=process.env.TECH4LEARN_API_MODE,dir=mkdtempSync(join(tmpdir(),'t4l-runtime-'));
 let app;
 try{
  process.env.TECH4LEARN_API_MODE='attendance';
  writeFileSync(join(dir,'index.html'),'Synthetic retired shell');
  const db={query:async()=>({rows:[]}),onModuleDestroy:async()=>{}};
  app=await createApp(dir,db);await app.listen(0,'127.0.0.1');const base=await app.getUrl();
  assert.equal((await fetch(base+'/')).status,404);
  assert.equal((await fetch(base+'/api/v1/health')).status,200);
  assert.equal((await fetch(base+'/api/v1/foundation/platforms/1/staff/1/identity')).status,401);
  assert.equal((await fetch(base+'/api/v1/organisations/11111111-1111-4111-8111-111111111111/attendance/sessions')).status,401);
  assert.equal((await fetch(base+'/api/v1/platform/examelite')).status,404);
  assert.equal((await fetch(base+'/api/v1/organisations/11111111-1111-4111-8111-111111111111/examelite/status')).status,404);
  assert.equal((await fetch(base+'/api/v1/organisations/11111111-1111-4111-8111-111111111111/student-exam/me')).status,404);
 }finally{await app?.close();rmSync(dir,{recursive:true,force:true});if(before===undefined)delete process.env.TECH4LEARN_API_MODE;else process.env.TECH4LEARN_API_MODE=before;}
});
test('unknown runtime modes fail closed',()=>{
 assert.equal(apiRuntimeMode('legacy'),'legacy');assert.equal(apiRuntimeMode('attendance'),'attendance');assert.throws(()=>apiRuntimeMode('attendence'));
});
