<?php
namespace App\Http\Controllers;

use App\Services\Tech4LearnProctorEvidence;
use Illuminate\Http\Request;

/** Private server-to-server review boundary; never a student route. */
class Tech4LearnProctorController extends Tech4LearnPlatformController
{
 public function attempts(Request $r,string $org,string $learner){
  $source=$this->configuration($r)['_platform']['organization_id'];
  $after=$r->query('after','0');abort_unless(is_string($after)&&preg_match('/^[0-9]{1,15}$/D',$after),422);
  return $this->reply($source,app(Tech4LearnProctorEvidence::class)->attempts($org,$source,$learner,(int)$after));
 }
 public function captures(Request $r,string $org,string $learner,string $attempt,?string $capture=null){
  $source=$this->configuration($r)['_platform']['organization_id'];
  abort_unless(preg_match('/^[1-9][0-9]{0,14}$/D',$attempt),422);
  return $this->reply($source,app(Tech4LearnProctorEvidence::class)->review($org,$source,$learner,(int)$attempt,$capture));
 }
}
