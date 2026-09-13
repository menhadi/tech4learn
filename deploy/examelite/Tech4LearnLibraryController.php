<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\{Exam,Question,Subject,Topic,Stopic,QuestionSection};
use App\Services\Tech4LearnContentCopies;
use App\Support\Tech4LearnWorkspacePolicy;

final class Tech4LearnLibraryController extends Controller
{
    private const TYPES=['exam'=>[Exam::class,'name','exams'], 'question'=>[Question::class,'question','questions'],
        'subject'=>[Subject::class,'subject_name','subjects'],'topic'=>[Topic::class,'name','topics'],
        'subtopic'=>[Stopic::class,'name','stopics'],'section'=>[QuestionSection::class,'name','sections']];
    private function workspace(Request $r,string $kind):object
    {
        $w=$r->attributes->get('tech4learn_workspace');
        abort_unless($w&&$r->session()->get('tech4learn_workspace.kind')==='staff'&&isset(self::TYPES[$kind]),403);
        $feature=match($kind){'exam'=>'exams','question'=>'questions',default=>'subjects'};
        abort_unless(Tech4LearnWorkspacePolicy::mayUse($feature,json_decode($w->restrictions,true)),403);
        return $w;
    }
    public function index(Request $r)
    {
        $kind=$r->query('type','exam');abort_unless(is_string($kind),422);
        $w=$this->workspace($r,$kind);[$class,$column]=self::TYPES[$kind];
        $q=$class::query();
        if(in_array($kind,['topic','subtopic'],true)) $q->whereHas('subject',fn($s)=>$s->where('organization_id',$w->source_organization_id));
        else $q->where('organization_id',$w->source_organization_id);
        if($kind==='exam') $q->whereNull('created_by_student_id')->where(fn($s)=>$s->where('is_student_practice',false)->orWhereNull('is_student_practice'));
        $search=$r->query('search','');abort_unless(is_string($search)&&mb_strlen($search)<=120,422);
        if($search!=='')$q->where($column,'like','%'.addcslashes($search,'%_\\').'%');
        $rows=$q->orderBy('id')->select(['id',$column])->paginate(50)->withQueryString();
        return view('tech4learn::library',compact('kind','column','rows','search'));
    }
    public function copy(Request $r,string $kind,string $id,Tech4LearnContentCopies $copies)
    {
        $w=$this->workspace($r,$kind);abort_unless(ctype_digit($id)&&(int)$id>0,422);
        try {$target=$copies->copy($w->id,(int)$w->organization_id,$kind,(int)$id);}
        catch(\DomainException $e) {return back()->with('error',$e->getMessage());}
        // Topic, subtopic and section editors are inline on their native index pages.
        $path=in_array($kind,['section','topic','subtopic'],true)?'/'.self::TYPES[$kind][2]:'/' .self::TYPES[$kind][2].'/'.$target.'/edit';
        return redirect($path)->with('success','Opened your organisation’s version. Shared originals remain unchanged.');
    }
}
