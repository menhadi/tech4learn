<?php
// Reuse installed libraries without loading the original application's helpers.
if(PHP_SAPI!=='cli')exit(2);
$base=realpath(__DIR__.'/../.local/tech4learn-foundation');
if(!$base || str_replace('\\','/',$base)!==str_replace('\\','/',dirname(__DIR__).'/.local/tech4learn-foundation'))exit(2);
foreach(array_slice($argv,1) as $argument) {
    if(str_starts_with($argument,'-c') || preg_match('/^--(?:configuration|no-configuration|bootstrap|php-ini)(?:=|$)/',$argument)) {
        fwrite(STDERR,"BLOCKED: local native tests use only the fixed synthetic configuration.\n");exit(2);
    }
}
$configuration=file_get_contents($base.'/phpunit.xml');
if(stripos($configuration,'<!DOCTYPE')!==false)exit(2);
$xml=simplexml_load_string($configuration,SimpleXMLElement::class,LIBXML_NONET);
if($xml===false)exit(2);
foreach(['DB_CONNECTION'=>'sqlite','DB_DATABASE'=>':memory:','APP_ENV'=>'testing'] as $name=>$value) {
    $entries=$xml?->xpath('/phpunit/php/server[@name="'.$name.'"]');
    if(count($entries??[])!==1 || (string)$entries[0]['value']!==$value || ($name!=='APP_ENV' && (string)$entries[0]['force']!=='true')) {
        fwrite(STDERR,"BLOCKED: native tests require the synthetic in-memory database.\n");exit(2);
    }
}
$_SERVER['APP_ENV']='testing';putenv('APP_ENV=testing');
require $base.'/vendor/autoload.php';
foreach([new ReflectionFunction('getConfiguration'),new ReflectionClass(App\Http\Controllers\SaasController::class)] as $reflection) {
    if(!str_starts_with(str_replace('\\','/',$reflection->getFileName()),str_replace('\\','/',$base).'/')) {
        fwrite(STDERR,"BLOCKED: native tests must load the copied application's source.\n");exit(2);
    }
}
exit((new PHPUnit\TextUI\Application)->run(array_merge([$argv[0],'-c',$base.'/phpunit.xml'],array_slice($argv,1))));
