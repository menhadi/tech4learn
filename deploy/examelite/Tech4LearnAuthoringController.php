<?php
namespace App\Http\Controllers;
use App\Models\Question;
use App\Services\Tech4LearnQuestionAuthoring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class Tech4LearnAuthoringController extends Tech4LearnPlatformController
{
    private function workspace(Request $r,string $org,?string $feature='questions'):array {
        $tenant=$this->configuration($r)['_platform']['organization_id'];$this->uuid($org);
        $w=DB::table('tech4learn_workspaces')->where('id',$org)->where('source_organization_id',$tenant)->first();
        abort_unless($w && $w->organization_id,404);
        $restrictions=json_decode($w->restrictions,true,512,JSON_THROW_ON_ERROR);
        abort_unless($feature===null?(!in_array('questions',$restrictions,true)||!in_array('subjects',$restrictions,true)||!in_array('exams',$restrictions,true)):!in_array($feature,$restrictions,true),403);
        return [$tenant,(int)$w->organization_id];
    }
    public function choices(Request $r,string $org,string $kind){
        [$central,$owner]=$this->workspace($r,$org,$kind==='exams'?'exams':null);
        $search=$r->query('search','');$after=$r->query('after','0');
        abort_unless(is_string($search)&&mb_strlen($search)<=120&&is_string($after)&&preg_match('/^[0-9]{1,15}$/D',$after),422);
        $definitions=[
            'exams'=>['exams','name'], 'packages'=>['packages','name'], 'groups'=>['groups','group_name'], 'subjects'=>['subjects','subject_name'],
            'sections'=>['question_sections','name'], 'topics'=>['topics','name'],
            'subtopics'=>['stopics','name'], 'languages'=>['languages','name'],
            'types'=>['qtypes','question_type'], 'difficulties'=>['diffs','diff_level'],
        ];
        abort_unless(isset($definitions[$kind]),404);[$table,$label]=$definitions[$kind];
        if($kind==='languages')$query=\App\Models\Language::enabledForOrganization($owner)->toBase();
        else {
            $query=DB::table($table);
            if(in_array($kind,['topics','subtopics'],true)){
                $query->whereExists(fn($q)=>$q->selectRaw('1')->from('groups')->whereColumn('groups.id',$table.'.group_id')->where('groups.organization_id',$owner));
                $query->whereExists(fn($q)=>$q->selectRaw('1')->from('subjects')->whereColumn('subjects.id',$table.'.subject_id')->where('subjects.organization_id',$owner));
            }elseif(!in_array($kind,['types','difficulties'],true))$query->where($table.'.organization_id',$owner);
        }
        $columns=[$table.'.id',$table.'.'.$label];if($kind==='types')$columns[]='type';
        if(in_array($kind,['topics','subtopics'],true)){$columns[]='subject_id';$columns[]='group_id';}
        if($kind==='subtopics')$columns[]='topic_id';
        if($search!=='')$query->where($table.'.'.$label,'like','%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$search).'%');
        $rows=$query->where($table.'.id','>',(int)$after)->orderBy($table.'.id')->limit(101)->get($columns);$more=$rows->count()>100;$rows=$rows->take(100);
        $items=$rows->map(function($row)use($label){
            $value=$row->$label;$translated=is_string($value)?json_decode($value,true):null;
            if(is_array($translated))$value=$translated['en']??reset($translated);
            $item=['id'=>(int)$row->id,'label'=>mb_substr(strip_tags((string)$value),0,250)];
            foreach(['type','subject_id','topic_id','group_id'] as $key)if(isset($row->$key))$item[$key]=$row->$key;
            return $item;
        })->values()->all();
        return $this->reply($central,['items'=>$items,'next'=>$more?(int)$rows->last()->id:null]);
    }
    public function taxonomy(Request $r,string $org,string $kind,string $id){
        [$central,$owner]=$this->workspace($r,$org,app(Tech4LearnQuestionAuthoring::class)->feature($kind));
        abort_unless($kind!=='questions',404);$service=app(Tech4LearnQuestionAuthoring::class);$service->definition($kind);
        if($kind==='exams'&&$id==='new')return $this->reply($central,$service->newExam());
        if($id==='new')return $this->reply($central,['id'=>0,'revision'=>'new','fields'=>array_intersect_key(['display_order'=>0,'group_ids'=>[],'category_ids'=>[],'status'=>true],array_flip($service->definition($kind)[2]))]);
        abort_unless(preg_match('/^[1-9][0-9]{0,14}$/D',$id),422);
        return $this->reply($central,$service->record($kind,$service->owned($kind,$owner)->findOrFail($id)));
    }
    public function saveTaxonomy(Request $r,string $org,string $kind,string $id){
        abort_unless($kind!=='questions',404);app(Tech4LearnQuestionAuthoring::class)->definition($kind);
        return $this->save($r,$org,$id,$kind);
    }
    public function question(Request $r,string $org,string $id){
        [$central,$owner]=$this->workspace($r,$org);abort_unless(preg_match('/^[1-9][0-9]{0,14}$/D',$id),422);
        $q=Question::where('organization_id',$owner)->findOrFail($id);
        return $this->reply($central,array_merge(app(Tech4LearnQuestionAuthoring::class)->snapshot($q),['type'=>$q->qtype?->type,'type_name'=>$q->qtype?->question_type]));
    }
    public function examAction(Request $r,string $org,string $id,string $action){return $this->save($r,$org,$id,'exams',$action);}
    public function examQuestions(Request $r,string $org,string $id){
        [$central,$owner]=$this->workspace($r,$org,'exams');abort_unless(preg_match('/^[1-9][0-9]{0,14}$/D',$id),422);
        $exam=app(Tech4LearnQuestionAuthoring::class)->owned('exams',$owner)->findOrFail($id);
        $after=$r->query('after','0');abort_unless(is_string($after)&&preg_match('/^[0-9]{1,15}$/D',$after),422);
        $rows=$exam->questions()->where('questions.organization_id',$owner)->where('questions.id','>',(int)$after)->orderBy('questions.id')->limit(101)->get(['questions.id','questions.question']);$more=$rows->count()>100;$rows=$rows->take(100);
        return $this->reply($central,['items'=>$rows->map(fn($q)=>['id'=>(int)$q->id,'question'=>mb_substr(strip_tags($q->question??''),0,500)])->values()->all(),'next'=>$more?(int)$rows->last()->id:null]);
    }
    public function create(Request $r,string $org){return $this->save($r,$org,'new');}
    public function save(Request $r,string $org,string $id,string $kind='questions',?string $action=null){
        [$central,$owner]=$this->workspace($r,$org,app(Tech4LearnQuestionAuthoring::class)->feature($kind));abort_unless($id==='new'||preg_match('/^[1-9][0-9]{0,14}$/D',$id),422);
        $actor=$r->input('actor_id');$request=$r->input('request_id');$this->uuid((string)$actor);$this->uuid((string)$request);
        $fields=$r->input('fields');$revision=$r->input('revision');abort_unless(is_array($fields)&&is_string($revision)&&(($id==='new'&&$revision==='new')||($id!=='new'&&preg_match('/^[a-f0-9]{64}$/D',$revision))),422);
        try {
            $saved=app(Tech4LearnQuestionAuthoring::class)->save($org,$owner,$actor,(int)$id,$fields,$revision,$request,$kind,$action);
            return $this->reply($central,['saved'=>true,'question'=>$saved]);
        }catch(ValidationException $e){return $this->reply($central,['saved'=>false,'errors'=>$e->errors()]);}
        catch(\Illuminate\Database\Eloquent\ModelNotFoundException $e){return $this->reply($central,['saved'=>false,'errors'=>['record'=>['A selected record is unavailable in this organisation. Reload the choices.']]]);}
        catch(HttpException $e){
            if($e->getStatusCode()===409)return $this->reply($central,['saved'=>false,'conflict'=>true]);
            if(in_array($e->getStatusCode(),[404,422],true))return $this->reply($central,['saved'=>false,'errors'=>['record'=>['Check that the selected records belong to this organisation and exam group.']]]);
            throw $e;
        }
    }
}
