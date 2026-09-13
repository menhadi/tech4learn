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

    public function snapshot(Question $question):array {
        $fields=$question->only(self::FIELDS);
        $fields['group_ids']=$question->groups()->orderBy('groups.id')->pluck('groups.id')->map(fn($id)=>(int)$id)->all();
        $fields['tag_ids']=$question->tags()->orderBy('question_tags.id')->pluck('question_tags.id')->map(fn($id)=>(int)$id)->all();
        $fields['correct_answers']=$question->correct_option_indices??[];
        $fields['fill_blank_answers']=array_map(fn($b)=>['accepted_answers'=>implode(' | ',$b['answers']??[])],$question->fill_blank_config['blanks']??[]);
        foreach(['mode','value','min','max','tolerance'] as $key)$fields['nat_'.$key]=$question->nat_config[$key]??null;
        $fields['nat_mode']=$fields['nat_mode']??'exact';
        return ['id'=>(int)$question->id,'fields'=>$fields,'revision'=>$this->revision($question)];
    }
    private function revision(Question $question):string {
        $attributes=$question->getAttributes();ksort($attributes);
        return hash('sha256',json_encode([$attributes,$question->groups()->orderBy('groups.id')->pluck('groups.id')->all(),$question->tags()->orderBy('question_tags.id')->pluck('question_tags.id')->all()],JSON_THROW_ON_ERROR));
    }
    public function save(string $workspace,int $tenant,string $actor,int $id,array $fields,string $revision,string $requestId):array {
        abort_unless($id>0,422);
        if(array_diff(array_keys($fields),self::FIELDS))throw ValidationException::withMessages(['fields'=>'Unsupported question fields.']);
        if(strlen(json_encode($fields,JSON_THROW_ON_ERROR))>250000)throw ValidationException::withMessages(['fields'=>'Question is too large.']);
        foreach(['question','option1','option2','option3','option4','option5','option6','hint','explanation','si_answer1'] as $key){
            if(isset($fields[$key])&&is_string($fields[$key]))$fields[$key]=$this->formattedText($fields[$key],$key);
        }
        $fingerprint=hash('sha256',json_encode([$tenant,$actor,$id,$fields,$revision],JSON_THROW_ON_ERROR));
        return DB::transaction(function()use($workspace,$tenant,$actor,$id,$fields,$revision,$requestId,$fingerprint){
            $w=DB::table('tech4learn_workspaces')->where('id',$workspace)->lockForUpdate()->first();
            abort_unless($w && (int)$w->organization_id===$tenant,403);
            abort_unless(!in_array('questions',json_decode($w->restrictions,true,512,JSON_THROW_ON_ERROR),true),403);
            $prior=DB::table('tech4learn_authoring_requests')->where('workspace_id',$workspace)->where('request_id',$requestId)->first();
            if($prior){abort_unless(hash_equals($prior->fingerprint,$fingerprint),409,'Request ID already used.');return json_decode($prior->result,true,512,JSON_THROW_ON_ERROR);}
            $nativeId=DB::table('tech4learn_workspace_users')->where('workspace_id',$workspace)->where('local_id',$actor)->where('kind','staff')->value('external_id');
            $user=User::findOrFail($nativeId);
            abort_unless(DB::table('organization_users')->where('organization_id',$tenant)->where('user_id',$user->id)->where('status',1)->exists(),403);
            $question=Question::where('organization_id',$tenant)->lockForUpdate()->findOrFail($id);
            abort_unless(hash_equals($this->revision($question),$revision),409,'Question changed. Reload before saving.');
            $values=array_replace($this->snapshot($question)['fields'],$fields);
            // The native controller accepts its web form. Give it a private request/session,
            // and translate its redirect feedback into an atomic API outcome.
            $result=$this->invoke($tenant,$user,$values,$question);
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
    private function invoke(int $tenant,User $user,array $fields,Question $question):array {
        $organisation=Organization::where('status','active')->findOrFail($tenant);
        $app=app();$oldRequest=$app->make('request');$oldRedirect=$app->make('redirect');$guard=Auth::guard('web');$oldUser=$guard->user();
        $session=new Store('t4l-authoring',new ArraySessionHandler(5));$session->start();
        $request=Request::create('https://'.$organisation->domain.'/questions','POST',$fields);
        $request->setLaravelSession($session);$request->setUserResolver(fn()=>$user);
        $redirect=new Redirector($app->make('url'));$redirect->setSession($session);
        $app->instance('request',$request);$app->instance('redirect',$redirect);$guard->setUser($user);Tenant::clear();
        try {
            abort_unless((int)Tenant::resolve($organisation->domain)->id===$tenant,403);
            $controller=app(QuestionController::class);
            $controller->update($request,$question);
            if($session->has('errors'))throw ValidationException::withMessages($session->get('errors')->getBag('default')->messages());
            if(!$session->has('success') || $session->has('error'))throw ValidationException::withMessages(['question'=>'ExamElite could not save this question. Check its fields and related records.']);
            return $this->snapshot($question->fresh());
        }finally{
            $oldUser?$guard->setUser($oldUser):$guard->forgetUser();
            $app->instance('request',$oldRequest);$app->instance('redirect',$oldRedirect);Tenant::clear();
            $session->invalidate();
        }
    }
}
