// Authorized operator entry point. Never invoke until the reviewed maintenance window.
import pg from 'pg';
import {lstatSync,readFileSync,writeFileSync,realpathSync,existsSync} from 'node:fs';
import {fileURLToPath} from 'node:url';
import {execFileSync} from 'node:child_process';
import {loadCleanupEvidence} from './load-cleanup-evidence.mjs';
import {legacyRestoreTarget} from './legacy-restore-target.mjs';
import {cleanupLegacyOrganisationRecords} from './cleanup-legacy-organisation-records.mjs';
let client,committed=false,commitAttempted=false;
try {
 if(process.platform!=='linux' || process.argv.length!==4 || process.argv[3]!=='--confirm-authorized-previous-organisation-deletion')throw new Error();
 const root=realpathSync(fileURLToPath(new URL('../../',import.meta.url)));
 const pin=/^\/home\/tech4learn\/releases\/([a-f0-9]{40})$/.exec(root)?.[1];
 if(!pin || execFileSync('/usr/bin/git',['-C',root,'rev-parse','HEAD'],{encoding:'utf8'}).trim()!==pin
   || execFileSync('/usr/bin/git',['-C',root,'status','--porcelain'],{encoding:'utf8'}).trim())throw new Error();
 const directory=process.argv[2],evidence=await loadCleanupEvidence(directory);
 if(existsSync(directory+'/cleanup-result.json'))throw new Error();
 legacyRestoreTarget(process.env.DATABASE_URL);
 const path=directory+'/cleanup-manifest.json',stat=lstatSync(path);
 if(stat.isSymbolicLink() || !stat.isFile() || stat.nlink!==1 || stat.uid!==process.getuid()
   || (stat.mode & 0o077)!==0 || stat.size>1048576)throw new Error();
 const manifest=JSON.parse(readFileSync(path,'utf8')),captured=Date.parse(manifest.capturedAt);
 if(manifest.version!==1 || !Number.isFinite(captured) || captured>Date.now() || Date.now()-captured>3600000)throw new Error();
 client=new pg.Client({connectionString:process.env.DATABASE_URL,connectionTimeoutMillis:5000});await client.connect();
 const database={transaction:async fn=>{
   await client.query('BEGIN');
   try {const result=await fn(client);commitAttempted=true;await client.query('COMMIT');return result;}
   catch(error){await client.query('ROLLBACK');throw error;}
 }};
 const result=await cleanupLegacyOrganisationRecords(database,manifest,evidence);committed=true;
 process.umask(0o077);
 writeFileSync(directory+'/cleanup-result.json',JSON.stringify({completedAt:new Date().toISOString(),release:pin,archiveSha256:manifest.archiveSha256,...result},null,2)+'\n',{mode:0o600,flag:'wx'});
 console.log('Reviewed previous-organisation cleanup committed; administrator preserved and private result saved.');
}catch{
 console.error(committed?'Cleanup committed, but private result saving failed. Do not rerun; verify database state.':commitAttempted?'Cleanup commit outcome is uncertain. Do not rerun; verify database state.':'Cleanup blocked or rolled back. Review private evidence and paused writes before retry.');
 process.exitCode=1;
}finally{await client?.end().catch(()=>{});}
