<?php
namespace App\Services;

use App\Models\Configuration;
use App\Support\AiProvider;

/** Read projection only. A calling boundary must authorise the owner first.
 * Never call available(): its result contains credentials and may include
 * another configuration through native platform fallback.
 */
final class Tech4LearnAiSettingsView
{
 public const TASKS=['translation','academic_review','source_text_audit','image_audit','answer_explanation','question_generation','question_regeneration','content_seo','subjective_assessment'];
 private const PROVIDERS=[
  'google'=>['google_gemini_api_key','google_gemini_model'],
  'openai'=>['openai_api_key','openai_model'],
  'deepseek'=>['deepseek_api_key','deepseek_model'],
  'anthropic'=>['anthropic_api_key','anthropic_model'],
 ];
 public function read(Configuration $configuration,int $owner):array {
  abort_unless($owner>0&&(int)$configuration->organization_id===$owner,403);
  $providers=[];
  foreach(self::PROVIDERS as $code=>[$keyField,$modelField]){
   $key=$configuration->$keyField;
   $providers[]=['code'=>$code,'credential_saved'=>is_string($key)&&trim($key)!=='',
    'configured_model'=>$this->model($configuration->$modelField),
    'configured_vision_model'=>$code==='deepseek'?$this->model($configuration->deepseek_vision_model):null];
  }
  $tasks=[];
  foreach(self::TASKS as $task)$tasks[$task]=$this->priority($configuration,$task);
  return ['providers'=>$providers,'priority'=>$this->priority($configuration,null),'task_priorities'=>$tasks];
 }
 private function model(mixed $value):?string {
  if($value===null||$value==='')return null;
  abort_unless(is_string($value)&&strlen($value)<=120&&!preg_match('/[\x00-\x1f\x7f]/',$value),503);
  return $value;
 }
 private function priority(Configuration $configuration,?string $task):array {
  $priority=AiProvider::priority($configuration,$task);
  abort_unless(count($priority)>0&&count($priority)<=4&&array_diff($priority,array_keys(self::PROVIDERS))===[],503);
  return array_values($priority);
 }
}
