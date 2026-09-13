<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,DB};
use App\Support\Tech4LearnWorkspacePolicy;
use App\Models\{User,Student};
use App\Services\Tech4LearnLaunchTickets;

final class Tech4LearnNativeController extends Controller
{
    public function launch(Request $r)
    {
        abort_unless($r->attributes->get('tech4learn_workspace_id'),404);
        return response()->view('tech4learn::launch')->header('Cache-Control','no-store')->header('Referrer-Policy','no-referrer');
    }
    public function accept(Request $r,Tech4LearnLaunchTickets $tickets)
    {
        $workspace=$r->attributes->get('tech4learn_workspace');
        abort_unless($workspace,404);
        $token=$r->input('ticket');
        abort_unless(is_string($token)&&preg_match('/^[a-f0-9]{64}$/D',$token),422,'Invalid launch link.');
        try { $ticket=$tickets->consume($workspace,$token); }
        catch(\DomainException $e) { abort($e->getCode(),$e->getMessage()); }
        if ($ticket->kind==='student') {
            $user=Student::where('organization_id',$workspace->organization_id)->where('status','Active')->findOrFail($ticket->external_id);
        } else {
            abort_unless($ticket->kind==='staff',403);
            $user=User::where('status',1)->findOrFail($ticket->external_id);
            abort_if($user->is_platform_admin,403);
            abort_unless(DB::table('organization_users')->where('organization_id',$workspace->organization_id)->where('user_id',$user->id)->where('status',1)->exists(),403);
        }
        Auth::guard('web')->logout();Auth::guard('student')->logout();
        $r->session()->invalidate();$r->session()->regenerateToken();
        Auth::guard($ticket->kind==='student'?'student':'web')->login($user);
        $r->session()->regenerate();
        $r->session()->put('tech4learn_workspace',['id'=>$workspace->id,'kind'=>$ticket->kind,'user'=>$user->id,'expires'=>time()+4*3600]);
        return redirect('/'.$ticket->entry)->header('Cache-Control','no-store');
    }
}
