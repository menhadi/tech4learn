import {test} from 'node:test';
import assert from 'node:assert/strict';
import {verifiedCleanupBackup} from '../deploy/virtualmin/cleanup-backup-proof.mjs';
const now=Date.parse('2026-10-07T17:00:00.000Z');
const manifest={createdAt:'2026-10-07T16:40:00.000Z',bytes:1234,sha256:'a'.repeat(64),archiveListed:true,archiveDecoded:true,restoreTested:false,liveDatabaseChanged:false};
const receipt={verifiedAt:'2026-10-07T16:50:00.000Z',database:'tech4learn_cleanup_restore',archiveSha256:manifest.sha256,counts:{admins:1,organisations:4,learners:16,attendance_sessions:10},administratorPreserved:true,liveDatabaseChanged:false};
test('isolated restore proof binds the fresh exact archive and retained administrator',()=>{
 assert.equal(verifiedCleanupBackup(manifest,receipt,now).archiveSha256,manifest.sha256);
 assert.throws(()=>verifiedCleanupBackup(manifest,undefined,now));
 for(const changed of [{...receipt,archiveSha256:'b'.repeat(64)},{...receipt,database:'tech4learn_app'},{...receipt,administratorPreserved:false},{...receipt,liveDatabaseChanged:true},{...receipt,counts:{...receipt.counts,admins:2}}])assert.throws(()=>verifiedCleanupBackup(manifest,changed,now));
});
test('incomplete, stale, future and malformed backup evidence cannot permit cleanup',()=>{
 for(const changed of [{...manifest,archiveDecoded:false},{...manifest,archiveListed:false},{...manifest,bytes:0},{...manifest,createdAt:'2026-10-07T15:00:00.000Z'},{...manifest,createdAt:'invalid'},{...manifest,createdAt:'2026-10-07T16:55:00.000Z'}])assert.throws(()=>verifiedCleanupBackup(changed,receipt,now));
 for(const verifiedAt of ['invalid','2026-10-07T17:01:00.000Z'])assert.throws(()=>verifiedCleanupBackup(manifest,{...receipt,verifiedAt},now));
 assert.throws(()=>verifiedCleanupBackup(manifest,{...receipt,counts:{...receipt.counts,learners:'16'}},now));
});
