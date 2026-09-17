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
    public const EXAM_ACTIONS=[
        'add-questions'=>['question_ids'],'remove-questions'=>['question_ids'],
        'create-section'=>['name','display_order','duration'],
        'update-section'=>['section_id','name','display_order','duration'],
        'remove-section'=>['section_id'],
        'assign-section'=>['question_ids','question_section_id'],
        'subject-timers'=>['subject_ids','durations'],
        'set-status'=>['status'],
        'set-result-status'=>['result_after_finish'],
        'generate-document'=>['package_id','language_id','document_type'],
        'approve-translation'=>['language_id','translation_revision'],
        'refresh-translation'=>['language_id','translation_revision'],
        'save-question-translation'=>['language_id','translation_revision','question_id','wording'],
        'save-exam-translation'=>['language_id','translation_revision','wording'],
    ];
    public const FIELDS=['qtype_id','subject_id','question_section_id','topic_id','stopic_id','diff_id','passage_id','language_id',
        'question','option1','option2','option3','option4','option5','option6','marks','negative_marks','scoring_policy',
        'hint','explanation','answer','true_false','fill_blank','fill_blank_answers','nat_mode','nat_value','nat_min','nat_max',
        'nat_tolerance','status','correct_answers','si_answer1','group_ids','tag_ids'];

    public const EXAM_FIELDS=['name','test_type','test_subject_id','test_topic_id','test_stopic_id','exam_year','exam_session','duration','attempt_count','passing_percentage','display_order','instruction','show_instruction','syllabus','start_date','end_date','groups','packages','language_ids','category_level_1','category_level_2','offline_enabled','online_attempt_enabled','frontend_visible','omr_enabled','browser_tolerance','random_question','result_after_finish','option_shuffle','allow_answer_change','grouping_mode','use_group_timer','timer_mode','is_subject_timer','negative_marking','proctor','calculator_allowed','tolerance_count'];
    public const PACKAGE_FIELDS=['name','description','slug','package_type','amount','discounted_amount','auto_enroll_on_registration','status','expiry_days','display_order','group_ids','tag_ids','category_level_1','category_level_2','show_pdf_download','show_solution_pdf_download','pdf_title_text','pdf_header_text','pdf_footer_text','pdf_watermark_text','solution_pdf_title_text','solution_pdf_header_text','solution_pdf_footer_text','solution_pdf_watermark_text','flashcards_enabled','guest_flashcards_enabled','ai_flashcard_generation_enabled','meta_title','meta_description','meta_keywords','canonical_url','og_title','og_description','og_image','robots_meta','seo_schema'];
    public function feature(string $kind):string{return $kind==='questions'?'questions':($kind==='exams'?'exams':'subjects');}
    public function newExam():array {
        $fields=array_fill_keys(['offline_enabled','online_attempt_enabled','frontend_visible','omr_enabled','browser_tolerance','random_question','option_shuffle','use_group_timer','is_subject_timer','negative_marking','proctor','calculator_allowed'],false);
        return ['id'=>0,'revision'=>'new','fields'=>array_merge($fields,['name'=>'','test_type'=>'full_length','duration'=>60,'attempt_count'=>1,'passing_percentage'=>null,'display_order'=>0,'groups'=>[],'packages'=>[],'language_ids'=>[],'grouping_mode'=>'subject','timer_mode'=>'none','result_after_finish'=>true,'allow_answer_change'=>true,'show_instruction'=>true,'tolerance_count'=>0]),'test_types'=>\App\Models\Exam::testTypeLabels(),'timezone'=>config('app.timezone','UTC')];
    }
    public function definition(string $kind):array {
        if($kind==='subcategories'){
            $definition=$this->definition('categories');
            $definition[2]=array_values(array_diff($definition[2],['group_ids','group_orders']));
            $definition[2][]='parent_id';return $definition;
        }
        $definitions=[
          'packages'=>[\App\Models\Package::class,\App\Http\Controllers\PackageController::class,self::PACKAGE_FIELDS,'package'],
          'languages'=>[\App\Models\Language::class,\App\Http\Controllers\LanguageController::class,['master_language_id','value1','value2'],'language'],
          'categories'=>[\App\Models\Category::class,\App\Http\Controllers\CategoryController::class,['title','description','status','display_order','group_ids','group_orders','show_in_header','header_display_order','meta_title','meta_description','meta_keywords','canonical_url','og_title','og_description','og_image','robots_meta','seo_schema'],'category'],
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
        if($kind==='categories')$query->whereNull('parent_id');
        if($kind==='subcategories')$query->whereNotNull('parent_id')->whereHas('parent',fn($q)=>$q->where('organization_id',$tenant)->whereNull('parent_id'));
        if(in_array($kind,['topics','subtopics'],true))return $query->whereHas('subject',fn($q)=>$q->where('organization_id',$tenant))->whereHas('group',fn($q)=>$q->where('organization_id',$tenant));
        return $query->where('organization_id',$tenant);
    }
    public function record(string $kind,\Illuminate\Database\Eloquent\Model $model):array {
        if($kind==='questions')return $this->snapshot($model);
        $fields=$model->only(array_values(array_intersect($this->definition($kind)[2],array_keys($model->getAttributes()))));
        if($kind==='languages')$fields=array_merge($fields,['name'=>$model->name,'code'=>$model->code,'is_enabled'=>(bool)$model->is_enabled]);
        if($kind==='packages'){
            $fields['group_ids']=$model->groups()->orderBy('groups.id')->pluck('groups.id')->map(fn($id)=>(int)$id)->all();
            $fields['tag_ids']=$model->tags()->orderBy('package_tags.id')->pluck('package_tags.id')->map(fn($id)=>(string)$id)->all();
        }
        if($kind==='categories'){
            $groups=$model->groups()->orderBy('groups.id')->get();
            $fields['group_ids']=$groups->map(fn($g)=>(int)$g->id)->all();
            $fields['group_orders']=$groups->mapWithKeys(fn($g)=>[$g->id=>$g->pivot->display_order])->all();
        }
        if(in_array($kind,['subjects','sections'],true))$fields['group_ids']=$model->groups()->orderBy('groups.id')->pluck('groups.id')->map(fn($id)=>(int)$id)->all();
        if($kind==='exams'){
            foreach(['groups'=>'groups','packages'=>'packages','language_ids'=>'languages'] as $field=>$relation)$fields[$field]=$model->$relation()->orderBy($relation.'.id')->pluck($relation.'.id')->map(fn($id)=>(int)$id)->all();
            foreach(['start_date','end_date'] as $date)$fields[$date]=$model->getRawOriginal($date);
            $fields['use_group_timer']=($fields['timer_mode']??'none')!=='none';
        }
        $relations=$kind==='exams'?[DB::table('exam_questions')->where('exam_id',$model->id)->orderBy('question_id')->get(['question_id','exam_section_id'])->toArray(),$model->sections()->orderBy('id')->get()->toArray(),$model->subjectDurations()->orderBy('subject_id')->get()->toArray()]:[];
        if($kind==='packages')$relations=DB::table('exam_packages')->where('package_id',$model->id)->orderBy('exam_id')->get(['exam_id','display_order'])->toArray();
        $raw=$model->getAttributes();ksort($raw);
        return array_merge(['id'=>(int)$model->id,'fields'=>$fields,'revision'=>hash('sha256',json_encode([$raw,$fields,$relations],JSON_THROW_ON_ERROR))],$kind==='packages'?['photo_asset'=>filled($model->photo)?hash('sha256',trim((string)$model->photo)):null]:[],$kind==='exams'?['test_types'=>\App\Models\Exam::testTypeLabels(),'timezone'=>config('app.timezone','UTC'),'status'=>$model->status,'sections'=>$relations[1],'subject_durations'=>$relations[2],'paper_subjects'=>\App\Models\Subject::where('organization_id',$model->organization_id)->whereIn('id',$model->questions()->select('questions.subject_id'))->orderBy('id')->get(['id','subject_name'])->map(fn($s)=>['id'=>(int)$s->id,'name'=>strip_tags($s->subject_name)])->values()->all()]:[]);
    }
    public function snapshot(Question $question):array {
        $fields=$question->only(self::FIELDS);
        $fields['group_ids']=$question->groups()->orderBy('groups.id')->pluck('groups.id')->map(fn($id)=>(int)$id)->all();
        $fields['tag_ids']=$question->tags()->orderBy('question_tags.id')->pluck('question_tags.id')->map(fn($id)=>(int)$id)->all();
        $fields['correct_answers']=$question->correct_option_indices??[];
        $fields['fill_blank_answers']=array_map(fn($b)=>['accepted_answers'=>implode(' | ',$b['answers']??[])],$question->fill_blank_config['blanks']??[]);
        foreach(['mode','value','min','max','tolerance'] as $key)$fields['nat_'.$key]=$question->nat_config[$key]??null;
        $fields['nat_mode']=$fields['nat_mode']??'exact';
        $preview=[];$media=app(Tech4LearnQuestionMedia::class);
        foreach(Tech4LearnQuestionMedia::AUTHORING_FIELDS as $field)$preview[$field]=$media->rewrite((string)($fields[$field]??''));
        return ['id'=>(int)$question->id,'fields'=>$fields,'preview_fields'=>$preview,'type'=>$question->qtype?->type,'type_name'=>$question->qtype?->question_type,'revision'=>$this->revision($question)];
    }
    private function revision(Question $question):string {
        $attributes=$question->getAttributes();ksort($attributes);
        return hash('sha256',json_encode([$attributes,$question->groups()->orderBy('groups.id')->pluck('groups.id')->all(),$question->tags()->orderBy('question_tags.id')->pluck('question_tags.id')->all()],JSON_THROW_ON_ERROR));
    }
    /** Dedicated-credential caller supplies the configured central owner, never a browser organisation ID. */
    public function saveCentralQuestion(int $central,string $actor,int $id,array $fields,string $revision,string $requestId,?string $action=null):array {
        return $this->saveCentralRecord($central,$actor,$id,$fields,$revision,$requestId,'questions',$action);
    }
    public function saveCentralTaxonomy(int $central,string $actor,string $kind,int $id,array $fields,string $revision,string $requestId):array {
        abort_unless(in_array($kind,['groups','subjects','topics','subtopics','sections','categories','subcategories','packages','exams'],true),422);
        return $this->saveCentralRecord($central,$actor,$id,$fields,$revision,$requestId,$kind);
    }
    public function saveCentralPackageImage(int $central,string $actor,int $id,array $fields,string $revision,string $requestId):array {
        return $this->saveCentralRecord($central,$actor,$id,$fields,$revision,$requestId,'packages','set-image');
    }
    public function deleteCentralCategory(int $central,string $actor,string $kind,int $id,string $revision,string $requestId):array {
        abort_unless(in_array($kind,['categories','subcategories'],true)&&$id>0,422);
        return $this->saveCentralRecord($central,$actor,$id,[],$revision,$requestId,$kind,'delete-category');
    }
    public function saveCentralExamAction(int $central,string $actor,int $id,array $fields,string $revision,string $requestId,string $action):array {
        return $this->saveCentralRecord($central,$actor,$id,$fields,$revision,$requestId,'exams',$action);
    }
    private function saveCentralRecord(int $central,string $actor,int $id,array $fields,string $revision,string $requestId,string $kind,?string $action=null):array {
        $categoryDelete=$action==='delete-category'&&in_array($kind,['categories','subcategories'],true)&&$id>0;
        abort_unless($central>0&&$id>=0&&($categoryDelete||count($fields)>0),422);
        abort_unless($categoryDelete||$action===null||($id>0&&(($action==='set-image'&&in_array($kind,['questions','packages'],true))||($kind==='exams'&&in_array($action,['add-questions','remove-questions','create-section','update-section','remove-section','assign-section','subject-timers','set-status','set-result-status','approve-translation','refresh-translation','save-question-translation','save-exam-translation','generate-document'],true)))),422);
        $imageAction=$action==='set-image';
        foreach([$actor,$requestId] as $uuid)abort_unless(preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D',$uuid),422);
        abort_unless($revision==='new'||preg_match('/^[a-f0-9]{64}$/D',$revision),422);
        if(array_diff(array_keys($fields),$categoryDelete?[]:($imageAction?($kind==='packages'?['image','asset','remove']:['field','image','asset','remove']):($action!==null?self::EXAM_ACTIONS[$action]:$this->definition($kind)[2])))||strlen(json_encode($fields,JSON_THROW_ON_ERROR))>($imageAction?710000:250000))
            throw ValidationException::withMessages(['fields'=>'Unsupported or oversized question fields.']);
        foreach(Tech4LearnQuestionMedia::AUTHORING_FIELDS as $key)if(isset($fields[$key])&&is_string($fields[$key]))$fields[$key]=$this->formattedText($fields[$key],$key,$kind==='questions'&&$id>0);
        if($kind==='exams')foreach(['instruction','syllabus'] as $key)if(isset($fields[$key])&&is_string($fields[$key]))$fields[$key]=$this->formattedText($fields[$key],$key);
        if($kind==='packages'&&isset($fields['description'])&&is_string($fields['description']))$fields['description']=$this->formattedText($fields['description'],'description');
        $this->translationFields($fields,$action);
        $identity=[$actor,$id,$fields,$revision];if($action!==null)$identity[]=$action;if($kind!=='questions')$identity[]=$kind;
        $fingerprint=hash('sha256',json_encode($identity,JSON_THROW_ON_ERROR));
        $storedImage=null;
        try{return DB::transaction(function()use($central,$actor,$id,$fields,$revision,$requestId,$fingerprint,$imageAction,$kind,$action,&$storedImage){
            // Serialise provisioning and retries within this actual central organisation.
            Organization::where('status','active')->lockForUpdate()->findOrFail($central);
            $prior=DB::table('tech4learn_central_requests')->where('organization_id',$central)->where('request_id',$requestId)->first();
            $mapping=DB::table('tech4learn_central_users')->where('organization_id',$central)->where('local_id',$actor)->first();
            if(!$mapping){
                abort_unless(!$prior,403,'Central author mapping is unavailable.');
                $key=hash('sha256','central:'.$central.':'.$actor);
                $user=User::create(['name'=>'Tech4Learn central author','username'=>'t4lc-'.$key,'email'=>$key.'@tech4learn.invalid','password'=>\Illuminate\Support\Facades\Hash::make(bin2hex(random_bytes(32))),'ugroup_id'=>0,'status'=>1,'is_platform_admin'=>false]);
                DB::table('organization_users')->insert(['organization_id'=>$central,'user_id'=>$user->id,'role'=>'owner','status'=>1,'created_at'=>now(),'updated_at'=>now()]);
                DB::table('tech4learn_central_users')->insert(['organization_id'=>$central,'local_id'=>$actor,'external_id'=>$user->id]);
            }else $user=User::where('status',1)->where('is_platform_admin',false)->lockForUpdate()->findOrFail($mapping->external_id);
            abort_unless(DB::table('organization_users')->where('organization_id',$central)->where('user_id',$user->id)->where('status',1)->lockForUpdate()->first()!==null,403);
            if($prior){abort_unless(hash_equals($prior->fingerprint,$fingerprint),409,'Request ID already used.');return json_decode($prior->result,true,512,JSON_THROW_ON_ERROR);}
            $question=$id?$this->owned($kind,$central)->lockForUpdate()->findOrFail($id):null;
            if($question)abort_unless(hash_equals($this->record($kind,$question)['revision'],$revision),409,'Central record changed. Reload before saving.');
            else abort_unless($revision==='new',422);
            if($imageAction)$fields=app($kind==='packages'?Tech4LearnPackageImageUpload::class:Tech4LearnQuestionImageUpload::class)->apply($question,$fields,$storedImage);
            elseif($kind==='questions'&&$question)$this->validateRetainedImages($question,$fields);
            $values=$question?array_replace($this->record($kind,$question)['fields'],$fields):$fields;
            if($kind==='packages'){
                abort_unless(($values['package_type']??null)==='free'&&(!$question||$question->package_type==='free'),422,'Central paid package authoring is not available through this adapter.');
                $this->validatePackageTags($central,$values);
            }
            if($kind==='exams')$this->validateExamAction($central,$question,$fields,$action);
            if($action==='generate-document')$this->validateDocumentSelection($central,$id,$fields);
            if(in_array($action,['approve-translation','refresh-translation','save-question-translation','save-exam-translation'],true)){
                abort_unless(is_int($fields['language_id']??null)&&$fields['language_id']>0&&is_string($fields['translation_revision']??null),422);
                $review=app(Tech4LearnExamTranslations::class)->centralReview($central,$id,$fields['language_id'],0,$fields['translation_revision']);
                $this->validateTranslationReview($question,$central,$review,$action);
            }
            $result=$this->invoke($central,$user,$action!==null&&!$imageAction?$fields:$values,$question,$kind,$imageAction?($kind==='packages'?'set-package-image':null):$action);
            DB::table('tech4learn_central_requests')->insert(['organization_id'=>$central,'request_id'=>$requestId,'actor_id'=>$actor,'fingerprint'=>$fingerprint,'result'=>json_encode($result,JSON_THROW_ON_ERROR),'created_at'=>now()]);
            return $result;
        });}catch(\Throwable $error){if($storedImage!==null)app($kind==='packages'?Tech4LearnPackageImageUpload::class:Tech4LearnQuestionImageUpload::class)->discard($storedImage);throw $error;}
    }
    private function validateDocumentSelection(int $owner,int $examId,array $fields):void {
                foreach(['package_id','language_id'] as $key)abort_unless(is_int($fields[$key]??null)&&$fields[$key]>0,422);
                abort_unless(in_array($fields['document_type']??null,['questions','solutions'],true),422);
                \App\Models\Package::where('organization_id',$owner)->whereHas('exams',fn($q)=>$q->where('exams.id',$examId))->findOrFail($fields['package_id']);
                \App\Models\Language::enabledForOrganization($owner)->whereHas('exams',fn($q)=>$q->where('exams.id',$examId))->findOrFail($fields['language_id']);
    }
    private function translationFields(array &$fields,?string $action):void {
        if(in_array($action,['save-question-translation','save-exam-translation'],true)){
            if($action==='save-question-translation')abort_unless(is_int($fields['question_id']??null)&&$fields['question_id']>0,422);
            $wordingFields=$action==='save-exam-translation'?Tech4LearnTranslationEdits::EXAM_FIELDS:Tech4LearnTranslationEdits::FIELDS;
            abort_unless(is_array($fields['wording']??null)&&count($fields['wording'])>0&&!array_diff(array_keys($fields['wording']),$wordingFields),422);
            foreach($fields['wording'] as $key=>$value){abort_unless($value===null||(is_string($value)&&strlen($value)<=200000),422);if(is_string($value))$fields['wording'][$key]=$this->formattedText($value,$key,$action==='save-question-translation');}
        }
    }
    private function validateTranslationReview(\Illuminate\Database\Eloquent\Model $exam,int $owner,array $review,string $action):void {
        $complete=$review['progress']['remaining']===0&&$review['progress']['exam_content_ready'];
        if($action==='approve-translation')abort_unless($complete,409,'Complete and review the current translation before approving it.');
        elseif($action==='refresh-translation'){abort_unless(!$complete,409,'This translation is already current. Reload its review.');abort_unless($review['progress']['status']!=='processing',409,'Translation is already processing. Reload its review later.');}
        else abort_unless($review['progress']['status']!=='processing',409,'Wait for the active translation before editing it.');
        abort_unless($exam->packages()->where('packages.organization_id',$owner)->count()===$exam->packages()->count(),403);
    }
    private function validateExamAction(int $owner,?\Illuminate\Database\Eloquent\Model $question,array &$fields,?string $action):void {
        if(in_array($action,['add-questions','remove-questions','assign-section'],true)){
            $ids=$fields['question_ids']??null;
            if(!is_array($ids)||count($ids)<1||count($ids)>100||count(array_filter($ids,fn($v)=>is_int($v)&&$v>0))!==count($ids))throw ValidationException::withMessages(['question_ids'=>'Select between 1 and 100 questions.']);
            $ids=array_values(array_unique($ids));
            abort_unless(Question::where('organization_id',$owner)->whereIn('id',$ids)->count()===count($ids),422);
            $fields['question_ids']=$ids;
            if($action!=='add-questions')abort_unless($question->questions()->whereIn('questions.id',$ids)->count()===count($ids),422);
        }
        if(in_array($action,['update-section','remove-section'],true)){
            abort_unless(is_int($fields['section_id']??null)&&$fields['section_id']>0,422);
            $question->sections()->findOrFail($fields['section_id']);
        }
        if($action==='subject-timers'){
            $ids=$fields['subject_ids']??null;$durations=$fields['durations']??null;
            abort_unless(is_array($ids)&&array_is_list($ids)&&count($ids)>0&&count($ids)<=100&&is_array($durations)&&array_is_list($durations)&&count($ids)===count($durations),422);
            abort_unless(count(array_unique($ids))===count($ids)&&count(array_filter($ids,fn($v)=>is_int($v)&&$v>0))===count($ids)&&count(array_filter($durations,fn($v)=>is_int($v)&&$v>=1))===count($durations),422);
            $paperIds=$question->questions()->whereNotNull('subject_id')->distinct()->pluck('subject_id')->map(fn($v)=>(int)$v)->all();
            abort_unless(!array_diff($ids,$paperIds)&&!array_diff($paperIds,$ids),422);
            abort_unless(\App\Models\Subject::where('organization_id',$owner)->whereIn('id',$ids)->count()===count($ids),422);
        }
        if($action==='set-result-status')abort_unless(is_bool($fields['result_after_finish']??null),422);
        if($action==='set-status')abort_unless(in_array($fields['status']??null,['Active','Inactive'],true),422);
    }
    private function validatePackageTags(int $owner,array $values):void {
        abort_unless(!isset($values['tag_ids'])||is_array($values['tag_ids']),422);
        foreach(($values['tag_ids']??[]) as $tag){
            if(is_numeric($tag))abort_unless(\App\Models\PackageTag::where('status',1)->where(fn($q)=>$q->whereNull('organization_id')->orWhere('organization_id',$owner))->whereKey((int)$tag)->exists(),422,'A package tag is unavailable. Review the selected tags.');
            else {
                abort_unless(is_string($tag)&&mb_strlen($tag)<=60&&trim($tag)!==''&&strip_tags($tag)===$tag,422,'Use a plain package tag name of up to 60 characters.');
                $slug=\Illuminate\Support\Str::slug(trim($tag));
                abort_unless($slug!==''&&!\App\Models\PackageTag::where('organization_id',$owner)->where('slug',$slug)->where(fn($q)=>$q->whereNull('status')->orWhere('status','<>',1))->exists(),422,'This tag is empty or disabled. Choose another tag.');
            }
        }
    }
    public function save(string $workspace,int $tenant,string $actor,int $id,array $fields,string $revision,string $requestId,string $kind='questions',?string $action=null):array {
        [$modelClass,$controllerClass,$allowedFields]=$this->definition($kind);
        abort_unless($id>=0,422);
        $imageAction=in_array($kind,['questions','packages'],true)&&$action==='set-image'&&$id>0;
        $languageDisable=$kind==='languages'&&$action==='disable-language'&&$id>0;
        $categoryDelete=in_array($kind,['categories','subcategories'],true)&&$action==='delete-category'&&$id>0;
        if($languageDisable||$categoryDelete)$allowedFields=[];
        elseif($imageAction)$allowedFields=$kind==='packages'?['image','asset','remove']:['field','image','asset','remove'];
        elseif($action!==null){abort_unless($kind==='exams'&&$id>0&&isset(self::EXAM_ACTIONS[$action]),422);$allowedFields=self::EXAM_ACTIONS[$action];}

        if(array_diff(array_keys($fields),$allowedFields))throw ValidationException::withMessages(['fields'=>'Unsupported question fields.']);
        $this->translationFields($fields,$action);
        if($kind==='languages'){
            if(!$id)abort_unless(array_keys($fields)===['master_language_id']&&is_int($fields['master_language_id'])&&$fields['master_language_id']>0,422);
            else abort_unless(!array_key_exists('master_language_id',$fields),422);
        }
        if(strlen(json_encode($fields,JSON_THROW_ON_ERROR))>($imageAction?750000:250000))throw ValidationException::withMessages(['fields'=>'Question is too large.']);
        if($kind==='questions')foreach(['question','option1','option2','option3','option4','option5','option6','hint','explanation','si_answer1'] as $key){
            if(isset($fields[$key])&&is_string($fields[$key]))$fields[$key]=$this->formattedText($fields[$key],$key,$kind==='questions'&&$id>0);
        }
        if($kind==='exams')foreach(['instruction','syllabus'] as $key)if(isset($fields[$key])&&is_string($fields[$key]))$fields[$key]=$this->formattedText($fields[$key],$key);
        if($kind==='packages'&&isset($fields['description'])&&is_string($fields['description']))$fields['description']=$this->formattedText($fields['description'],'description');
        $fingerprint=hash('sha256',json_encode([$kind,$action,$tenant,$actor,$id,$fields,$revision],JSON_THROW_ON_ERROR));
        $storedImage=null;
        try{return DB::transaction(function()use($workspace,$tenant,$actor,$id,$fields,$revision,$requestId,$fingerprint,$kind,$action,$imageAction,&$storedImage){
            $w=DB::table('tech4learn_workspaces')->where('id',$workspace)->lockForUpdate()->first();
            abort_unless($w && (int)$w->organization_id===$tenant,403);
            if($kind==='languages'&&!$id)\App\Models\Language::where('organization_id',$w->source_organization_id)->findOrFail($fields['master_language_id']);
            abort_unless(!in_array($this->feature($kind),json_decode($w->restrictions,true,512,JSON_THROW_ON_ERROR),true),403);
            Organization::where('status','active')->findOrFail($tenant);
            $nativeId=DB::table('tech4learn_workspace_users')->where('workspace_id',$workspace)->where('local_id',$actor)->where('kind','staff')->value('external_id');
            $user=User::where('status',1)->lockForUpdate()->findOrFail($nativeId);
            abort_unless(DB::table('organization_users')->where('organization_id',$tenant)->where('user_id',$user->id)->where('status',1)->exists(),403);
            $prior=DB::table('tech4learn_authoring_requests')->where('workspace_id',$workspace)->where('request_id',$requestId)->first();
            if($prior){abort_unless(hash_equals($prior->fingerprint,$fingerprint),409,'Request ID already used.');return json_decode($prior->result,true,512,JSON_THROW_ON_ERROR);}
            $question=$id?$this->owned($kind,$tenant)->lockForUpdate()->findOrFail($id):null;
            if($question)abort_unless(hash_equals($this->record($kind,$question)['revision'],$revision),409,'Question changed. Reload before saving.');
            else abort_unless($revision==='new',422);
            if($imageAction)$fields=app($kind==='packages'?Tech4LearnPackageImageUpload::class:Tech4LearnQuestionImageUpload::class)->apply($question,$fields,$storedImage);
            elseif($kind==='questions'&&$question)$this->validateRetainedImages($question,$fields);
            $this->validateExamAction($tenant,$question,$fields,$action);
            if($action==='generate-document')$this->validateDocumentSelection($tenant,$id,$fields);
            if(in_array($action,['approve-translation','refresh-translation','save-question-translation','save-exam-translation'],true)){
                abort_unless(is_int($fields['language_id']??null)&&$fields['language_id']>0&&is_string($fields['translation_revision']??null),422);
                $review=app(Tech4LearnExamTranslations::class)->review($workspace,(int)$w->source_organization_id,$actor,$id,$fields['language_id'],0,$fields['translation_revision']);
                $this->validateTranslationReview($question,$tenant,$review,$action);
            }
            $values=$question?array_replace($this->record($kind,$question)['fields'],$fields):$fields;
            if($kind==='packages')$this->validatePackageTags($tenant,$values);
            // The native controller accepts its web form. Give it a private request/session,
            // and translate its redirect feedback into an atomic API outcome.
            $result=$this->invoke($tenant,$user,$action!==null&&!$imageAction?$fields:$values,$question,$kind,$imageAction?($kind==='packages'?'set-package-image':null):$action);
            DB::table('tech4learn_authoring_requests')->insert(['workspace_id'=>$workspace,'request_id'=>$requestId,'fingerprint'=>$fingerprint,'result'=>json_encode($result,JSON_THROW_ON_ERROR),'created_at'=>now()]);
            return $result;
        });}catch(\Throwable $error){if($storedImage!==null)app($kind==='packages'?Tech4LearnPackageImageUpload::class:Tech4LearnQuestionImageUpload::class)->discard($storedImage);throw $error;}
    }
    private function validateRetainedImages(Question $question,array $fields):void {
        $media=app(Tech4LearnQuestionMedia::class);
        foreach(Tech4LearnQuestionMedia::AUTHORING_FIELDS as $field){
            if(!isset($fields[$field])||!is_string($fields[$field]))continue;
            $requested=$media->sources($fields[$field]);if(!$requested)continue;
            $before=$media->sources((string)$question->$field);
            foreach($requested as $key=>$source)
                if(!isset($before[$key])||!hash_equals($before[$key],$source))
                    throw ValidationException::withMessages([$field=>'Only images already in this field may be retained. Use the image upload controls to add or replace an image.']);
        }
    }
    private function formattedText(string $html,string $key,bool $images=false):string {
        // Accept bounded presentation MathML as well as basic text formatting.
        // Existing image sources are checked against the locked field before native saving.
        $dom=new \DOMDocument();$previous=libxml_use_internal_errors(true);
        try{$dom->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>',LIBXML_NONET);}
        finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
        $body=$dom->getElementsByTagName('body')->item(0);if(!$body)return '';
        $allowed=['p','div','br','b','strong','i','em','u','s','sub','sup','ul','ol','li','span','table','thead','tbody','tr','td','th'];
        $mathTags=['math','mrow','mi','mn','mo','mtext','mspace','msup','msub','msubsup','mfrac','msqrt','mroot','mover','munder','munderover','mtable','mtr','mtd','mlabeledtr','mstyle','mpadded','mphantom','menclose','mmultiscripts','mprescripts','none','semantics'];
        $mathAttributes=['display','mathvariant','displaystyle','scriptlevel','stretchy','fence','separator','lspace','rspace','width','height','depth','accent','accentunder','columnalign','rowalign','columnspacing','rowspacing','linethickness','notation'];
        if($body->getElementsByTagName('*')->length>2000)throw ValidationException::withMessages([$key=>'Formatting is too complex. Split this content into smaller fields.']);
        foreach($body->getElementsByTagName('*') as $element){
            $tag=strtolower($element->tagName);$math=in_array($tag,$mathTags,true);
            if($images&&$tag==='img'){
                if(!$element->hasAttribute('src')||trim($element->getAttribute('src'))===''||strlen($element->getAttribute('src'))>200000)
                    throw ValidationException::withMessages([$key=>'Invalid retained image.']);
                foreach($element->attributes as $attribute){
                    $valid=($attribute->name==='src')||($attribute->name==='alt'&&strlen($attribute->value)<=1000)||(in_array($attribute->name,['width','height'],true)&&preg_match('/^[1-9][0-9]{0,3}$/D',$attribute->value));
                    if(!$valid)throw ValidationException::withMessages([$key=>'Unsupported retained image formatting.']);
                }
                continue;
            }
            if(!$math&&!in_array($tag,$allowed,true))throw ValidationException::withMessages([$key=>'This content requires the native media or formula editor.']);
            if($math&&$tag!=='math'){
                $parent=$element->parentNode;$inside=false;
                while($parent instanceof \DOMElement){if(strtolower($parent->tagName)==='math'){$inside=true;break;}$parent=$parent->parentNode;}
                if(!$inside)throw ValidationException::withMessages([$key=>'MathML elements must be inside a math formula.']);
            }
            foreach($element->attributes as $attribute){
                $valid=$math
                    ? (($attribute->name==='xmlns'&&$tag==='math'&&$attribute->value==='http://www.w3.org/1998/Math/MathML')||(in_array($attribute->name,$mathAttributes,true)&&strlen($attribute->value)<=160&&preg_match('/^[a-zA-Z0-9 .,%+_\-]*$/D',$attribute->value)))
                    : (in_array($attribute->name,['colspan','rowspan'],true)&&preg_match('/^[1-9][0-9]{0,2}$/D',$attribute->value));
                if(!$valid)throw ValidationException::withMessages([$key=>'Unsupported formatting. Paste plain text or use the question editor.']);
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
            if(in_array($kind,['languages','packages'],true)||in_array($action,['generate-document','approve-translation','refresh-translation','save-question-translation','save-exam-translation'],true))abort_unless(!\App\Support\SaasAccess::isPlatformAdmin()&&(int)\App\Support\SaasAccess::organization()?->id===$tenant,403);
            if($action==='set-package-image'){
                $question->photo=$fields['photo'];$question->save();
                return $this->record($kind,$question->fresh());
            }
            if($action==='save-exam-translation'){
                app(Tech4LearnTranslationEdits::class)->applyExam($question,$fields);
                return $this->record($kind,$question->fresh());
            }
            if($action==='save-question-translation'){
                app(Tech4LearnTranslationEdits::class)->apply($question,$fields,$request);
                return $this->record($kind,$question->fresh());
            }
            if($action==='refresh-translation'){
                abort_unless(\App\Support\SaasAccess::featureEnabled('ai_translation',$organisation),403,'AI translation is not enabled for this organisation plan.');
                // The native worker intentionally ignores failed rows. An explicit
                // staff retry resets that stop state and the old approval first.
                $question->languages()->updateExistingPivot($fields['language_id'],['translation_status'=>'pending','last_error'=>null,'translating_at'=>null,'translation_failed_source_fingerprint'=>null,'translation_approved_at'=>null,'translation_approved_by'=>null]);
                $result=app(ExamDocumentBulkActionService::class)->process([$question->id.':0:'.$fields['language_id']],$tenant,(int)$user->id,'translate',true);
                abort_unless($result['processed']===1,409,'This translation could not be queued.');
                return $this->record($kind,$question)+['translation_requested'=>true];
            }
            $controller=app($controllerClass);
            $arguments=['request'=>$request];if($question)$arguments[$parameter]=$question;
            $methods=['add-questions'=>'bulkAddQuestions','remove-questions'=>'removeQuestions','create-section'=>'storeSection','update-section'=>'updateSection','remove-section'=>'destroySection','assign-section'=>'assignQuestionSections','subject-timers'=>'setSectionWiseTimer','generate-document'=>'generate','set-status'=>'toggleStatus','set-result-status'=>'toggleResultStatus'];
            if(in_array($action,['update-section','remove-section'],true))$arguments['section']=$question->sections()->findOrFail($fields['section_id']);
            if($action==='set-result-status'){
                if((bool)$question->result_after_finish===$fields['result_after_finish'])return $this->record($kind,$question);
                unset($arguments[$parameter]);$arguments['id']=$question->id;
            }
            if($action==='set-status'&&$question->status===$fields['status'])return $this->record($kind,$question);
            if($action==='disable-language'){
                if(!(bool)$question->is_enabled)return $this->record($kind,$question);
                $methods[$action]='destroy';unset($arguments[$parameter]);$arguments['id']=$question->id;
            }
            if($action==='delete-category')$methods[$action]='destroy';
            $methods['approve-translation']='approve';
            $method=$action!==null?$methods[$action]:($question?'update':($kind==='subcategories'?'storeSubcategory':'store'));
            if(in_array($action,['generate-document','approve-translation'],true)){
                $controller=app(\App\Http\Controllers\ExamDocumentController::class);$method=$action==='generate-document'?'generate':'approve';
                $arguments=['request'=>$request,'exam'=>$question,'language'=>\App\Models\Language::enabledForOrganization($tenant)->findOrFail($fields['language_id'])];
            }
            $response=$app->call([$controller,$method],$arguments);
            if($session->has('errors'))throw ValidationException::withMessages($session->get('errors')->getBag('default')->messages());
            $jsonSuccess=$action!==null&&$response instanceof \Illuminate\Http\JsonResponse&&$response->getStatusCode()<300&&($response->getData(true)['success']??false)===true;
            if((!$session->has('success')&&!$jsonSuccess) || $session->has('error'))throw ValidationException::withMessages(['question'=>'ExamElite could not save this question. Check its fields and related records.']);
            if($action==='delete-category'){
                abort_unless(!$this->owned($kind,$tenant)->whereKey($question->id)->exists(),500,'Native category deletion was not confirmed.');
                return ['id'=>(int)$question->id,'deleted'=>true];
            }
            if(!$question){
                if($kind==='languages')$question=$this->owned($kind,$tenant)->where('source_language_id',$fields['master_language_id'])->sole();
                else {abort_unless(count($created)===1,500,'Native create did not return one question.');$question=$created[0];}
            }
            return $this->record($kind,$this->owned($kind,$tenant)->findOrFail($question->id));
        }finally{
            if(!$originalDispatcher)Question::unsetEventDispatcher();else Question::setEventDispatcher($originalDispatcher);
            $oldUser?$guard->setUser($oldUser):$guard->forgetUser();
            $app->instance('request',$oldRequest);$app->instance('redirect',$oldRedirect);Tenant::clear();
            $session->invalidate();
        }
    }
}
