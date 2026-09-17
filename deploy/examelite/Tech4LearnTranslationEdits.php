<?php
namespace App\Services;

use App\Models\{Exam,ExamLanguageTranslation,Language,QuestionLang};
use App\Http\Controllers\QuestionLangController;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

/** Translate the native question-language web form, preserving untouched fields. */
final class Tech4LearnTranslationEdits
{
    public const FIELDS=['question','option1','option2','option3','option4','option5','option6','hint','explanation','fill_blank','si_answer1'];
    public const EXAM_FIELDS=['name','instruction','syllabus'];
    /** Resolve only opaque images already in the locked translation's same field. */
    private function retainedImages(?QuestionLang $target,array $wording):array {
        $media=app(Tech4LearnQuestionMedia::class);
        foreach($wording as $field=>$html){
            if(!is_string($html)||!str_contains(strtolower($html),'<img'))continue;
            $sources=$media->sources((string)($target?->{$field}??''));
            $document=new \DOMDocument();$before=libxml_use_internal_errors(true);
            try{$document->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>',LIBXML_NONET);}
            finally{libxml_clear_errors();libxml_use_internal_errors($before);}
            abort_unless($document->getElementsByTagName('img')->length<=50,422);
            foreach($document->getElementsByTagName('img') as $image){
                $source=$image->getAttribute('src');
                if(!preg_match('/^t4l-media:([a-f0-9]{64})$/D',$source,$match)||!isset($sources[$match[1]]))
                    throw ValidationException::withMessages([$field=>'Only images already in this translated field may be retained. Reload the translation review.']);
                $image->setAttribute('src',$sources[$match[1]]);
            }
            $clean='';foreach($document->getElementsByTagName('body')->item(0)->childNodes as $node)$clean.=$document->saveHTML($node);
            abort_unless(strlen($clean)<=200000,422,'Translated wording is too large after restoring its images.');
            $wording[$field]=$clean;
        }
        return $wording;
    }
    /** The native engine has no manual exam-language form controller. */
    public function applyExam(Exam $exam,array $fields):void {
        $language=Language::enabledForOrganization($exam->organization_id)->whereHas('exams',fn($q)=>$q->where('exams.id',$exam->id))->findOrFail($fields['language_id']);
        abort_unless(strtolower((string)$language->code)!=='en',422,'Edit English wording in the exam editor.');
        $targets=ExamLanguageTranslation::where('exam_id',$exam->id)->where('language_id',$language->id)->lockForUpdate()->get();
        abort_unless($targets->count()<=1,409,'This exam has duplicate translations. Resolve them before editing.');
        $target=$targets->first();
        $values=array_replace(array_fill_keys(self::EXAM_FIELDS,null),$target?->only(self::EXAM_FIELDS)??[],$fields['wording']);
        validator($values,['name'=>'required|string|max:255','instruction'=>'nullable|string','syllabus'=>'nullable|string'])->validate();
        $native=app(ExamTranslationService::class);
        $fingerprints=$target?->source_field_fingerprints??[];
        // Preserve existing per-field evidence; a partial edit must never certify
        // untouched fields against a newer source (including legacy records).
        foreach($native->examFieldFingerprints($exam) as $field=>$hash){
            if(array_key_exists($field,$fields['wording'])||(blank($exam->{$field})&&blank($values[$field])))$fingerprints[$field]=$hash;
        }
        $target??=new ExamLanguageTranslation(['exam_id'=>$exam->id,'language_id'=>$language->id]);
        $target->fill($values+['translated_by'=>'MANUAL','source_fingerprint'=>$native->examFingerprint($exam),'source_field_fingerprints'=>$fingerprints]);
        $target->save();
        $exam->languages()->updateExistingPivot($language->id,['translation_status'=>'pending','translation_approved_at'=>null,'translation_approved_by'=>null]);
        app(ExamDocumentInvalidationService::class)->invalidateExam($exam);
    }
    public function apply(Exam $exam,array $fields,Request $request):void {
        $stored=null;$this->save($exam,$fields,$request,false,$stored);
    }
    public function applyImage(Exam $exam,array $fields,Request $request,?string &$stored):void {
        $this->save($exam,$fields,$request,true,$stored);
    }
    private function save(Exam $exam,array $fields,Request $request,bool $image,?string &$stored):void {
        $source=$exam->questions()->where('questions.organization_id',$exam->organization_id)->lockForUpdate()->findOrFail($fields['question_id']);
        $language=Language::enabledForOrganization($exam->organization_id)->whereHas('exams',fn($q)=>$q->where('exams.id',$exam->id))->findOrFail($fields['language_id']);
        abort_unless(strtolower((string)$language->code)!=='en',422,'Edit English wording in the question editor.');
        // The engine invalidates every paper referencing the question.
        abort_unless(!$source->exams()->where('exams.organization_id','<>',$exam->organization_id)->exists(),403);
        $targets=QuestionLang::where('question_id',$source->id)->where('language_id',$language->id)->lockForUpdate()->get();
        abort_unless($targets->count()<=1,409,'This question has duplicate translations. Resolve them before editing.');
        $target=$targets->first();$native=app(ExamTranslationService::class);
        if($image){
            abort_unless($target!==null,409,'Save and review translated wording before adding images.');
            $fields['wording']=app(Tech4LearnQuestionImageUpload::class)->applyTranslation($source,$target,$fields,$stored);
            foreach($fields['wording'] as $html)abort_unless(strlen($html)<=200000,422,'Translated wording is too large.');
        }else $fields['wording']=$this->retainedImages($target,$fields['wording']);
        $values=array_replace(array_fill_keys(self::FIELDS,null),$target?->only(self::FIELDS)??[],$fields['wording'],['language_id'=>(int)$language->id]);
        $stale=$native->questionFieldsNeedingTranslation($source,$target);
        $fingerprints=$target?->source_field_fingerprints??[];
        foreach($native->questionFieldFingerprints($source) as $field=>$hash){
            if(!in_array($field,$stale,true)||array_key_exists($field,$fields['wording'])||(blank($source->{$field})&&blank($values[$field]??$target?->{$field})))$fingerprints[$field]=$hash;
        }
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
