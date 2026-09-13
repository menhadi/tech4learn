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
    private function workspace(Request $r,string $org):array {
        $tenant=$this->configuration($r)['_platform']['organization_id'];$this->uuid($org);
        $w=DB::table('tech4learn_workspaces')->where('id',$org)->where('source_organization_id',$tenant)->first();
        abort_unless($w && $w->organization_id,404);
        abort_unless(!in_array('questions',json_decode($w->restrictions,true,512,JSON_THROW_ON_ERROR),true),403);
        return [$tenant,(int)$w->organization_id];
    }
    public function question(Request $r,string $org,string $id){
        [$central,$owner]=$this->workspace($r,$org);abort_unless(preg_match('/^[1-9][0-9]{0,14}$/D',$id),422);
        $q=Question::where('organization_id',$owner)->findOrFail($id);
        return $this->reply($central,array_merge(app(Tech4LearnQuestionAuthoring::class)->snapshot($q),['type'=>$q->qtype?->type,'type_name'=>$q->qtype?->question_type]));
    }
    public function save(Request $r,string $org,string $id){
        [$central,$owner]=$this->workspace($r,$org);abort_unless(preg_match('/^[1-9][0-9]{0,14}$/D',$id),422);
        $actor=$r->input('actor_id');$request=$r->input('request_id');$this->uuid((string)$actor);$this->uuid((string)$request);
        $fields=$r->input('fields');$revision=$r->input('revision');abort_unless(is_array($fields)&&is_string($revision)&&preg_match('/^[a-f0-9]{64}$/D',$revision),422);
        try {
            $saved=app(Tech4LearnQuestionAuthoring::class)->save($org,$owner,$actor,(int)$id,$fields,$revision,$request);
            return $this->reply($central,['saved'=>true,'question'=>$saved]);
        }catch(ValidationException $e){return $this->reply($central,['saved'=>false,'errors'=>$e->errors()]);}
        catch(HttpException $e){if($e->getStatusCode()!==409)throw $e;return $this->reply($central,['saved'=>false,'conflict'=>true]);}
    }
}
