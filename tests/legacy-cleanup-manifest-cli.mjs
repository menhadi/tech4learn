import {test} from 'node:test';
import assert from 'node:assert/strict';
import {spawnSync} from 'node:child_process';
test('manifest CLI refuses invalid inputs without exposing their contents or touching a database',()=>{
 const secretMarker='synthetic-private-input-marker';
 const result=spawnSync(process.execPath,['deploy/virtualmin/prepare-legacy-cleanup-manifest.mjs',secretMarker,'unexpected-argument'],{encoding:'utf8'});
 assert.equal(result.status,1);
 assert.match(result.stderr,/No deletion attempted/);
 assert.equal((result.stdout+result.stderr).includes(secretMarker),false);
});

test('executor refuses missing explicit execution flag without echoing private input',()=>{
 const marker='synthetic-private-execution-marker';
 const result=spawnSync(process.execPath,['deploy/virtualmin/execute-legacy-cleanup.mjs',marker],{encoding:'utf8'});
 assert.equal(result.status,1);assert.match(result.stderr,/blocked or rolled back/);
 assert.equal((result.stdout+result.stderr).includes(marker),false);
});
