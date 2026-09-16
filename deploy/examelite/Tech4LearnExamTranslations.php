<?php
namespace App\Services;

use App\Models\{Exam,ExamLanguageTranslation,Language,Organization,User};
use Illuminate\Support\Facades\DB;

/** Read-only staff review. Native fingerprints decide translation completeness. */
final class Tech4LearnExamTranslations
{
    private const FIELDS=['question','option1','option2','option3','option4','option5','option6','hint','explanation','fill_blank','si_answer1'];
    public function review(string $workspace,int $source,string $actor,int $examId,int $languageId,int $after=0,?string $expectedRevision=null):array {
        $result=$this->snapshot($workspace,$source,$actor,$examId,$languageId,$after,$expectedRevision);
        $media=app(Tech4LearnQuestionMedia::class);
        $rewrite=fn($fields)=>$fields===null?null:array_map(fn($html)=>$html===null?null:$media->rewrite((string)$html),$fields);
        $result['source']=$rewrite($result['source']);$result['translation']=$rewrite($result['translation']);
        foreach($result['items'] as &$item){$item['source']=$rewrite($item['source']);$item['translation']=$rewrite($item['translation']);}unset($item);
        return $result;
    }
    public function media(string $workspace,int $source,string $actor,int $examId,int $languageId,int $questionId,string $asset,string $revision):array {
        abort_unless($questionId>=0&&preg_match('/^[a-f0-9]{64}$/D',$revision),422);
        $read=fn()=>$this->snapshot($workspace,$source,$actor,$examId,$languageId,max(0,$questionId-1),$revision);
        $review=$read();
        if($questionId===0){$fields=$review;}
        else {$fields=$review['items'][0]??null;abort_unless($fields&&$fields['question_id']===$questionId,404);}
        $wording=array_merge(array_values($fields['source']),array_values($fields['translation']??[]));
        $result=app(Tech4LearnQuestionMedia::class)->readReferenced($wording,$asset);
        // Recheck access and the complete reviewed version after reading the file.
        $read();
        return $result+['exam_id'=>$examId,'language_id'=>$languageId,'question_id'=>$questionId,'revision'=>$revision];
    }
    private function snapshot(string $workspace,int $source,string $actor,int $examId,int $languageId,int $after,?string $expectedRevision):array {
        foreach([$workspace,$actor] as $uuid)abort_unless(preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D',$uuid),422);
        abort_unless($examId>0&&$languageId>0&&$after>=0,422);
        abort_unless($expectedRevision===null||preg_match('/^[a-f0-9]{64}$/D',$expectedRevision),422);
        abort_unless($after===0||$expectedRevision!==null,422);
        return DB::transaction(function()use($workspace,$source,$actor,$examId,$languageId,$after,$expectedRevision){
            $w=DB::table('tech4learn_workspaces')->where('id',$workspace)->where('source_organization_id',$source)->lockForUpdate()->first();
            abort_unless($w&&$w->organization_id,404);$owner=(int)$w->organization_id;
            Organization::where('status','active')->lockForUpdate()->findOrFail($owner);
            abort_unless(!in_array('exams',json_decode($w->restrictions,true,512,JSON_THROW_ON_ERROR),true),403);
            $userId=DB::table('tech4learn_workspace_users')->where('workspace_id',$workspace)->where('local_id',$actor)->where('kind','staff')->value('external_id');
            User::findOrFail($userId);
            abort_unless(DB::table('organization_users')->where('organization_id',$owner)->where('user_id',$userId)->where('status',1)->lockForUpdate()->first()!==null,403);
            $exam=Exam::where('organization_id',$owner)->lockForUpdate()->findOrFail($examId);
            $language=Language::enabledForOrganization($owner)->whereHas('exams',fn($q)=>$q->where('exams.id',$examId))->lockForUpdate()->findOrFail($languageId);
            $count=$exam->questions()->count();abort_unless($count<=2000,422,'This paper exceeds the translation review size limit.');
            abort_unless($exam->questions()->where('questions.organization_id',$owner)->count()===$count,403);
            $questions=$exam->questions()->with(['langs'=>fn($q)=>$q->where('language_id',$languageId)->lockForUpdate()])->orderBy('questions.id')->lockForUpdate()->get();
            $translated=ExamLanguageTranslation::where('exam_id',$examId)->where('language_id',$languageId)->lockForUpdate()->first();
            $pivot=$exam->languages()->whereKey($languageId)->lockForUpdate()->firstOrFail()->pivot;
            $native=app(ExamTranslationService::class);$progress=$native->progress($exam,$language);
            $english=strtolower((string)$language->code)==='en';
            $revision=hash('sha256',json_encode([$exam->getAttributes(),$language->getAttributes(),$pivot->getAttributes(),$translated?->getAttributes(),$questions->map(fn($q)=>[$q->getAttributes(),$q->langs->map(fn($l)=>$l->getAttributes())->all()])->all()],JSON_THROW_ON_ERROR));
            abort_unless($expectedRevision===null||hash_equals($revision,$expectedRevision),409,'The source or translation changed. Restart the review.');
            $page=$questions->filter(fn($q)=>(int)$q->id>$after)->take(51);$more=$page->count()>50;$page=$page->take(50);
            $result=['is_source_language'=>$english,'exam_id'=>$examId,'language_id'=>$languageId,'language_name'=>(string)$language->name,'revision'=>$revision,'progress'=>array_intersect_key($progress,array_flip(['status','translated','remaining','total','exam_content_ready'])),'approved'=>$english||($pivot->translation_status==='ready'&&filled($pivot->translation_approved_at)),'source'=>$exam->only(['name','instruction','syllabus']),'translation'=>$english?$exam->only(['name','instruction','syllabus']):$translated?->only(['name','instruction','syllabus']),'items'=>$page->map(fn($q)=>['question_id'=>(int)$q->id,'source'=>$q->only(self::FIELDS),'translation'=>$english?$q->only(self::FIELDS):$q->langs->first()?->only(self::FIELDS),'stale_fields'=>$english?[]:$native->questionFieldsNeedingTranslation($q,$q->langs->first())])->values()->all(),'next'=>$more?(int)$page->last()->id:null];
            abort_unless(strlen(json_encode($result,JSON_THROW_ON_ERROR))<=2000000,422,'This translation review page is too large.');
            return $result;
        });
    }
}
