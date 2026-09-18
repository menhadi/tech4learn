<?php
namespace App\Support {
    // The native translator runs; this boundary can never contact an AI provider.
    class AiProvider {
        public static array $owners=[];
        public static array $payloads=[];
        public static string $mode='valid';
        public static $duringGeneration=null;
        public static function firstAvailable($configuration,...$args){
            self::$owners[]=(int)$configuration?->organization_id;
            return ['provider'=>'synthetic','stored_name'=>'SYNTHETIC'];
        }
        public static function generateText($provider,$prompt,...$args){
            if(self::$mode==='failure')throw new \RuntimeException('Synthetic provider failure');
            if(self::$mode==='invalid')return 'not a translation JSON response';
            $payload=json_decode(substr($prompt,strrpos($prompt,"\n\n")+2),true,512,JSON_THROW_ON_ERROR);
            self::$payloads[]=$payload;
            if(self::$duringGeneration){$hook=self::$duringGeneration;self::$duringGeneration=null;$hook();}
            $translate=function(array $fields):array{
                foreach($fields as $key=>$value)if($key!=='id'&&is_string($value)&&trim($value)!=='')$fields[$key]='[synthetic target] '.$value;
                return $fields;
            };
            $result=['exam'=>$payload['exam']===null?null:$translate($payload['exam']),
                'questions'=>array_map($translate,$payload['questions'])];
            if(self::$mode==='omit')unset($result['questions'][1]['question']);
            return json_encode($result,JSON_THROW_ON_ERROR);
        }
    }
}
namespace {
// Isolated in-memory native records. Never bootstrap the installed application.
if(isset($argv[5]))$GLOBALS['t4lTestTranslationService']=$argv[5];
require __DIR__.'/test-exam-translations.php';
use Illuminate\Support\Facades\{DB,Cache};
use App\Models\{Exam,Language,Question,QuestionLang,ExamLanguageTranslation};
use App\Support\AiProvider;

$GLOBALS['t4lTestAiTranslation']=true;
if(!DB::getSchemaBuilder()->hasColumn('question_langs','translated_by'))DB::statement('ALTER TABLE question_langs ADD COLUMN translated_by TEXT');
if(!DB::getSchemaBuilder()->hasColumn('organizations','saas_plan_id'))DB::statement('ALTER TABLE organizations ADD COLUMN saas_plan_id INTEGER');
if(!DB::getSchemaBuilder()->hasTable('saas_plans'))DB::statement('CREATE TABLE saas_plans(id INTEGER PRIMARY KEY,features TEXT)');
DB::table('saas_plans')->insert(['id'=>9876,'features'=>json_encode(['ai_translation'=>true])]);
DB::table('organizations')->where('id',20)->update(['saas_plan_id'=>9876]);
if(!DB::getSchemaBuilder()->hasTable('configurations'))DB::statement('CREATE TABLE configurations(id INTEGER PRIMARY KEY,organization_id INTEGER)');
DB::table('configurations')->insert([['id'=>9001,'organization_id'=>30],['id'=>9002,'organization_id'=>20]]);
$locks=new class {
    public int $released=0;
    public function lock($key,$seconds){return new class($this){
        public function __construct(private $owner){}
        public function get(){return true;}
        public function release(){$this->owner->released++;}
    };}
};
$app->instance('cache',$locks);Cache::clearResolvedInstance('cache');
$paper=Exam::create(['organization_id'=>20,'name'=>'Synthetic generation paper','instruction'=>'Synthetic instructions','status'=>'Inactive']);
$language=Language::create(['organization_id'=>20,'name'=>'Synthetic target','code'=>'zz-generation','is_enabled'=>true]);
$paper->languages()->attach($language->id,['translation_status'=>'pending','auto_translate'=>false]);
$questions=[];
for($i=0;$i<6;$i++){
    $q=Question::create(['organization_id'=>20,'question'=>'Synthetic source '.$i,'option1'=>'First '.$i,'si_answer1'=>'Model answer '.$i]);
    $questions[]=$q;$paper->questions()->attach($q->id);
}
$pivot=fn()=>DB::table('exam_languages')->where('exam_id',$paper->id)->where('language_id',$language->id)->first();
$count=fn()=>QuestionLang::where('language_id',$language->id)->count();
foreach(['failure','invalid','omit'] as $mode){
    AiProvider::$mode=$mode;$before=$locks->released;
    try{$native->translateNextBatch($paper->fresh(),$language);throw new LogicException('Expected native batch rejection');}
    catch(RuntimeException $e){
        $expected=['failure'=>'Synthetic provider failure','invalid'=>'AI translation returned invalid JSON','omit'=>'AI translation omitted question'];
        check(str_starts_with($e->getMessage(),$expected[$mode]),'Native generation rejects the injected provider error');
    }
    check($count()===0&&ExamLanguageTranslation::where('exam_id',$paper->id)->count()===0,'Failed batch rolls back exam wording and earlier question writes');
    check($pivot()->translation_status==='failed'&&$pivot()->translation_approved_at===null&&$locks->released===$before+1,'Failed generation remains unapproved and releases its lock');
}
AiProvider::$mode='valid';
$first=$native->translateNextBatch($paper->fresh(),$language);
check($first['status']==='pending'&&$first['remaining']===1&&$count()===5,'Native batch limit persists five questions and reports remaining work');
check($pivot()->translation_approved_at===null,'Partial generation is not approved');
$second=$native->translateNextBatch($paper->fresh(),$language);
check($second['status']==='ready'&&$second['remaining']===0&&$count()===6,'Next native batch completes remaining translations');
check($pivot()->translation_approved_at===null,'Completed manual-review translation still requires approval');
check(array_unique(AiProvider::$owners)===[20],'Provider selection uses only the paper owner configuration');
foreach($questions as $source){
    $target=QuestionLang::where('question_id',$source->id)->where('language_id',$language->id)->firstOrFail();
    check($target->question==='[synthetic target] '.$source->question&&$target->si_answer1==='[synthetic target] '.$source->si_answer1,'Native generation persists wording and subjective model answers');
    check($target->source_fingerprint===$native->questionFingerprint($source->fresh()),'Saved target records native source fingerprints');
}
$calls=count(AiProvider::$owners);$native->translateNextBatch($paper->fresh(),$language);
check(count(AiProvider::$owners)===$calls&&$count()===6,'Repeated completion neither calls provider nor duplicates wording');
$before=QuestionLang::where('question_id',$questions[0]->id)->where('language_id',$language->id)->firstOrFail();
$beforeModel=$before->si_answer1;
$questions[0]->update(['option1'=>'Changed source option']);
$native->translateNextBatch($paper->fresh(),$language);
$last=end(AiProvider::$payloads);
check($last['exam']===null&&count($last['questions'])===1&&array_keys($last['questions'][0])===['id','option1'],'Refresh requests only changed source fields');
check($before->fresh()->option1==='[synthetic target] Changed source option'&&$before->fresh()->si_answer1===$beforeModel,'Refresh preserves unchanged translated fields');
$calls=count(AiProvider::$owners);
foreach([30,20] as $owner){
    $unlinked=Language::create(['organization_id'=>$owner,'name'=>'Synthetic unlinked language','code'=>'unlinked-'.$owner,'is_enabled'=>true]);
    try{$native->translateNextBatch($paper->fresh(),$unlinked);throw new LogicException('Expected language scope denial');}
    catch(Symfony\Component\HttpKernel\Exception\HttpException $e){check($e->getStatusCode()===422,'Foreign or unlinked target language is rejected');}
}
check(count(AiProvider::$owners)===$calls,'Invalid language scope never reaches provider selection');
$paper->update(['name'=>'Source before provider']);
$examTarget=ExamLanguageTranslation::where('exam_id',$paper->id)->where('language_id',$language->id)->firstOrFail();
$beforeTarget=$examTarget->getAttributes();
AiProvider::$duringGeneration=fn()=>DB::table('exams')->where('id',$paper->id)->update(['name'=>'Source changed during provider']);
try{$native->translateNextBatch($paper->fresh(),$language);throw new LogicException('Expected changed source rejection');}
catch(RuntimeException $e){check(str_contains($e->getMessage(),'Translation inputs changed'),'Changed source is rejected before target writes');}
check($examTarget->fresh()->getAttributes()===$beforeTarget&&$pivot()->translation_status==='failed','Late provider response cannot replace the translation after source changes');
$native->translateNextBatch($paper->fresh(),$language);
$paper->update(['instruction'=>'Instruction before provider']);
$beforeInstruction=$examTarget->fresh()->instruction;
AiProvider::$duringGeneration=fn()=>DB::table('exam_language_translations')->where('id',$examTarget->id)->update(['name'=>'Staff corrected exam target']);
try{$native->translateNextBatch($paper->fresh(),$language);throw new LogicException('Expected changed exam target rejection');}
catch(RuntimeException $e){check(str_contains($e->getMessage(),'Translation inputs changed'),'Changed exam target is rejected before generated writes');}
check($examTarget->fresh()->name==='Staff corrected exam target'&&$examTarget->fresh()->instruction===$beforeInstruction,'Exam target correction survives without partial instruction writes');
$native->translateNextBatch($paper->fresh(),$language);
check($examTarget->fresh()->name==='Staff corrected exam target','Explicit exam retry retains the reviewed target name');
$questions[0]->update(['option1'=>'Question option before provider']);
$beforeQuestion=$before->fresh()->getAttributes();
AiProvider::$duringGeneration=fn()=>DB::table('questions')->where('id',$questions[0]->id)->update(['question'=>'Question source changed during provider']);
try{$native->translateNextBatch($paper->fresh(),$language);throw new LogicException('Expected changed question source rejection');}
catch(RuntimeException $e){check(str_contains($e->getMessage(),'Translation inputs changed'),'Changed question source is rejected before generated writes');}
check($before->fresh()->getAttributes()===$beforeQuestion,'Question source change leaves all prior target fields intact');
$native->translateNextBatch($paper->fresh(),$language);
$questions[0]->update(['option1'=>'Source option before provider']);
$beforeOption=$before->fresh()->option1;
AiProvider::$duringGeneration=fn()=>DB::table('question_langs')->where('id',$before->id)->update(['question'=>'Staff corrected target during provider']);
try{$native->translateNextBatch($paper->fresh(),$language);throw new LogicException('Expected changed target rejection');}
catch(RuntimeException $e){check(str_contains($e->getMessage(),'Translation inputs changed'),'Changed target is rejected before generated writes');}
check($before->fresh()->question==='Staff corrected target during provider'&&$before->fresh()->option1===$beforeOption,'Staff correction survives the late provider response without partial writes');
$native->translateNextBatch($paper->fresh(),$language);
check($before->fresh()->question==='Staff corrected target during provider','Explicit retry refreshes changed source fields without overwriting the staff correction');
$GLOBALS['t4lTestAiTranslation']=false;$calls=count(AiProvider::$owners);
try{$native->translateNextBatch($paper->fresh(),$language);throw new LogicException('Expected plan denial');}
catch(Symfony\Component\HttpKernel\Exception\HttpException $e){check($e->getStatusCode()===403,'Revoked native feature stops generation');}
check(count(AiProvider::$owners)===$calls,'Feature denial happens before provider selection');
echo "Native translation generation: synthetic-provider batching, rollback, ownership, model answers, selective refresh, stale source/target protection and repeat checks passed. Real provider quality and queue daemon operation remain unverified.\n";
}
