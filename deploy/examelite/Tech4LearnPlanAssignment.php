<?php
namespace App\Services;

use App\Models\{Organization,SaasPlan};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Private native persistence helper. The calling boundary must authorise the
 * central actor and own its request ledger; this class has no public route.
 */
final class Tech4LearnPlanAssignment
{
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
