<?php
// Reuse installed libraries without loading the original application's helpers.
if(PHP_SAPI!=='cli')exit(2);
$base=realpath(__DIR__.'/../.local/tech4learn-foundation');
if(!$base || str_replace('\\','/',$base)!==str_replace('\\','/',dirname(__DIR__).'/.local/tech4learn-foundation'))exit(2);
require $base.'/vendor/autoload.php';
foreach([new ReflectionFunction('getConfiguration'),new ReflectionClass(App\Http\Controllers\SaasController::class)] as $reflection) {
    if(!str_starts_with(str_replace('\\','/',$reflection->getFileName()),str_replace('\\','/',$base).'/')) {
        fwrite(STDERR,"BLOCKED: native tests must load the copied application's source.\n");exit(2);
    }
}
exit((new PHPUnit\TextUI\Application)->run(array_merge([$argv[0],'-c',$base.'/phpunit.xml'],array_slice($argv,1))));
