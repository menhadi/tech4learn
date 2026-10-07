// Read-only loader, no CLI and no deletion. IO seam is for isolated filesystem-boundary tests.
import {lstatSync,realpathSync,readFileSync,createReadStream} from 'node:fs';
import {createHash} from 'node:crypto';
import {verifiedCleanupBackup} from './cleanup-backup-proof.mjs';
const nativeIO={uid:()=>process.getuid?.(),stat:lstatSync,realpath:realpathSync,text:path=>readFileSync(path,'utf8'),stream:createReadStream};
export async function loadCleanupEvidence(directory,io=nativeIO) {
 try {
  const owner=io.uid();
  if(!Number.isInteger(owner) || owner===0 || typeof directory!=='string'
    || !/^\/home\/tech4learn\/private-backups\/legacy-cleanup-[A-Za-z0-9]+$/.test(directory)
    || io.realpath(directory)!==directory)throw new Error();
  const files=['database.dump','manifest.json','restore-verification.json'];
  for(const path of [directory,...files.map(file=>directory+'/'+file)]) {
   const stat=io.stat(path);
   if(stat.uid!==owner || (stat.mode & 0o077)!==0 || stat.isSymbolicLink()
     || (path===directory ? !stat.isDirectory() : !stat.isFile() || stat.nlink!==1))throw new Error();
  }
  for(const name of ['manifest.json','restore-verification.json'])if(io.stat(directory+'/'+name).size>65536)throw new Error();
  const backupManifest=JSON.parse(io.text(directory+'/manifest.json'));
  const restoreReceipt=JSON.parse(io.text(directory+'/restore-verification.json'));
  const proof=verifiedCleanupBackup(backupManifest,restoreReceipt);
  const archive=directory+'/database.dump',before=io.stat(archive),hash=createHash('sha256');
  if(before.size!==backupManifest.bytes)throw new Error();
  for await(const chunk of io.stream(archive))hash.update(chunk);
  const after=io.stat(archive);
  if(after.uid!==owner || (after.mode & 0o077)!==0 || after.nlink!==1 || after.isSymbolicLink() || !after.isFile()
    || hash.digest('hex')!==proof.archiveSha256 || before.ino!==after.ino
    || before.size!==after.size || before.mtimeMs!==after.mtimeMs)throw new Error();
  return {backupManifest,restoreReceipt};
 }catch{throw new Error('Private cleanup backup evidence is missing, unsafe or changed.');}
}
