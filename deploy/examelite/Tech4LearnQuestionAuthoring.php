<?php
namespace App\Services;

use App\Models\{Question,Organization,User};
use App\Support\Tenant;
use App\Http\Controllers\QuestionController;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Session\{Store,ArraySessionHandler};
use Illuminate\Support\Facades\{Auth,DB};
use Illuminate\Validation\ValidationException;

/** Adapts the installed native controller; answer validation and persistence stay in ExamElite. */
final class Tech4LearnQuestionAuthoring
{
    public const FIELDS=['qtype_id','subject_id','question_section_id','topic_id','stopic_id','diff_id','passage_id','language_id',
        'question','option1','option2','option3','option4','option5','option6','marks','negative_marks','scoring_policy',
        'hint','explanation','answer','true_false','fill_blank','fill_blank_answers','nat_mode','nat_value','nat_min','nat_max',
        'nat_tolerance','status','correct_answers','si_answer1','group_ids','tag_ids'];

    public const EXAM_FIELDS=['name','test_type','test_subject_id','test_topic_id','test_stopic_id','exam_year','exam_session','duration','attempt_count','passing_percentage','display_order','instruction','show_instruction','syllabus','start_date','end_date','groups','packages','language_ids','category_level_1','category_level_2','offline_enabled','online_attempt_enabled','frontend_visible','omr_enabled','browser_tolerance','random_question','result_after_finish','option_shuffle','allow_answer_change','grouping_mode','use_group_timer','timer_mode','is_subject_timer','negative_marking','proctor','calculator_allowed','tolerance_count'];
    public function feature(string $kind):string{return $kind==='questions'?'questions':($kind==='exams'?'exams':'subjects');}
    public function newExam():array {
        $fields=array_fill_keys(['offline_enabled','online_attempt_enabled','frontend_visible','omr_enabled','browser_tolerance','random_question','option_shuffle','use_group_timer','is_subject_timer','negative_marking','proctor','calculator_allowed'],false);
        return ['id'=>0,'revision'=>'new','fields'=>array_merge($fields,['name'=>'','test_type'=>'full_length','duration'=>60,'attempt_count'=>1,'passing_percentage'=>null,'display_order'=>0,'groups'=>[],'packages'=>[],'language_ids'=>[],'grouping_mode'=>'subject','timer_mode'=>'none','result_after_finish'=>true,'allow_answer_change'=>true,'show_instruction'=>true,'tolerance_count'=>0]),'test_types'=>\App\Models\Exam::testTypeLabels(),'timezone'=>config('app.timezone','UTC')];
    }
    public function definition(string $kind):array {
        $definitions=[
          'exams'=>[\App\Models\Exam::class,\App\Http\Controllers\ExamController::class,self::EXAM_FIELDS,'exam'],
          'questions'=>[Question::class,QuestionController::class,self::FIELDS,'question'],
          'groups'=>[\App\Models\Group::class,\App\Http\Controllers\GroupController::class,['group_name','display_order'],'group'],
          'subjects'=>[\App\Models\Subject::class,\App\Http\Controllers\SubjectController::class,['subject_name','group_ids','category_ids'],'subject'],
          'topics'=>[\App\Models\Topic::class,\App\Http\Controllers\TopicController::class,['name','group_id','subject_id','display_order'],'topic'],
          'subtopics'=>[\App\Models\Stopic::class,\App\Http\Controllers\StopicController::class,['name','group_id','subject_id','topic_id','display_order'],'stopic'],
          'sections'=>[\App\Models\QuestionSection::class,\App\Http\Controllers\SectionController::class,['name','group_ids','display_order','status'],'section'],
        ];
        abort_unless(isset($definitions[$kind]),404);return $definitions[$kind];
    }
    public function owned(string $kind,int $tenant){
        [$model]=$this->definition($kind);$query=$model::query();
        if(in_array($kind,['topics','subtopics'],true))return $query->whereHas('subject',fn($q)=>$q->where('organization_id',$tenant))->whereHas('group',fn($q)=>$q->where('organization_id',$tenant));
        return $query->where('organization_id',$tenant);
    }
    public function record(string $kind,\Illuminate\Database\Eloquent\Model $model):array {
        if($kind==='questions')return $this->snapshot($model);
        $fields=$model->only(array_values(array_intersect($this->definition($kind)[2],array_keys($model->getAttributes()))));
        if(in_array($kind,['subjects','sections'],true))$fields['group_ids']=$model->groups()->orderBy('groups.id')->pluck('groups.id')->map(fn($id)=>(int)$id)->all();
        if($kind==='exams'){
            foreach(['groups'=>'groups','packages'=>'packages','language_ids'=>'languages'] as $field=>$relation)$fields[$field]=$model->$relation()->orderBy($relation.'.id')->pluck($relation.'.id')->map(fn($id)=>(int)$id)->all();
            foreach(['start_date','end_date'] as $date)$fields[$date]=$model->getRawOriginal($date);
            $fields['use_group_timer']=($fields['timer_mode']??'none')!=='none';
        }
        $relations=$kind==='exams'?[$model->questions()->orderBy('questions.id')->pluck('questions.id')->all(),$model->sections()->orderBy('id')->get()->toArray()]:[];
        $raw=$model->getAttributes();ksort($raw);
        return array_merge(['id'=>(int)$model->id,'fields'=>$fields,'revision'=>hash('sha256',json_encode([$raw,$fields,$relations],JSON_THROW_ON_ERROR))],$kind==='exams'?['test_types'=>\App\Models\Exam::testTypeLabels(),'timezone'=>config('app.timezone','UTC')]:[]);
    }
    public function snapshot(Question $question):array {
        $fields=$question->only(self::FIELDS);
        $fields['group_ids']=$question->groups()->orderBy('groups.id')->pluck('groups.id')->map(fn($id)=>(int)$id)->all();
        $fields['tag_ids']=$question->tags()->orderBy('question_tags.id')->pluck('question_tags.id')->map(fn($id)=>(int)$id)->all();
        $fields['correct_answers']=$question->correct_option_indices??[];
        $fields['fill_blank_answers']=array_map(fn($b)=>['accepted_answers'=>implode(' | ',$b['answers']??[])],$question->fill_blank_config['blanks']??[]);
        foreach(['mode','value','min','max','tolerance'] as $key)$fields['nat_'.$key]=$question->nat_config[$key]??null;
        $fields['nat_mode']=$fields['nat_mode']??'exact';
        return ['id'=>(int)$question->id,'fields'=>$fields,'type'=>$question->qtype?->type,'type_name'=>$question->qtype?->question_type,'revision'=>$this->revision($question)];
    }
    private function revision(Question $question):string {
        $attributes=$question->getAttributes();ksort($attributes);
        return hash('sha256',json_encode([$attributes,$question->groups()->orderBy('groups.id')->pluck('groups.id')->all(),$question->tags()->orderBy('question_tags.id')->pluck('question_tags.id')->all()],JSON_THROW_ON_ERROR));
    }
    public function save(string $workspace,int $tenant,string $actor,int $id,array $fields,string $revision,string $requestId,string $kind='questions',?string $action=null):array {
        [$modelClass,$controllerClass,$allowedFields]=$this->definition($kind);
        abort_unless($id>=0,422);
        if($action!==null){abort_unless($kind==='exams'&&$id>0&&in_array($action,['add-questions','remove-questions'],true),422);$allowedFields=['question_ids'];}

        if(array_diff(array_keys($fields),$allowedFields))throw ValidationException::withMessages(['fields'=>'Unsupported question fields.']);
        if(strlen(json_encode($fields,JSON_THROW_ON_ERROR))>250000)throw ValidationException::withMessages(['fields'=>'Question is too large.']);
        if($kind==='questions')foreach(['question','option1','option2','option3','option4','option5','option6','hint','explanation','si_answer1'] as $key){
            if(isset($fields[$key])&&is_string($fields[$key]))$fields[$key]=$this->formattedText($fields[$key],$key);
        }
        if($kind==='exams')foreach(['instruction','syllabus'] as $key)if(isset($fields[$key])&&is_string($fields[$key]))$fields[$key]=$this->formattedText($fields[$key],$key);
        $fingerprint=hash('sha256',json_encode([$kind,$action,$tenant,$actor,$id,$fields,$revision],JSON_THROW_ON_ERROR));
        return DB::transaction(function()use($workspace,$tenant,$actor,$id,$fields,$revision,$requestId,$fingerprint,$kind,$action){
            $w=DB::table('tech4learn_workspaces')->where('id',$workspace)->lockForUpdate()->first();
            abort_unless($w && (int)$w->organization_id===$tenant,403);
            abort_unless(!in_array($this->feature($kind),json_decode($w->restrictions,true,512,JSON_THROW_ON_ERROR),true),403);
            $prior=DB::table('tech4learn_authoring_requests')->where('workspace_id',$workspace)->where('request_id',$requestId)->first();
            if($prior){abort_unless(hash_equals($prior->fingerprint,$fingerprint),409,'Request ID already used.');return json_decode($prior->result,true,512,JSON_THROW_ON_ERROR);}
            $nativeId=DB::table('tech4learn_workspace_users')->where('workspace_id',$workspace)->where('local_id',$actor)->where('kind','staff')->value('external_id');
            $user=User::findOrFail($nativeId);
            abort_unless(DB::table('organization_users')->where('organization_id',$tenant)->where('user_id',$user->id)->where('status',1)->exists(),403);
            $question=$id?$this->owned($kind,$tenant)->lockForUpdate()->findOrFail($id):null;
            if($question)abort_unless(hash_equals($this->record($kind,$question)['revision'],$revision),409,'Question changed. Reload before saving.');
            else abort_unless($revision==='new',422);
            if($action!==null){
                $ids=$fields['question_ids']??null;
                if(!is_array($ids)||count($ids)<1||count($ids)>100||count(array_filter($ids,fn($v)=>is_int($v)&&$v>0))!==count($ids))throw ValidationException::withMessages(['question_ids'=>'Select between 1 and 100 questions.']);
                $ids=array_values(array_unique($ids));
                abort_unless(Question::where('organization_id',$tenant)->whereIn('id',$ids)->count()===count($ids),422);
                $fields['question_ids']=$ids;
            }
            $values=$question?array_replace($this->record($kind,$question)['fields'],$fields):$fields;
            // The native controller accepts its web form. Give it a private request/session,
            // and translate its redirect feedback into an atomic API outcome.
            $result=$this->invoke($tenant,$user,$action!==null?$fields:$values,$question,$kind,$action);
            DB::table('tech4learn_authoring_requests')->insert(['workspace_id'=>$workspace,'request_id'=>$requestId,'fingerprint'=>$fingerprint,'result'=>json_encode($result,JSON_THROW_ON_ERROR),'created_at'=>now()]);
            return $result;
        });
    }
    private function formattedText(string $html,string $key):string {
        // Only the basic editor's formatting is accepted here. Existing media and
        // formula fields remain untouched unless a supported editor explicitly changes them.
        $dom=new \DOMDocument();$previous=libxml_use_internal_errors(true);
        try{$dom->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>',LIBXML_NONET);}
        finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
        $body=$dom->getElementsByTagName('body')->item(0);if(!$body)return '';
        $allowed=['p','div','br','b','strong','i','em','u','s','sub','sup','ul','ol','li','span','table','thead','tbody','tr','td','th'];
        foreach($body->getElementsByTagName('*') as $element){
            if(!in_array(strtolower($element->tagName),$allowed,true))throw ValidationException::withMessages([$key=>'This content requires the native media or formula editor.']);
            foreach($element->attributes as $attribute){
                if(!in_array($attribute->name,['colspan','rowspan'],true)||!preg_match('/^[1-9][0-9]{0,2}$/D',$attribute->value))throw ValidationException::withMessages([$key=>'Unsupported formatting. Paste plain text or use the question editor.']);
            }
        }
        $clean='';foreach($body->childNodes as $node){if($node instanceof \DOMElement||$node instanceof \DOMText)$clean.=$dom->saveHTML($node);}
        return $clean;
    }
    private function invoke(int $tenant,User $user,array $fields,?\Illuminate\Database\Eloquent\Model $question,string $kind,?string $action=null):array {
        [$modelClass,$controllerClass,,$parameter]=$this->definition($kind);
        $organisation=Organization::where('status','active')->findOrFail($tenant);
        $app=app();$oldRequest=$app->make('request');$oldRedirect=$app->make('redirect');$guard=Auth::guard('web');$oldUser=$guard->user();
        $session=new Store('t4l-authoring',new ArraySessionHandler(5));$session->start();
        $request=Request::create('https://'.$organisation->domain.'/'.$kind,'POST',$fields);
        $request->setLaravelSession($session);$request->setUserResolver(fn()=>$user);
        $redirect=new Redirector($app->make('url'));$redirect->setSession($session);
        $app->instance('request',$request);$app->instance('redirect',$redirect);$guard->setUser($user);Tenant::clear();
        // Capture the native create result without guessing the latest ID (other
        // ExamElite requests may be writing concurrently). Restore the dispatcher.
        $originalDispatcher=Question::getEventDispatcher();
        $dispatcher=$originalDispatcher?clone $originalDispatcher:new \Illuminate\Events\Dispatcher($app);
        $created=[];
        if(!$question){
            Question::setEventDispatcher($dispatcher);
            $dispatcher->listen('eloquent.created: '.$modelClass,function($model)use(&$created){$created[]=$model;});
        }
        try {
            abort_unless((int)Tenant::resolve($organisation->domain)->id===$tenant,403);
            $controller=app($controllerClass);
            $arguments=['request'=>$request];if($question)$arguments[$parameter]=$question;
            $method=$action==='add-questions'?'bulkAddQuestions':($action==='remove-questions'?'removeQuestions':($question?'update':'store'));
            $response=$app->call([$controller,$method],$arguments);
            if($session->has('errors'))throw ValidationException::withMessages($session->get('errors')->getBag('default')->messages());
            $jsonSuccess=$action!==null&&$response instanceof \Illuminate\Http\JsonResponse&&$response->getStatusCode()<300&&($response->getData(true)['success']??false)===true;
            if((!$session->has('success')&&!$jsonSuccess) || $session->has('error'))throw ValidationException::withMessages(['question'=>'ExamElite could not save this question. Check its fields and related records.']);
            if(!$question){abort_unless(count($created)===1,500,'Native create did not return one question.');$question=$created[0];}
            return $this->record($kind,$this->owned($kind,$tenant)->findOrFail($question->id));
        }finally{
            if(!$originalDispatcher)Question::unsetEventDispatcher();else Question::setEventDispatcher($originalDispatcher);
            $oldUser?$guard->setUser($oldUser):$guard->forgetUser();
            $app->instance('request',$oldRequest);$app->instance('redirect',$oldRedirect);Tenant::clear();
            $session->invalidate();
        }
    }
}
