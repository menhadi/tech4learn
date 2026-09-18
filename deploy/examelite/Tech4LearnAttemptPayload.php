<?php
namespace App\Services;

/** Converts the native attempt view into a student-safe payload; never serialise its models. */
final class Tech4LearnAttemptPayload
{
    public function fromNativeView(array $view,int $tenant,int $student):array {
        $exam=$view['exam']??null;$result=$view['examResult']??null;
        abort_unless($exam&&$result&&(int)$exam->organization_id===$tenant&&(int)$result->organization_id===$tenant&&(int)$result->student_id===$student&&(int)$result->exam_id===(int)$exam->id,403);
        $language=(int)($view['selectedLanguageId']??0);
        $questions=[];
        foreach($exam->questions as $question){
            $stat=$view['examStats'][$question->id]??null;
            abort_unless($stat&&(int)$question->organization_id===$tenant&&(int)$stat->organization_id===$tenant&&(int)$stat->student_id===$student&&(int)$stat->exam_result_id===(int)$result->id&&(int)$stat->exam_id===(int)$exam->id&&(int)$stat->question_id===(int)$question->id,403);
            $type=$question->question_type;
            abort_unless(in_array($type,['multiple_choice_radio','multiple_choice_checkbox','true_false','fill_blank','nat','subjective'],true),422,'Unsupported native answer type.');
            $translated=$question->langs->firstWhere('language_id',$language);
            $content=[];
            foreach(['question','option1','option2','option3','option4','option5','option6','hint'] as $key)$content[$key]=(string)($translated?->$key??$question->$key??'');
            foreach($content as $text)abort_unless(!preg_match('/<(?:svg|math-field|iframe|video|audio|object|embed)\b/i',$text),422,'This paper needs media or formula display that is not available yet.');
            // Native templates use the same normaliser for legacy formulas/options.
            $normaliser=app(MathContentNormalizer::class);
            $media=app(Tech4LearnQuestionMedia::class);
            foreach($content as $key=>$text){
                $text=$normaliser->normalize($media->rewrite($text,true))['content'];
                $content[$key]=$media->restore(str_starts_with($key,'option')?$normaliser->repairOptionForDisplay($text):$normaliser->repairForDisplay($text));
            }
            $passageContent=$media->passageWording($question,$language);
            if($passageContent){
                abort_unless(!preg_match('/<(?:svg|math-field|iframe|video|audio|object|embed)\b/i',$passageContent['content']),422,'This paper needs media or formula display that is not available yet.');
                $text=$normaliser->normalize($media->rewrite($passageContent['content'],true))['content'];
                $passageContent['content']=$media->restore($normaliser->repairForDisplay($text));
            }
            $optionOrder=collect(range(1,6))->filter(fn($n)=>$content['option'.$n]!=='');
            if($exam->option_shuffle)$optionOrder=$optionOrder->shuffle();
            $questions[]=[
                'id'=>(int)$question->id,'number'=>(int)$stat->ques_no,'type'=>$type,
                'content'=>$content,'passage'=>$passageContent,
                'option_order'=>$optionOrder->values()->all(),
                'group_key'=>(string)($question->exam_group_key??'all'),'group_label'=>(string)($question->exam_group_label??''),
                'blank_count'=>$type==='fill_blank'?(int)$question->fill_blank_count:0,
                'marks'=>(float)$stat->marks,'negative_marks'=>(float)$stat->negative_marks,
                'answer'=>$question->prefilled_answer,'answered'=>(bool)$stat->answered,
                'review'=>(bool)$stat->review,'bookmark'=>(bool)$stat->bookmark,'opened'=>(bool)$stat->opened,
                'answer_locked'=>(bool)$question->answer_locked,'revision'=>app(Tech4LearnAttemptAnswers::class)->revision($stat),
            ];
        }
        return [
            'attempt_id'=>(int)$result->id,'exam_id'=>(int)$exam->id,'name'=>(string)$exam->name,
            'remaining_seconds'=>max(0,(int)($view['remainingTime']??0)),
            'time_limited'=>(float)$exam->duration>0||$exam->end_date!==null,
            'language_id'=>$language?:null,'questions'=>$questions,'tolerance_count'=>(int)$result->tolerance_count,
            'group_durations'=>$view['subjectDurations']??[],
            'settings'=>[
                'allow_answer_change'=>(bool)$exam->allow_answer_change,'option_shuffle'=>(bool)$exam->option_shuffle,
                'calculator_allowed'=>(bool)$exam->calculator_allowed,'grouping_mode'=>(string)$exam->grouping_mode,
                'timer_mode'=>(string)$exam->timer_mode,'browser_tolerance'=>(bool)$exam->browser_tolerance,
                'tolerance_count'=>(int)$exam->tolerance_count,'proctor'=>(bool)$exam->proctor,
            ],
        ];
    }
}
