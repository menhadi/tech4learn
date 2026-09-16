<?php
namespace App\Services;

use App\Models\{Exam,Language,Organization,Package,User};
use Illuminate\Support\Facades\DB;

/** Staff document reads only. Native publication selects the approved artifact. */
final class Tech4LearnExamDocuments
{
    public const MAX_BYTES=10485760;

    public function status(string $workspace,int $source,string $actor,int $examId,?int $packageId,?int $languageId,string $type):array {
        abort_unless($examId>0&&($packageId===null||$packageId>0)&&($languageId===null||$languageId>0)&&in_array($type,['questions','solutions'],true),422);
        $owner=$this->scope($workspace,$source,$actor);
        $exam=Exam::where('organization_id',$owner)->findOrFail($examId);
        $package=$packageId===null?null:Package::where('organization_id',$owner)->whereHas('exams',fn($q)=>$q->where('exams.id',$examId))->findOrFail($packageId);
        $language=$languageId===null?null:Language::enabledForOrganization($owner)->whereHas('exams',fn($q)=>$q->where('exams.id',$examId))->findOrFail($languageId);
        $build=\App\Models\ExamPdfBuild::where('organization_id',$owner)->where('exam_id',$examId)->where('package_id',$packageId)->where('language_id',$languageId)->where('document_type',$type)->latest('updated_at')->first();
        $state=$build?(string)$build->status:'not_built';
        abort_unless(in_array($state,['not_built','queued','processing','ready','stale','failed'],true),409,'This native document status is not supported.');
        $approved=app(ExamDocumentLifecycleService::class)->approved($exam,$package,$language,$type);
        $available=false;
        if($approved&&(int)$approved->organization_id===$owner){
            $root=realpath(storage_path('app/exam-pdfs'));$path=realpath((string)$approved->current_path);
            $available=$root!==false&&$path!==false&&str_starts_with($path,$root.DIRECTORY_SEPARATOR)&&is_file($path)&&filesize($path)>0&&filesize($path)<=self::MAX_BYTES;
            if($available){$handle=@fopen($path,'rb');$available=$handle!==false;if($handle!==false){try{$available=fread($handle,5)==='%PDF-';}finally{fclose($handle);}}}
        }
        abort_unless($this->scope($workspace,$source,$actor)===$owner,403);
        if($packageId!==null)Package::where('organization_id',$owner)->whereHas('exams',fn($q)=>$q->where('exams.id',$examId))->findOrFail($packageId);
        if($languageId!==null)Language::enabledForOrganization($owner)->whereHas('exams',fn($q)=>$q->where('exams.id',$examId))->findOrFail($languageId);
        return ['exam_id'=>$examId,'package_id'=>$packageId,'language_id'=>$languageId,'document_type'=>$type,'build_id'=>$build?(int)$build->id:null,'status'=>$state,'approved_available'=>$available];
    }

    private function scope(string $workspace,int $source,string $actor):int {
        foreach([$workspace,$actor] as $uuid)abort_unless(preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D',$uuid),422);
        $w=DB::table('tech4learn_workspaces')->where('id',$workspace)->where('source_organization_id',$source)->first();
        abort_unless($w&&$w->organization_id,404);
        $owner=(int)$w->organization_id;
        Organization::where('status','active')->findOrFail($owner);
        abort_unless(!in_array('exams',json_decode($w->restrictions,true,512,JSON_THROW_ON_ERROR),true),403);
        $userId=DB::table('tech4learn_workspace_users')->where('workspace_id',$workspace)->where('local_id',$actor)->where('kind','staff')->value('external_id');
        User::where('status',1)->findOrFail($userId);
        abort_unless(DB::table('organization_users')->where('organization_id',$owner)->where('user_id',$userId)->where('status',1)->exists(),403);
        return $owner;
    }

    public function read(string $workspace,int $source,string $actor,int $examId,?int $packageId,?int $languageId,string $type):array {
        abort_unless($examId>0&&($packageId===null||$packageId>0)&&($languageId===null||$languageId>0)&&in_array($type,['questions','solutions'],true),422);
        $owner=$this->scope($workspace,$source,$actor);
        $exam=Exam::where('organization_id',$owner)->findOrFail($examId);
        $package=$packageId===null?null:Package::where('organization_id',$owner)->whereHas('exams',fn($q)=>$q->where('exams.id',$examId))->findOrFail($packageId);
        $language=$languageId===null?null:Language::enabledForOrganization($owner)->whereHas('exams',fn($q)=>$q->where('exams.id',$examId))->findOrFail($languageId);
        $build=app(ExamDocumentLifecycleService::class)->approved($exam,$package,$language,$type);
        abort_unless($build,409,'An approved PDF is not available for this selection.');
        abort_unless((int)$build->organization_id===$owner&&(int)$build->exam_id===$examId&&($build->package_id===null?null:(int)$build->package_id)===$packageId&&($build->language_id===null?null:(int)$build->language_id)===$languageId&&$build->document_type===$type,403);
        $bytes=$this->bytes((string)$build->current_path);
        // Reading can take time. Recheck revocation before releasing document bytes.
        abort_unless($this->scope($workspace,$source,$actor)===$owner,403);
        Exam::where('organization_id',$owner)->findOrFail($examId);
        if($packageId!==null)Package::where('organization_id',$owner)->whereHas('exams',fn($q)=>$q->where('exams.id',$examId))->findOrFail($packageId);
        if($languageId!==null)Language::enabledForOrganization($owner)->whereHas('exams',fn($q)=>$q->where('exams.id',$examId))->findOrFail($languageId);
        return ['exam_id'=>$examId,'package_id'=>$packageId,'language_id'=>$languageId,'document_type'=>$type,'build_id'=>(int)$build->id,'mime'=>'application/pdf','base64'=>base64_encode($bytes)];
    }

    private function bytes(string $path):string {
        $root=realpath(storage_path('app/exam-pdfs'));$resolved=realpath($path);
        abort_unless($root!==false&&$resolved!==false&&str_starts_with($resolved,$root.DIRECTORY_SEPARATOR)&&is_file($resolved),404);
        $handle=fopen($resolved,'rb');abort_unless($handle!==false,404);
        try {
            $size=fstat($handle)['size']??0;
            abort_unless($size>0&&$size<=self::MAX_BYTES,422,'The PDF exceeds the download size limit.');
            $bytes=stream_get_contents($handle,self::MAX_BYTES+1);
            abort_unless(is_string($bytes)&&strlen($bytes)<=self::MAX_BYTES&&str_starts_with($bytes,'%PDF-'),422,'The approved file is not a PDF.');
            return $bytes;
        } finally {fclose($handle);}
    }
}
