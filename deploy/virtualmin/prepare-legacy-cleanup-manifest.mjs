// Operator preparation only: reads the source database and writes one private review artifact.
import pg from 'pg';
import {lstatSync,readFileSync,writeFileSync} from 'node:fs';
import {loadCleanupEvidence} from './load-cleanup-evidence.mjs';
import {legacyRestoreTarget} from './legacy-restore-target.mjs';
import {assertLegacyWritesPaused} from './check-legacy-writes-paused.mjs';
import {captureLegacyCleanupManifest} from './cleanup-legacy-organisation-records.mjs';
let client;
try {
 const directory=process.argv[2];
 if(process.argv.length!==3 || process.platform!=='linux')throw new Error();
 const evidence=await loadCleanupEvidence(directory);
 legacyRestoreTarget(process.env.DATABASE_URL); // Validate source account/database; never use the restore target here.
 const reviewedPath=directory+'/previous-organisations.json',stat=lstatSync(reviewedPath);
 if(stat.isSymbolicLink() || !stat.isFile() || stat.nlink!==1 || stat.uid!==process.getuid()
   || (stat.mode & 0o077)!==0 || stat.size>65536)throw new Error();
 const reviewed=JSON.parse(readFileSync(reviewedPath,'utf8'));
 client=new pg.Client({connectionString:process.env.DATABASE_URL,connectionTimeoutMillis:5000});
 await client.connect();
 await client.query('BEGIN TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
 await client.query("SET LOCAL statement_timeout='10s'");
 await assertLegacyWritesPaused(client);
 const manifest=await captureLegacyCleanupManifest(client,evidence.backupManifest.sha256,reviewed.organisationIds);
 await client.query('ROLLBACK');
 process.umask(0o077);
 writeFileSync(directory+'/cleanup-manifest.json',JSON.stringify({version:1,capturedAt:new Date().toISOString(),...manifest},null,2)+'\n',{mode:0o600,flag:'wx'});
 console.log('Private archive-bound cleanup manifest saved for review. No database records changed.');
}catch{await client?.query('ROLLBACK').catch(()=>{});console.error('Cleanup manifest preparation blocked; verify private review IDs, fresh restored backup and paused writes. No deletion attempted.');process.exitCode=1;}
finally{await client?.end().catch(()=>{});}
