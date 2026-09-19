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
            $media=app(Tech4LearnQuestionMedia::class);
            foreach($content as $key=>$text)$content[$key]=$this->display($text,str_starts_with($key,'option'));
            $passageContent=$media->passageWording($question,$language);
            if($passageContent){
                $passageContent['content']=$this->display($passageContent['content']);
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
                'attachment_asset'=>Tech4LearnAnswerAttachments::asset($stat),
                'attachments_enabled'=>$type==='subjective'&&\App\Support\SaasAccess::featureEnabled('ai_subjective_analysis'),
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
    /** Native conversion is authoritative; never silently discard an unconvertible formula. */
    private function display(string $html,bool $option=false):string {
        $message='This paper needs media or formula review before it can be taken.';
        abort_unless(strlen($html)<=2000000&&!preg_match('/<(?:math-field|iframe|video|audio|object|embed)\b/i',$html),422,$message);
        if(preg_match('/<(?:svg|mjx-)/i',$html)){
            $document=new \DOMDocument();$previous=libxml_use_internal_errors(true);
            try{$document->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);}
            finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
            $checked=[];
            abort_unless($document->getElementsByTagName('*')->length<=20000,422,$message);
            foreach($document->getElementsByTagName('*') as $element){
                $tag=strtolower($element->tagName);
                if($tag!=='svg'&&!str_starts_with($tag,'mjx-'))continue;
                $container=$element;$depth=0;
                while($container instanceof \DOMElement&&strtolower($container->tagName)!=='mjx-container'){
                    abort_unless(++$depth<=64,422,$message);$container=$container->parentNode;
                }
                abort_unless($container instanceof \DOMElement&&strtolower($container->tagName)==='mjx-container',422,$message);
                $identity=spl_object_id($container);if(isset($checked[$identity]))continue;
                $math=$container->getElementsByTagName('math');
                abort_unless($math->length===1&&trim($math->item(0)->textContent)!==''&&$container->getElementsByTagName('svg')->length<=1,422,$message);
                // This is the exact generated-wrapper shape consumed by the native normaliser.
                $parent=$math->item(0)->parentNode;$depth=0;
                while($parent instanceof \DOMElement&&$parent!==$container){
                    abort_unless(++$depth<=64,422,$message);
                    $class=strtolower($parent->getAttribute('class'));
                    abort_unless(str_starts_with(strtolower($parent->tagName),'mjx-')||str_contains($class,'mathjax-mathml')||str_contains($class,'mjx-assistive-mml'),422,$message);
                    $parent=$parent->parentNode;
                }
                abort_unless($parent===$container,422,$message);
                $checked[$identity]=$container;
            }
        }
        $normaliser=app(MathContentNormalizer::class);$media=app(Tech4LearnQuestionMedia::class);
        $result=$normaliser->normalize($media->rewrite($html,true));
        abort_unless(in_array($result['status']??null,['clean','converted','sanitized'],true)&&is_string($result['content']??null),422,$message);
        $text=$result['content'];
        return $media->restore($option?$normaliser->repairOptionForDisplay($text):$normaliser->repairForDisplay($text));
    }
}
