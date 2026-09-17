<?php
namespace App\Support {
    class AiProvider { public static function firstAvailable(...$args){throw new \RuntimeException('AI provider access is forbidden in the completion fixture.');} }
}
namespace {
// Native completion without contacting any AI provider or running a queue.
require __DIR__.'/test-exam-translations.php';
use Illuminate\Support\Facades\{DB,Cache};
use App\Models\{Exam,Language,Question,QuestionLang,ExamLanguageTranslation};

if(!DB::getSchemaBuilder()->hasColumn('organizations','saas_plan_id'))DB::statement('ALTER TABLE organizations ADD COLUMN saas_plan_id INTEGER');
if(!DB::getSchemaBuilder()->hasTable('saas_plans'))DB::statement('CREATE TABLE saas_plans(id INTEGER PRIMARY KEY,features TEXT)');
DB::table('saas_plans')->insert(['id'=>9876,'features'=>json_encode(['ai_translation'=>true])]);
DB::table('organizations')->where('id',20)->update(['saas_plan_id'=>9876]);
$locks=new class {public int $released=0;public bool $available=true;public function lock($key,$seconds){return new class($this){public function __construct(private $owner){}public function get(){return $this->owner->available;}public function release(){$this->owner->released++;}};}};
$app->instance('cache',$locks);Cache::clearResolvedInstance('cache');
$paper=Exam::create(['organization_id'=>20,'name'=>'Synthetic complete translation','status'=>'Inactive']);
$language=Language::create(['organization_id'=>20,'name'=>'Completion fixture','code'=>'zz','is_enabled'=>true]);
$paper->languages()->attach($language->id,['translation_status'=>'pending','auto_translate'=>false]);
$question=Question::create(['organization_id'=>20,'question'=>'Synthetic completed source']);$paper->questions()->attach($question->id);
$paper=$paper->fresh();$question=$question->fresh();
QuestionLang::create(['question_id'=>$question->id,'language_id'=>$language->id,'question'=>'Synthetic completed target','source_fingerprint'=>$native->questionFingerprint($question),'source_field_fingerprints'=>$native->questionFieldFingerprints($question)]);
ExamLanguageTranslation::create(['exam_id'=>$paper->id,'language_id'=>$language->id,'name'=>'Synthetic target exam','source_fingerprint'=>$native->examFingerprint($paper),'source_field_fingerprints'=>$native->examFieldFingerprints($paper)]);
$result=$native->translateNextBatch($paper,$language);
check($result['status']==='ready'&&$result['remaining']===0&&$locks->released===1,'Native completed batch reaches ready and releases lock');
$pivot=fn()=>DB::table('exam_languages')->where('exam_id',$paper->id)->where('language_id',$language->id)->first();
check($pivot()->translation_approved_at===null,'Manual-review mode does not gain automatic approval');
$native->translateNextBatch($paper,$language);check($locks->released===2&&QuestionLang::where('question_id',$question->id)->count()===1,'Repeated completion does not duplicate translations');
$paper->languages()->updateExistingPivot($language->id,['auto_translate'=>true]);
$native->translateNextBatch($paper,$language);check($pivot()->translation_approved_at!==null,'Native automatic mode retains its explicit approval policy');
$locks->available=false;$before=$locks->released;
check($native->translateNextBatch($paper,$language)['status']==='processing'&&$locks->released===$before,'Contended worker does not release another worker lock');
$locks->available=true;
$GLOBALS['t4lTestAiTranslation']=false;
try{$native->translateNextBatch($paper,$language);throw new RuntimeException('Expected disabled-plan rejection');}
catch(Symfony\Component\HttpKernel\Exception\HttpException $error){check($error->getStatusCode()===403,'Injected feature denial is applied before completion');}
echo "Native translation completion: ready/manual approval, repeat, automatic policy, lock contention and feature-denial checks passed. AI generation remains untested.\n";
}
