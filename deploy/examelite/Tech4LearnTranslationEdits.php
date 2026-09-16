<?php
namespace App\Services;

use App\Models\{Exam,Language,QuestionLang};
use App\Http\Controllers\QuestionLangController;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

/** Translate the native question-language web form, preserving untouched fields. */
final class Tech4LearnTranslationEdits
{
    public const FIELDS=['question','option1','option2','option3','option4','option5','option6','hint','explanation','fill_blank'];
    public function apply(Exam $exam,array $fields,Request $request):void {
        $source=$exam->questions()->where('questions.organization_id',$exam->organization_id)->lockForUpdate()->findOrFail($fields['question_id']);
        $language=Language::enabledForOrganization($exam->organization_id)->whereHas('exams',fn($q)=>$q->where('exams.id',$exam->id))->findOrFail($fields['language_id']);
        abort_unless(strtolower((string)$language->code)!=='en',422,'Edit English wording in the question editor.');
        // The engine invalidates every paper referencing the question.
        abort_unless(!$source->exams()->where('exams.organization_id','<>',$exam->organization_id)->exists(),403);
        $targets=QuestionLang::where('question_id',$source->id)->where('language_id',$language->id)->lockForUpdate()->get();
        abort_unless($targets->count()<=1,409,'This question has duplicate translations. Resolve them before editing.');
        $target=$targets->first();$native=app(ExamTranslationService::class);
        $stale=$native->questionFieldsNeedingTranslation($source,$target);
        $fingerprints=$target?->source_field_fingerprints??[];
        foreach($native->questionFieldFingerprints($source) as $field=>$hash){
            if(!in_array($field,$stale,true)||array_key_exists($field,$fields['wording']))$fingerprints[$field]=$hash;
        }
        $values=array_replace(array_fill_keys(self::FIELDS,null),$target?->only(self::FIELDS)??[],$fields['wording'],['language_id'=>(int)$language->id]);
        $request->replace($values);
        $controller=app(QuestionLangController::class);
        if($target)$controller->update($request,$target->id);else $controller->store($request,$source->id);
        if($request->session()->has('errors'))throw ValidationException::withMessages($request->session()->get('errors')->getBag('default')->messages());
        abort_unless($request->session()->has('success')&&!$request->session()->has('error'),422,'ExamElite could not save the translation.');
        $target=QuestionLang::where('question_id',$source->id)->where('language_id',$language->id)->sole();
        $target->source_field_fingerprints=$fingerprints;$target->source_fingerprint=$native->questionFingerprint($source);$target->saveQuietly();
        $paperIds=$source->exams()->where('exams.organization_id',$exam->organization_id)->pluck('exams.id');
        DB::table('exam_languages')->whereIn('exam_id',$paperIds)->where('language_id',$language->id)->update(['translation_status'=>'pending','translation_approved_at'=>null,'translation_approved_by'=>null]);
        app(ExamDocumentInvalidationService::class)->invalidateQuestion($source,true);
    }
}
