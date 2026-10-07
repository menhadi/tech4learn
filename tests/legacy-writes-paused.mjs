import {test} from 'node:test';
import assert from 'node:assert/strict';
import {assertLegacyWritesPaused} from '../deploy/virtualmin/check-legacy-writes-paused.mjs';
const sql=count=>({query:async()=>({rows:[{total:count}]})});
test('cleanup requires explicitly inactive API and sole database client',async()=>{
 await assertLegacyWritesPaused(sql(0),()=>({status:3,stdout:'inactive\n'}));
 for(const state of [{status:0,stdout:'active'},{status:3,stdout:'failed'},{status:4,stdout:'unknown'},{status:null,stdout:'inactive',error:new Error('timeout')}])await assert.rejects(assertLegacyWritesPaused(sql(0),()=>state));
 await assert.rejects(assertLegacyWritesPaused(sql(1),()=>({status:3,stdout:'inactive'})),/Other database clients/);
});
