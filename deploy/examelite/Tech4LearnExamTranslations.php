<?php
namespace App\Services;

use App\Models\{Exam,ExamLanguageTranslation,Language,Organization,User};
use Illuminate\Support\Facades\DB;

/** Read-only staff review. Native fingerprints decide translation completeness. */
final class Tech4LearnExamTranslations
{
    private const FIELDS=['question','option1','option2','option3','option4','option5','option6','hint','explanation','fill_blank','si_answer1'];
    public function review(string $workspace,int $source,string $actor,int $examId,int $languageId,int $after=0,?string $expectedRevision=null):array {
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
            $result=['exam_id'=>$examId,'language_id'=>$languageId,'language_name'=>(string)$language->name,'revision'=>$revision,'progress'=>array_intersect_key($progress,array_flip(['status','translated','remaining','total','exam_content_ready'])),'approved'=>$english||($pivot->translation_status==='ready'&&filled($pivot->translation_approved_at)),'source'=>$exam->only(['name','instruction','syllabus']),'translation'=>$english?$exam->only(['name','instruction','syllabus']):$translated?->only(['name','instruction','syllabus']),'items'=>$page->map(fn($q)=>['question_id'=>(int)$q->id,'source'=>$q->only(self::FIELDS),'translation'=>$english?$q->only(self::FIELDS):$q->langs->first()?->only(self::FIELDS),'stale_fields'=>$english?[]:$native->questionFieldsNeedingTranslation($q,$q->langs->first())])->values()->all(),'next'=>$more?(int)$page->last()->id:null];
            abort_unless(strlen(json_encode($result,JSON_THROW_ON_ERROR))<=2000000,422,'This translation review page is too large.');
            return $result;
        });
    }
}
