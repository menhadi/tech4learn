import test from 'node:test';
import assert from 'node:assert/strict';
import { parseRegistrationDraft } from '../dist/registration-draft.js';
import { runRegistrationVision, visionProviders } from '../dist/vision-providers.js';

const page = () => ({fields:{code:' DEMO-001 ',name:' Synthetic Student ',age:8,
  guardian_name:'',guardian_phone:''},warnings:[' Check the handwritten code. ']});

test('registration extraction is an editable draft and excludes authority and identity fields', () => {
  const input=page();
  Object.assign(input.fields,{organisation_id:'another-tenant',group_id:'another-section',
    id:'existing-student',password:'untrusted',consent:true,photo:'untrusted'});
  input.requiresReview=false;
  const result=parseRegistrationDraft('```json\n'+JSON.stringify(input)+'\n```');
  assert.deepEqual(result,{source:'registration_page',requiresReview:true,
    fields:{code:'DEMO-001',name:'Synthetic Student',age:8,guardian_name:'',guardian_phone:''},
    warnings:['Check the handwritten code.']});
});

test('unreadable or incomplete registration pages remain blank drafts', () => {
  const input=page();input.fields={code:'',name:'',age:null,guardian_name:'',guardian_phone:''};
  input.warnings=['Multiple students: use separate pages.'];
  assert.equal(parseRegistrationDraft(JSON.stringify(input)).fields.age,null);
  assert.equal(parseRegistrationDraft(JSON.stringify(input)).fields.name,'');
});

test('registration parser rejects malformed, oversized and invalid field values', () => {
  for (const age of ['8',-1,121,8.5,{},undefined]) {
    const input=page();input.fields.age=age;
    assert.throws(()=>parseRegistrationDraft(JSON.stringify(input)));
  }
  for (const input of [null,[],{}, {...page(),warnings:'not an array'},
    {...page(),fields:{...page().fields,name:'x'.repeat(121)}},
    {...page(),fields:{...page().fields,name:'a\u0000b'}}]) {
    assert.throws(()=>parseRegistrationDraft(JSON.stringify(input)));
  }
  assert.throws(()=>parseRegistrationDraft('not JSON'));
  assert.throws(()=>parseRegistrationDraft(' '.repeat(16001)));
});

test('registration extraction uses existing fixed provider transports and never accepts model authority', async () => {
  for (const provider of visionProviders) {
    const key=`T4L_${provider.prefix}_API_KEY`, model=`T4L_${provider.prefix}_VISION_MODEL`;
    const previousKey=process.env[key], previousModel=process.env[model];
    try {
      process.env[key]='synthetic-secret';process.env[model]='synthetic-model';
      const input=page();input.fields.organisation_id='untrusted';
      const text=JSON.stringify(input);
      const response=provider.id==='openai' ? {output:[{content:[{text}]}]} :
        provider.id==='claude' ? {content:[{type:'text',text}]} :
        provider.id==='gemini' ? {candidates:[{content:{parts:[{text}]}}]} :
        {choices:[{message:{content:text}}]};
      const draft=await runRegistrationVision(provider.id,Buffer.from('synthetic page'),async (url,options)=>{
        assert.equal(options.redirect,'error');
        assert.ok(options.body.includes('student registration page'));
        assert.ok(!options.body.includes('synthetic-secret'));
        if(provider.id==='openai')assert.equal(JSON.parse(options.body).store,false);
        return Response.json(response);
      });
      assert.equal(draft.result.requiresReview,true);
      assert.equal(draft.result.fields.organisation_id,undefined);
      await assert.rejects(()=>runRegistrationVision(provider.id,Buffer.alloc(0),()=>{throw new Error('must not send');}),e=>e.getStatus()===400);
      await assert.rejects(()=>runRegistrationVision(provider.id,Buffer.alloc(5*1024*1024+1),()=>{throw new Error('must not send');}),e=>e.getStatus()===400);
      await assert.rejects(()=>runRegistrationVision(provider.id,Buffer.from('synthetic'),async()=>new Response('secret',{status:401})),e=>e.getStatus()===503&&!e.message.includes('secret'));
    } finally {
      if(previousKey===undefined)delete process.env[key];else process.env[key]=previousKey;
      if(previousModel===undefined)delete process.env[model];else process.env[model]=previousModel;
    }
  }
});
