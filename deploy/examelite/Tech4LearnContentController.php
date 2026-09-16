<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Question;
use App\Services\Tech4LearnContentCopies;

/** Server-credential API. T4L authorises its authenticated superadmin before transfers. */
class Tech4LearnContentController extends Tech4LearnPlatformController
{
    public function centralWrite(Request $r,string $id='new') {
        $central=$this->configuration($r)['_platform']['organization_id'];
        abort_unless($r->query()===[]&&!array_diff(array_keys($r->all()),['actor_id','fields','revision','request_id']),422);
        abort_unless($id==='new'||preg_match('/^[1-9][0-9]{0,14}$/D',$id),422);
        foreach(['actor_id','revision','request_id'] as $key)abort_unless(is_string($r->input($key)),422);
        abort_unless(is_array($r->input('fields')),422);
        try {
            $result=app(\App\Services\Tech4LearnQuestionAuthoring::class)->saveCentralQuestion($central,$r->input('actor_id'),$id==='new'?0:(int)$id,$r->input('fields'),$r->input('revision'),$r->input('request_id'));
            return $this->reply($central,['saved'=>true,'question'=>$result]);
        }catch(\Illuminate\Validation\ValidationException $e){return $this->reply($central,['saved'=>false,'errors'=>$e->errors()]);}
        catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){
            if($e->getStatusCode()===409)return $this->reply($central,['saved'=>false,'conflict'=>true]);
            if(in_array($e->getStatusCode(),[404,422],true))return $this->reply($central,['saved'=>false,'errors'=>['record'=>['Check that the selected records belong to the central bank and exam group.']]]);
            throw $e;
        }
    }
    /** Separate central-bank reads: an organisation ID can never select this owner. */
    private function centralQuestion(Request $r,string $id):array {
        $tenant=$this->configuration($r)['_platform']['organization_id'];
        abort_unless(preg_match('/^[1-9][0-9]{0,14}$/D',$id),422);
        \App\Models\Organization::where('status','active')->findOrFail($tenant);
        return [$tenant,Question::where('organization_id',$tenant)->findOrFail((int)$id)];
    }
    public function centralDetail(Request $r,string $id) {
        abort_unless($r->query()===[],422);
        [$tenant,$question]=$this->centralQuestion($r,$id);
        $record=app(\App\Services\Tech4LearnQuestionAuthoring::class)->snapshot($question);
        // A preview exposes opaque image references, never native storage locations.
        foreach($record['preview_fields'] as $field=>$value)$record['fields'][$field]=$value;
        return $this->reply($tenant,$record);
    }
    public function centralMedia(Request $r,string $id,string $asset) {
        abort_unless(array_keys($r->query())===['revision']&&is_string($r->query('revision'))&&preg_match('/^[a-f0-9]{64}$/D',$r->query('revision')),422);
        [$tenant,$question]=$this->centralQuestion($r,$id);
        $authoring=app(\App\Services\Tech4LearnQuestionAuthoring::class);
        $revision=$r->query('revision');
        abort_unless(hash_equals($authoring->snapshot($question)['revision'],$revision),409,'Question changed. Reload its preview.');
        $result=app(\App\Services\Tech4LearnQuestionMedia::class)->readAuthoring($question,$tenant,$asset);
        // Revalidate the credential, active owner and question version before releasing bytes.
        [$currentTenant,$current]=$this->centralQuestion($r,$id);
        abort_unless($tenant===$currentTenant&&hash_equals($authoring->snapshot($current)['revision'],$revision),409,'Question changed. Reload its preview.');
        return $this->reply($tenant,$result+['revision'=>$revision]);
    }
    public function history(Request $r,string $org) {
        $tenant=$this->configuration($r)['_platform']['organization_id'];$this->uuid($org);
        $workspace=DB::table('tech4learn_workspaces')->where('id',$org)->where('source_organization_id',$tenant)->exists();
        if(!$workspace)return $this->reply($tenant,['items'=>[]]);
        $items=DB::table('tech4learn_content_transfers')->where('workspace_id',$org)->orderByDesc('created_at')->orderByDesc('request_id')->limit(50)->get(['request_id','direction','result','created_at'])->map(function($r){
            $result=json_decode($r->result,true,512,JSON_THROW_ON_ERROR);
            return ['request_id'=>$r->request_id,'direction'=>$r->direction,'created_at'=>$r->created_at,'count'=>$result['count'],'items'=>$result['items']];
        });
        return $this->reply($tenant,['items'=>$items]);
    }
    public function questions(Request $r,string $org) {
        $tenant=$this->configuration($r)['_platform']['organization_id'];$this->uuid($org);
        $source=$r->query('source');$search=$r->query('search','');$after=$r->query('after','0');
        abort_unless(in_array($source,['central','organisation'],true)&&is_string($search)&&mb_strlen($search)<=120&&is_string($after)&&preg_match('/^\d{1,15}$/D',$after),422);
        $w=DB::table('tech4learn_workspaces')->where('id',$org)->where('source_organization_id',$tenant)->first();
        if($source==='organisation' && (!$w || !$w->organization_id)) return $this->reply($tenant,['items'=>[],'next'=>null]);
        $owner=$source==='central'?$tenant:(int)$w->organization_id;
        $query=DB::table('questions')->where('organization_id',$owner)->where('id','>',$after);
        if($search!=='')$query->where('question','like','%'.$search.'%');
        $rows=$query->orderBy('id')->limit(51)->get(['id','question','subject_id','qtype_id']);
        $more=$rows->count()>50;
        $items=$rows->take(50)->map(fn($q)=>['id'=>(int)$q->id,'question'=>mb_substr(html_entity_decode(strip_tags($q->question??''),ENT_QUOTES|ENT_HTML5,'UTF-8'),0,700),'subject_id'=>$q->subject_id,'qtype_id'=>$q->qtype_id])->values();
        return $this->reply($tenant,['items'=>$items,'next'=>$more?$items->last()['id']:null]);
    }
    public function transfer(Request $r,string $org) {
        $tenant=$this->configuration($r)['_platform']['organization_id'];$this->uuid($org);
        $request=$r->input('request_id');$actor=$r->input('actor_id');$this->uuid((string)$request);$this->uuid((string)$actor);
        $direction=$r->input('direction');$ids=$r->input('question_ids');
        abort_unless(in_array($direction,['share','pull'],true)&&is_array($ids)&&count($ids)>0&&count($ids)<=50,422);
        foreach($ids as $id)abort_unless(is_int($id)&&$id>0,422);
        $ids=array_values(array_unique($ids));sort($ids,SORT_NUMERIC);
        $fingerprint=hash('sha256',json_encode([$direction,$ids,$actor]));
        $result=DB::transaction(function()use($org,$tenant,$request,$actor,$direction,$ids,$fingerprint){
            $w=DB::table('tech4learn_workspaces')->where('id',$org)->lockForUpdate()->first();
            abort_unless($w && (int)$w->source_organization_id===$tenant && $w->organization_id,409,'Organisation exam workspace is not connected.');
            $prior=DB::table('tech4learn_content_transfers')->where('workspace_id',$org)->where('request_id',$request)->first();
            if($prior){abort_unless(hash_equals($prior->fingerprint,$fingerprint),409,'Request identifier already used.');return json_decode($prior->result,true,512,JSON_THROW_ON_ERROR);}
            $source=$direction==='share'?$tenant:(int)$w->organization_id;
            $target=$direction==='share'?(int)$w->organization_id:$tenant;
            abort_unless(Question::where('organization_id',$source)->whereIn('id',$ids)->count()===count($ids),403,'Selected questions are outside the source bank.');
            $copies=[];
            foreach($ids as $id){
                try{$copy=app(Tech4LearnContentCopies::class)->copy($org,$target,'question',$id,$direction==='pull');}
                catch(\DomainException $e){abort(409,'Question relationships or feature restrictions prevent this copy.');}
                $copies[]=['source_id'=>$id,'target_id'=>$copy];
            }
            $result=['count'=>count($copies),'items'=>$copies];
            DB::table('tech4learn_content_transfers')->insert(['workspace_id'=>$org,'request_id'=>$request,'actor_id'=>$actor,'direction'=>$direction,'fingerprint'=>$fingerprint,'result'=>json_encode($result),'created_at'=>now()]);
            return $result;
        });
        return $this->reply($tenant,$result);
    }
}
