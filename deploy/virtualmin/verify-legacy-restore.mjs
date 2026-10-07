// Restore only into the fixed, separately created verification database.
import pg from 'pg';
import { execFileSync } from 'node:child_process';
import { lstatSync,realpathSync,readFileSync,writeFileSync,createReadStream } from 'node:fs';
import { createHash } from 'node:crypto';
import { legacyRestoreTarget } from './legacy-restore-target.mjs';

let destination;
try {
  const directory=process.argv[2];
  if(process.argv.length!==3 || !/^\/home\/tech4learn\/private-backups\/legacy-cleanup-[A-Za-z0-9]+$/.test(directory)
    || process.platform!=='linux' || process.getuid()===0 || realpathSync(directory)!==directory)throw new Error();
  for(const path of [directory,directory+'/database.dump',directory+'/manifest.json']) {
    const stat=lstatSync(path);
    if(stat.isSymbolicLink() || stat.uid!==process.getuid() || (stat.mode & 0o077)!==0)throw new Error();
  }
  const manifest=JSON.parse(readFileSync(directory+'/manifest.json','utf8'));
  if(!manifest.archiveDecoded || manifest.restoreTested || !/^[a-f0-9]{64}$/.test(manifest.sha256))throw new Error();
  const hash=createHash('sha256');for await(const chunk of createReadStream(directory+'/database.dump'))hash.update(chunk);
  if(hash.digest('hex')!==manifest.sha256 || lstatSync(directory+'/database.dump').size!==manifest.bytes)throw new Error();
  const url=legacyRestoreTarget(process.env.DATABASE_URL);
  destination=new pg.Client({connectionString:url.toString(),connectionTimeoutMillis:5000});
  await destination.connect();
  const empty=await destination.query("SELECT count(*)::integer AS count FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname='public' AND c.relkind IN ('r','p','v','m','S','f')");
  if(empty.rows[0].count!==0)throw new Error();
  const env={...process.env,PGHOST:url.hostname,PGPORT:url.port||'5432',PGDATABASE:'tech4learn_cleanup_restore',
    PGUSER:decodeURIComponent(url.username),PGPASSWORD:decodeURIComponent(url.password)};
  if(url.searchParams.get('sslmode'))env.PGSSLMODE=url.searchParams.get('sslmode');
  execFileSync('/usr/bin/pg_restore',['--exit-on-error','--no-owner','--no-privileges','--dbname=tech4learn_cleanup_restore',directory+'/database.dump'],{env,stdio:['ignore','ignore','pipe'],timeout:300000});
  const counts=await destination.query(`SELECT (SELECT count(*) FROM users WHERE is_superadmin)::integer AS admins,
    (SELECT count(*) FROM organisations)::integer AS organisations,
    (SELECT count(*) FROM learners)::integer AS learners,
    (SELECT count(*) FROM attendance_sessions)::integer AS attendance_sessions`);
  if(counts.rows[0].admins!==1)throw new Error();
  // Mark success only after actual restore and queries. No learner values in output.
  writeFileSync(directory+'/restore-verification.json',JSON.stringify({verifiedAt:new Date().toISOString(),database:'tech4learn_cleanup_restore',archiveSha256:manifest.sha256,counts:counts.rows[0],liveDatabaseChanged:false},null,2)+'\n',{mode:0o600,flag:'wx'});
  console.log('Isolated database restore completed; private verification receipt saved. Live database unchanged.');
}catch{console.error('Isolated restore blocked or failed. No live database restore was attempted; inspect private state before retry.');process.exitCode=1;}
finally{await destination?.end().catch(()=>{});}
