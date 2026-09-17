<?php
namespace App\Http\Controllers;

use App\Services\Tech4LearnExamDocuments;
use Illuminate\Http\Request;

class Tech4LearnDocumentController extends Tech4LearnPlatformController
{
    public function centralStatus(Request $request,string $exam,string $type) {return $this->document($request,'',$exam,$type,true,true);}
    public function centralRead(Request $request,string $exam,string $type) {return $this->document($request,'',$exam,$type,false,true);}
    public function documentStatus(Request $request,string $org,string $exam,string $type) {return $this->document($request,$org,$exam,$type,true);}
    public function read(Request $request,string $org,string $exam,string $type) {
        return $this->document($request,$org,$exam,$type,false);
    }
    private function document(Request $request,string $org,string $exam,string $type,bool $status,bool $central=false) {
        $source=$this->configuration($request)['_platform']['organization_id'];
        abort_unless(array_diff(array_keys($request->query()),$central?['package_id','language_id']:['actor_id','package_id','language_id'])===[],422);
        $actor=$central?'':$request->query('actor_id');
        abort_unless(is_string($actor)&&preg_match('/^[1-9][0-9]{0,14}$/D',$exam),422);
        $selection=[];
        foreach(['package_id','language_id'] as $field){
            $value=$request->query($field);
            abort_unless($value===null||(is_string($value)&&preg_match('/^[1-9][0-9]{0,14}$/D',$value)),422);
            $selection[$field]=$value===null?null:(int)$value;
        }
        $method=$status?'status':'read';
        return $this->reply($source,['data'=>app(Tech4LearnExamDocuments::class)->$method($org,$source,$actor,(int)$exam,$selection['package_id'],$selection['language_id'],$type,$central)]);
    }
}
