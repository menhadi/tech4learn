import {test} from 'node:test';
import assert from 'node:assert/strict';
import {createHash} from 'node:crypto';
import {loadCleanupEvidence} from '../deploy/virtualmin/load-cleanup-evidence.mjs';
const directory='/home/tech4learn/private-backups/legacy-cleanup-synthetic';
function fixture(){
 const bytes=Buffer.from('synthetic archive'),sha=createHash('sha256').update(bytes).digest('hex'),timestamp=new Date().toISOString();
 const backup={createdAt:timestamp,bytes:bytes.length,sha256:sha,archiveListed:true,archiveDecoded:true,restoreTested:false,liveDatabaseChanged:false};
 const receipt={verifiedAt:timestamp,database:'tech4learn_cleanup_restore',archiveSha256:sha,administratorPreserved:true,liveDatabaseChanged:false,counts:{admins:1,organisations:1,learners:0,attendance_sessions:0}};
 const io={uid:()=>1033,realpath:path=>path,stat:path=>({uid:1033,mode:0o600,nlink:1,ino:123,mtimeMs:1,size:path.endsWith('.dump')?bytes.length:100,isSymbolicLink:()=>false,isDirectory:()=>path===directory,isFile:()=>path!==directory}),text:path=>JSON.stringify(path.endsWith('/manifest.json')?backup:receipt),stream:async function*(){yield bytes;}};
 return {io,backup,receipt};
}
test('private evidence loader validates streamed archive bytes and matching actual receipt',async()=>{
 const {io,backup,receipt}=fixture();assert.deepEqual(await loadCleanupEvidence(directory,io),{backupManifest:backup,restoreReceipt:receipt});
 const altered={...io,stream:async function*(){yield Buffer.from('changed archive');}};
 await assert.rejects(loadCleanupEvidence(directory,altered),/unsafe or changed/);
});
test('unsafe paths, root, links, shared permissions and replaced archive are refused',async()=>{
 const {io}=fixture();
 for(const modified of [{...io,uid:()=>0},{...io,realpath:()=>'/elsewhere'},{...io,stat:path=>({...io.stat(path),mode:0o644})},{...io,stat:path=>({...io.stat(path),uid:999})},{...io,stat:path=>({...io.stat(path),nlink:2})},{...io,stat:path=>({...io.stat(path),isSymbolicLink:()=>true})}])await assert.rejects(loadCleanupEvidence(directory,modified));
 await assert.rejects(loadCleanupEvidence('/home/other/private-backups/legacy-cleanup-synthetic',io));
 let reads=0;const raced={...io,stat:path=>({...io.stat(path),ino:path.endsWith('.dump') && ++reads>2 ? 456:123})};
 await assert.rejects(loadCleanupEvidence(directory,raced));
});
