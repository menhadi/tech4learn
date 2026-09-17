<?php
namespace App\Services;

use App\Models\{Organization,SaasPlan,User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Private native persistence helper. The calling boundary must authorise the
 * central actor and own its request ledger; this class has no public route.
 */
final class Tech4LearnPlanAssignment
{
    /** Private credential boundary must supply a freshly authorised T4L superadmin.
     * Require an existing central actor; never elevate a native account.
     */
    public function assign(int $central,string $workspace,string $actor,int $planId,string $ownerRevision,string $planRevision,string $requestId):array {
        foreach([$workspace,$actor,$requestId] as $value)abort_unless(is_string($value)&&preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/Di',$value),422);
        abort_unless($planId>0&&preg_match('/^[a-f0-9]{64}$/D',$ownerRevision)&&preg_match('/^[a-f0-9]{64}$/D',$planRevision),422);
        $fingerprint=hash('sha256',json_encode(['assign-plan',$central,$workspace,$actor,$planId,$ownerRevision,$planRevision],JSON_THROW_ON_ERROR));
        return DB::transaction(function()use($central,$workspace,$actor,$planId,$ownerRevision,$planRevision,$requestId,$fingerprint){
            $organization=Organization::where('status','active')->lockForUpdate()->findOrFail($central);
            $mapping=DB::table('tech4learn_central_users')->where('organization_id',$central)->where('local_id',$actor)->lockForUpdate()->first();
            abort_unless($mapping!==null,403,'Central actor is not provisioned.');
            $user=User::where('status',1)->where('is_platform_admin',false)->lockForUpdate()->findOrFail($mapping->external_id);
            abort_unless(DB::table('organization_users')->where('organization_id',$central)->where('user_id',$user->id)->where('status',1)->whereIn('role',['owner','admin'])->lockForUpdate()->first()!==null,403);
            // Recheck mapping and actor before replay, including after revocation.
            $workspaceMapping=DB::table('tech4learn_workspaces')->where('id',$workspace)->where('source_organization_id',$central)->lockForUpdate()->first();
            abort_unless($workspaceMapping!==null&&$workspaceMapping->organization_id!==null&&(int)$workspaceMapping->organization_id!==$central,404);
            $owner=Organization::where('status','active')->lockForUpdate()->findOrFail($workspaceMapping->organization_id);
            abort_unless(($owner->settings['tech4learn_workspace']??null)===$workspace&&$owner->slug!=='examelite',404);
            $prior=DB::table('tech4learn_central_requests')->where('organization_id',$central)->where('request_id',$requestId)->first();
            if($prior){abort_unless(hash_equals($prior->fingerprint,$fingerprint),409,'Request ID already used.');return json_decode($prior->result,true,512,JSON_THROW_ON_ERROR);}
            $app=app();$oldRequest=$app->make('request');$oldRedirect=$app->make('redirect');
            $guard=\Illuminate\Support\Facades\Auth::guard('web');$oldUser=$guard->user();
            $session=new \Illuminate\Session\Store('t4l-plan-assignment',new \Illuminate\Session\ArraySessionHandler(5));$session->start();
            $request=Request::create('https://'.$organization->domain.'/saas','POST');
            $request->setLaravelSession($session);$request->setUserResolver(fn()=>$user);
            $redirect=new \Illuminate\Routing\Redirector($app->make('url'));$redirect->setSession($session);
            $app->instance('request',$request);$app->instance('redirect',$redirect);$guard->setUser($user);\App\Support\Tenant::clear();
            try{
                abort_unless((int)\App\Support\Tenant::resolve($organization->domain)->id===$central,403);
                $result=$this->apply($central,$workspace,$planId,$ownerRevision,$planRevision,$request);
                DB::table('tech4learn_central_requests')->insert(['organization_id'=>$central,'request_id'=>$requestId,'actor_id'=>$actor,'fingerprint'=>$fingerprint,'result'=>json_encode($result,JSON_THROW_ON_ERROR),'created_at'=>now()]);
                return $result;
            }finally{
                $oldUser?$guard->setUser($oldUser):$guard->forgetUser();
                $app->instance('request',$oldRequest);$app->instance('redirect',$oldRedirect);\App\Support\Tenant::clear();$session->invalidate();
            }
        });
    }
    public static function revision($model):string {
        return hash('sha256',json_encode($model->getRawOriginal(),JSON_THROW_ON_ERROR));
    }
    public function apply(int $central,string $workspace,int $planId,string $ownerRevision,string $planRevision,Request $request):array {
        return DB::transaction(function()use($central,$workspace,$planId,$ownerRevision,$planRevision,$request){
            Organization::where('status','active')->findOrFail($central);
            $mapping=DB::table('tech4learn_workspaces')->where('id',$workspace)->where('source_organization_id',$central)->lockForUpdate()->first();
            abort_unless($mapping!==null&&$mapping->organization_id!==null&&(int)$mapping->organization_id!==$central,404);
            $owner=Organization::where('status','active')->lockForUpdate()->findOrFail($mapping->organization_id);
            abort_unless(($owner->settings['tech4learn_workspace']??null)===$workspace&&$owner->slug!=='examelite',404);
            $plan=SaasPlan::where('status',true)->lockForUpdate()->findOrFail($planId);
            abort_unless(hash_equals(self::revision($owner),$ownerRevision)&&hash_equals(self::revision($plan),$planRevision),409,'Organisation or plan changed. Reload before assigning.');
            // The native organisation form owns validation and persistence.
            // Rebuild its input from stored values; do not accept other edits.
            $values=$owner->only(['name','domain','subdomain','email','phone','status','trial_ends_at','subscription_ends_at']);
            foreach(['trial_ends_at','subscription_ends_at'] as $key)$values[$key]=$owner->getRawOriginal($key);
            $request->replace($values+['saas_plan_id'=>$planId]);
            app(\App\Http\Controllers\SaasController::class)->updateOrganization($request,$owner);
            abort_unless($request->session()->has('success')&&!$request->session()->has('errors')&&!$request->session()->has('error'),422,'Native plan assignment was not confirmed.');
            $owner->refresh();
            abort_unless((int)$owner->saas_plan_id===$planId,500,'Native plan assignment was not persisted.');
            return ['plan_id'=>$planId,'assignment_revision'=>self::revision($owner)];
        });
    }
}
