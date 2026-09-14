<?php
namespace App\Http\Controllers;

use App\Services\Tech4LearnResultMarking;
use Illuminate\Http\Request;

/** Requires the dedicated platform credential, plus its asserted scoped staff actor. */
class Tech4LearnResultController extends Tech4LearnPlatformController
{
 public function media(Request $r,string $org,string $learner,string $attempt,string $stat,string $asset){
  $source=$this->configuration($r)['_platform']['organization_id'];$actor=$r->query('actor_id');
  abort_unless(is_string($actor)&&preg_match('/^[1-9][0-9]{0,14}$/D',$attempt)&&preg_match('/^[1-9][0-9]{0,14}$/D',$stat)&&preg_match('/^[a-f0-9]{64}$/D',$asset),422);
  return $this->reply($source,['data'=>app(Tech4LearnResultMarking::class)->media($org,$source,$actor,$learner,(int)$attempt,(int)$stat,$asset)]);
 }
 public function attempts(Request $r,string $org,string $learner){
  $source=$this->configuration($r)['_platform']['organization_id'];
  $actor=$r->query('actor_id');$after=$r->query('after','0');
  abort_unless(is_string($actor)&&is_string($after)&&preg_match('/^[0-9]{1,15}$/D',$after),422);
  return $this->reply($source,app(Tech4LearnResultMarking::class)->attempts($org,$source,$actor,$learner,(int)$after));
 }
 public function review(Request $r,string $org,string $learner,string $attempt){
  $source=$this->configuration($r)['_platform']['organization_id'];$actor=$r->query('actor_id');
  abort_unless(is_string($actor)&&preg_match('/^[1-9][0-9]{0,14}$/D',$attempt),422);
  return $this->reply($source,app(Tech4LearnResultMarking::class)->review($org,$source,$actor,$learner,(int)$attempt));
 }
 public function save(Request $r,string $org,string $learner,string $attempt){
  $source=$this->configuration($r)['_platform']['organization_id'];
  abort_unless(strlen($r->getContent())<=50000&&array_diff(array_keys($r->all()),['actor_id','marks','revision','request_id'])===[],422);
  $actor=$r->input('actor_id');$marks=$r->input('marks');$revision=$r->input('revision');$request=$r->input('request_id');
  abort_unless(is_string($actor)&&is_array($marks)&&is_string($revision)&&is_string($request)&&preg_match('/^[1-9][0-9]{0,14}$/D',$attempt),422);
  try {
   $result=app(Tech4LearnResultMarking::class)->save($org,$source,$actor,$learner,(int)$attempt,$marks,$revision,$request);
   return $this->reply($source,['saved'=>true,'result'=>$result]);
  }catch(\Symfony\Component\HttpKernel\Exception\HttpException $error){
   if(!in_array($error->getStatusCode(),[409,422],true))throw $error;
   return $this->reply($source,['saved'=>false,'conflict'=>$error->getStatusCode()===409]);
  }catch(\Illuminate\Validation\ValidationException $error){return $this->reply($source,['saved'=>false,'conflict'=>false]);}
 }
}
