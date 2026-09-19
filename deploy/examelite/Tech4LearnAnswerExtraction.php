<?php
namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

/** Extract a draft only. The ordinary native answer-save path remains authoritative. */
final class Tech4LearnAnswerExtraction
{
 public function extract(string $workspace,int $source,string $learner,int $attempt,int $question,string $asset,int $exam):array {
  $reader=app(Tech4LearnAnswerAttachments::class);
  $file=$reader->read($workspace,$source,$learner,$attempt,$question,$asset,$exam);
  $owner=DB::table('tech4learn_workspaces')->where('id',$workspace)->where('source_organization_id',$source)->value('organization_id');
  $mapped=DB::table('tech4learn_workspace_users')->where('workspace_id',$workspace)->where('local_id',$learner)->where('kind','student')->value('external_id');
  $student=Student::where('organization_id',$owner)->where('status','Active')->findOrFail($mapped);
  return app(Tech4LearnStudentContext::class)->run((int)$owner,$student,[],function()use($reader,$workspace,$source,$learner,$attempt,$question,$asset,$exam,$file){
   \App\Support\SaasAccess::abortIfFeatureDisabled('ai_subjective_analysis');
   $bytes=base64_decode($file['base64'],true);abort_unless(is_string($bytes),503);
   $draft=app(Tech4LearnNativeAnswerExtraction::class)->text($bytes,$file['mime']);
   // No stale or revoked attachment text is returned after a slow extraction.
   $reader->read($workspace,$source,$learner,$attempt,$question,$asset,$exam);
   \App\Support\SaasAccess::abortIfFeatureDisabled('ai_subjective_analysis');
   return ['exam_id'=>$exam,'attempt_id'=>$attempt,'question_id'=>$question,'asset'=>$asset,'text'=>$draft['text']];
  });
 }
}
