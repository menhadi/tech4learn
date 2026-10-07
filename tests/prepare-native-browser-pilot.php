<?php
if (PHP_SAPI!=='cli') exit(1);
set_exception_handler(function(Throwable $error){fwrite(STDERR,"BLOCKED: local synthetic fixture requires review.\n");exit(1);});
$base=realpath(__DIR__.'/../.local/tech4learn-foundation');
if(!$base || str_replace('\\','/',$base)!==str_replace('\\','/',dirname(__DIR__).'/.local/tech4learn-foundation')) throw new RuntimeException('Local fixture missing');
require $base.'/vendor/autoload.php';
$app=require $base.'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!$app->environment('local') || config('database.default')!=='sqlite' || str_replace('\\','/',config('database.connections.sqlite.database'))!==str_replace('\\','/',realpath($base.'/database/foundation.sqlite'))) throw new RuntimeException('Local fixture only');
if(str_replace('\\','/',realpath($base.'/database/foundation.sqlite'))!==str_replace('\\','/',$base.'/database/foundation.sqlite')) throw new RuntimeException('Database path differs');
$db=Illuminate\Support\Facades\DB::connection();
$db->transaction(function()use($db,$base){
 if($db->table('organizations')->where('id',2)->exists()||$db->table('users')->where('id',2)->exists()) {
  $organization=$db->table('organizations')->where('id',2)->first();$user=$db->table('users')->where('id',2)->first();
  $settings=json_decode($organization->settings??'',true);
  if(!$organization || !$user || $organization->slug!=='synthetic-browser-tenant' || $organization->domain!=='two.localhost' || $organization->status!=='active' || ($settings['is_primary_platform']??null)!==false || $user->username!=='synthetic-browser-staff' || $user->email!=='synthetic@example.invalid' || $user->status!=='Active' || $user->deleted || $user->is_platform_admin || !$db->table('organization_users')->where(['organization_id'=>2,'user_id'=>2,'role'=>'admin','status'=>1])->exists()) throw new RuntimeException('IDs occupied; review instead of adopting');
  return;
 }
 $now=now();$credentials=json_decode(file_get_contents($base.'/local-pilot-credentials.json'),true,512,JSON_THROW_ON_ERROR);
 $db->table('organizations')->insert(['id'=>2,'name'=>'Synthetic browser tenant','slug'=>'synthetic-browser-tenant','domain'=>'two.localhost','status'=>'active','settings'=>json_encode(['is_primary_platform'=>false]),'created_at'=>$now,'updated_at'=>$now]);
 $db->table('users')->insert(['id'=>2,'name'=>'Synthetic staff','username'=>'synthetic-browser-staff','email'=>'synthetic@example.invalid','password'=>Illuminate\Support\Facades\Hash::make($credentials['password']),'status'=>'Active','deleted'=>0,'is_platform_admin'=>0,'created_at'=>$now,'updated_at'=>$now]);
 $db->table('organization_users')->insert(['organization_id'=>2,'user_id'=>2,'role'=>'admin','status'=>1,'created_at'=>$now,'updated_at'=>$now]);
});
echo "Synthetic native tenant and staff prepared locally.\n";
