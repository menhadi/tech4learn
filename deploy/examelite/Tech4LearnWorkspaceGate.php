<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,DB};
use App\Support\{Tenant,Tech4LearnWorkspacePolicy};

final class Tech4LearnWorkspaceGate
{
    public function handle(Request $r,Closure $next)
    {
        $id=$r->attributes->get('tech4learn_workspace_id');
        if (!$id) return $next($r);
        $w=DB::table('tech4learn_workspaces')->where('id',$id)->first();
        abort_unless($w && (int)$w->organization_id===(int)Tenant::id(),404);
        $r->attributes->set('tech4learn_workspace',$w);
        if ($r->is('tech4learn/launch')) return $next($r);
        $session=$r->session()->get('tech4learn_workspace');
        abort_unless(is_array($session)&&($session['id']??null)===$id&&($session['expires']??0)>time(),401,'Open this workspace again from Tech4Learn.');
        $kind=$session['kind']??'';$guard=$kind==='student'?'student':'web';
        abort_unless(in_array($kind,['student','staff'],true)&&Auth::guard($guard)->id()===$session['user'],403);
        $mapping=DB::table('tech4learn_workspace_users')->where('workspace_id',$id)->where('kind',$kind)->where('external_id',$session['user'])->exists();
        abort_unless($mapping,403);
        if ($r->isMethod('post') && $r->is('logout','student/signout')) {
            Auth::guard('web')->logout();Auth::guard('student')->logout();
            $r->session()->invalidate();$r->session()->regenerateToken();
            return redirect('https://tech4learn.com/');
        }
        if ($kind==='student') {
            abort_unless((int)Auth::guard('student')->user()->organization_id===(int)$w->organization_id,403);
            $feature=$r->is('student/results*')?'results':'taking';
            abort_unless($r->is('student/*','exam/*','exam-details/*','exam-print/*','exam-solutions/*','questions/*/language/*','subjective-upload'),403);
        } else {
            abort_unless(DB::table('organization_users')->where('organization_id',$w->organization_id)->where('user_id',$session['user'])->where('status',1)->exists(),403);
            $path=$r->path();
            if ($r->is('tech4learn/library*')) return $next($r);
            $feature=match(true) {
                $r->is('exams/*/analytics','exams/reports*','exams/study-card-reports*','ai/subjective/*')=>'results',
                $r->is('subjects*','topics*','stopics*','subtopics*','sections*','groups*','languages*')=>'subjects',
                $r->is('questions*','question/*','question-tags*','passages*','get-topics*','get-subtopics*','get-subjects*','export-questions*','upload-image','ai/translate','ai-content/generate','ai-regenerator/*')=>'questions',
                $r->is('exams*','exam-print*','exam-solutions*','exam-documents*','filters/dependent-options')=>'exams',
                $r->is('results*')=>'results',
                default=>null,
            };
            abort_unless($feature!==null,403,'This page is outside the exam workspace.');
        }
        abort_unless(Tech4LearnWorkspacePolicy::mayUse($feature,json_decode($w->restrictions,true,512,JSON_THROW_ON_ERROR)),403,'This feature is restricted by superadmin.');
        return $next($r);
    }
}
