// Trusted Tech4Learn deployment account only. Writes a private backup, never live data.
import { execFileSync } from 'node:child_process';
import { lstatSync,realpathSync,mkdtempSync,chmodSync,statSync,writeFileSync,createReadStream } from 'node:fs';
import { createHash } from 'node:crypto';

try {
  const root='/home/tech4learn/private-backups';
  const directory=lstatSync(root);
  if (process.platform !== 'linux' || !process.getuid || process.getuid()===0
      || directory.isSymbolicLink() || !directory.isDirectory()
      || realpathSync(root)!==root || directory.uid!==process.getuid()
      || (directory.mode & 0o077)!==0 || !process.env.DATABASE_URL) throw new Error();
  const url=new URL(process.env.DATABASE_URL);
  if (!['postgres:','postgresql:'].includes(url.protocol)) throw new Error();
  const backup=mkdtempSync(root+'/legacy-cleanup-');chmodSync(backup,0o700);
  const dump=backup+'/database.dump';
  const env={...process.env,PGHOST:url.hostname,PGPORT:url.port||'5432',
    PGDATABASE:decodeURIComponent(url.pathname.slice(1)),PGUSER:decodeURIComponent(url.username),
    PGPASSWORD:decodeURIComponent(url.password)};
  if(url.searchParams.get('sslmode'))env.PGSSLMODE=url.searchParams.get('sslmode');
  process.umask(0o077);
  execFileSync('/usr/bin/pg_dump',['--format=custom','--file',dump],{env,stdio:['ignore','ignore','pipe'],timeout:300000});
  chmodSync(dump,0o600);
  if (!statSync(dump).size)throw new Error();
  const list=execFileSync('/usr/bin/pg_restore',['--list',dump],{stdio:['ignore','pipe','pipe'],timeout:60000});
  writeFileSync(backup+'/archive-list',list,{mode:0o600,flag:'wx'});
  // Decode every selected archive entry to a sink; no SQL is executed or printed.
  execFileSync('/usr/bin/pg_restore',['--file=/dev/null',dump],{stdio:['ignore','ignore','pipe'],timeout:300000});
  const hash=createHash('sha256');
  for await(const chunk of createReadStream(dump))hash.update(chunk);
  writeFileSync(backup+'/manifest.json',JSON.stringify({createdAt:new Date().toISOString(),
    bytes:statSync(dump).size,sha256:hash.digest('hex'),archiveListed:true,archiveDecoded:true,
    restoreTested:false,liveDatabaseChanged:false},null,2)+'\n',{mode:0o600,flag:'wx'});
  console.log('Private backup created and archive decoded: '+backup);
  console.log('Actual isolated database restore verification is still required before cleanup.');
} catch {
  console.error('Private backup blocked or incomplete; live database unchanged. Do not use an incomplete backup for cleanup.');
  process.exitCode=1;
}
