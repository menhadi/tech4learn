import { test } from 'node:test';
import assert from 'node:assert/strict';
import { legacyRestoreTarget,administratorRestoreProof } from '../deploy/virtualmin/legacy-restore-target.mjs';

test('restore destination is fixed and preserves private connection settings',()=>{
  const url=legacyRestoreTarget('postgresql://tech4learn_app:synthetic@127.0.0.1:5432/tech4learn_app?sslmode=require');
  assert.equal(url.pathname,'/tech4learn_cleanup_restore');assert.equal(url.hostname,'127.0.0.1');
  assert.equal(url.searchParams.get('sslmode'),'require');
});
test('other databases, roles and protocols cannot select a restore destination',()=>{
  for(const connection of ['postgres://tech4learn_app:synthetic@localhost/examelite','postgres://root:synthetic@localhost/tech4learn_app','mysql://tech4learn_app:synthetic@localhost/tech4learn_app'])
    assert.throws(()=>legacyRestoreTarget(connection));
});
test('administrator restore proof detects identity or password changes and refuses ambiguous roots',()=>{
  const identity={id:'synthetic-id',email:'synthetic@example.invalid',name:'Synthetic',password_hash:'synthetic-hash'};
  const proof=administratorRestoreProof([identity]);
  assert.equal(administratorRestoreProof([{...identity}]),proof);
  for(const key of Object.keys(identity))assert.notEqual(administratorRestoreProof([{...identity,[key]:'changed-synthetic-value'}]),proof);
  assert.throws(()=>administratorRestoreProof([]));
  assert.throws(()=>administratorRestoreProof([identity,identity]));
  assert.throws(()=>administratorRestoreProof([{...identity,password_hash:''}]));
});
