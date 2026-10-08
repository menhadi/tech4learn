import test from 'node:test';
import assert from 'node:assert/strict';
import { parseRegistrationDraft } from '../dist/registration-draft.js';

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
