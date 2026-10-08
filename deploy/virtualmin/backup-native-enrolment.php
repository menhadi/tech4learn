<?php
// CLI deployment account only. No migrations, records, credentials or SQL are printed.
if(PHP_SAPI!=='cli' || $argc!==2){fwrite(STDERR,"Use the checked native platform directory.\n");exit(2);}
try {
    $root=realpath($argv[1]);$base='/home/tech4learn/private-backups';
    if(PHP_OS_FAMILY!=='Linux' || !function_exists('posix_geteuid') || posix_geteuid()===0
        || !$root || !preg_match('#^/home/tech4learn/releases/[a-f0-9]{40}/platform$#D',$root)
        || !is_file($root.'/vendor/autoload.php') || !is_file($root.'/.env')
        || is_link($base) || realpath($base)!==$base || fileowner($base)!==posix_geteuid()
        || (fileperms($base)&0077)!==0)throw new RuntimeException;
    require $root.'/vendor/autoload.php';
    $env=Dotenv\Dotenv::parse(file_get_contents($root.'/.env'));
    if(($env['DB_CONNECTION']??'')!=='mysql' || ($env['DB_HOST']??'')!=='127.0.0.1'
        || ($env['DB_PORT']??'3306')!=='3306' || ($env['DB_DATABASE']??'')!=='tech4learn_exams'
        || ($env['DB_USERNAME']??'')!=='tech4learn_exams' || empty($env['DB_PASSWORD']))throw new RuntimeException;
    $directory=$base.'/native-enrolment-'.gmdate('Ymd\THis\Z').'-'.bin2hex(random_bytes(4));
    if(!mkdir($directory,0700))throw new RuntimeException;
    $dump=$directory.'/database.sql';$errors=$directory.'/dump.stderr';$mask=umask(0077);
    try {
        $process=proc_open(['/usr/bin/mariadb-dump','--host=127.0.0.1','--port=3306','--user=tech4learn_exams',
            '--single-transaction','--skip-lock-tables','--no-tablespaces','--databases','tech4learn_exams'],
            [0=>['file','/dev/null','r'],1=>['file',$dump,'x'],2=>['file',$errors,'x']],$pipes,null,
            ['PATH'=>'/usr/bin:/bin','MYSQL_PWD'=>$env['DB_PASSWORD']]);
        if(!is_resource($process) || proc_close($process)!==0)throw new RuntimeException;
        chmod($dump,0600);chmod($errors,0600);
        if(filesize($dump)<100 || filesize($errors)>0)throw new RuntimeException;
        $file=fopen($dump,'rb');fseek($file,max(0,filesize($dump)-4096));$tail=stream_get_contents($file);fclose($file);
        if(!str_contains($tail,'Dump completed on'))throw new RuntimeException;
        $manifest=['createdAt'=>gmdate('c'),'bytes'=>filesize($dump),'sha256'=>hash_file('sha256',$dump),
            'dumpCompleted'=>true,'restoreTested'=>false,'liveDatabaseChanged'=>false];
        if(file_put_contents($directory.'/manifest.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n",LOCK_EX)===false)throw new RuntimeException;
    } finally {umask($mask);}
    echo "Private native database backup completed: ".$directory."\n";
    echo "No live database changes; isolated restore acceptance is not claimed.\n";
} catch(Throwable $error){fwrite(STDERR,"Native backup blocked or incomplete; no migrations or live data changes performed.\n");exit(1);}
