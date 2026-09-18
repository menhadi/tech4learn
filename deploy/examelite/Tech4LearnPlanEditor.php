<?php
namespace App\Services;

use App\Models\{Organization,SaasPlan,User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Private central credential boundary; T4L reauthorises its superadmin.
 * ExamElite's controller owns plan validation and persistence.
 */
final class Tech4LearnPlanEditor
{
    public const FEATURES=['public_website','paid_packages','guest_exams','ai_generator','ai_translation','ai_regeneration','ai_content_generation','ai_subjective_analysis','ai_student_analysis','ai_seo','ai_platform_api','ai_settings','email_messaging','sms_messaging','reports','question_sharing','custom_theme','student_self_registration','flashcards','ai_flashcard_generation','exam_quality_audit','exam_quality_source','exam_quality_visual','exam_quality_ai'];
    public const LIMITS=['organizations','admins','students','exams','packages','questions','quality_audits_monthly','quality_questions_per_audit','quality_source_per_audit','quality_ai_per_audit','quality_visual_per_audit','quality_repairs_monthly'];

    public function createForActor(int $central,string $actor,string $requestId,array $fields):array {
        foreach([$actor,$requestId] as $value)abort_unless(preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/Di',$value),422);
        abort_unless(!array_is_list($fields)&&count($fields)<=40&&strlen(json_encode($fields,JSON_THROW_ON_ERROR))<=16384,422);
        ksort($fields);
        $fingerprint=hash('sha256',json_encode(['create-plan',$central,$actor,$fields],JSON_THROW_ON_ERROR));
        return DB::transaction(function()use($central,$actor,$requestId,$fields,$fingerprint){
            $organization=Organization::where('status','active')->lockForUpdate()->findOrFail($central);
            $prior=DB::table('tech4learn_central_requests')->where('organization_id',$central)->where('request_id',$requestId)->first();
            $mapping=DB::table('tech4learn_central_users')->where('organization_id',$central)->where('local_id',$actor)->lockForUpdate()->first();
            if(!$mapping){
                abort_unless(!$prior,403,'Central actor mapping is unavailable.');
                $key=hash('sha256','central:'.$central.':'.$actor);
                $user=User::create(['name'=>'Tech4Learn central author','username'=>'t4lc-'.$key,'email'=>$key.'@tech4learn.invalid','password'=>\Illuminate\Support\Facades\Hash::make(bin2hex(random_bytes(32))),'ugroup_id'=>0,'status'=>1,'is_platform_admin'=>false]);
                DB::table('organization_users')->insert(['organization_id'=>$central,'user_id'=>$user->id,'role'=>'owner','status'=>1,'created_at'=>now(),'updated_at'=>now()]);
                DB::table('tech4learn_central_users')->insert(['organization_id'=>$central,'local_id'=>$actor,'external_id'=>$user->id]);
            }else $user=User::where('status',1)->where('is_platform_admin',false)->lockForUpdate()->findOrFail($mapping->external_id);
            abort_unless(DB::table('organization_users')->where('organization_id',$central)->where('user_id',$user->id)->where('status',1)->whereIn('role',['owner','admin'])->lockForUpdate()->first()!==null,403);
            if($prior){abort_unless(hash_equals($prior->fingerprint,$fingerprint),409,'Request ID already used.');return json_decode($prior->result,true,512,JSON_THROW_ON_ERROR);}
            $app=app();$oldRequest=$app->make('request');$oldRedirect=$app->make('redirect');
            $guard=\Illuminate\Support\Facades\Auth::guard('web');$oldUser=$guard->user();
            $session=new \Illuminate\Session\Store('t4l-plan-create',new \Illuminate\Session\ArraySessionHandler(5));$session->start();
            $request=Request::create('https://'.$organization->domain.'/saas','POST');
            $request->setLaravelSession($session);$request->setUserResolver(fn()=>$user);
            $redirect=new \Illuminate\Routing\Redirector($app->make('url'));$redirect->setSession($session);
            $app->instance('request',$request);$app->instance('redirect',$redirect);$guard->setUser($user);\App\Support\Tenant::clear();
            try{
                abort_unless((int)\App\Support\Tenant::resolve($organization->domain)->id===$central,403);
                $result=$this->create($central,$fields,$request);
                DB::table('tech4learn_central_requests')->insert(['organization_id'=>$central,'request_id'=>$requestId,'actor_id'=>$actor,'fingerprint'=>$fingerprint,'result'=>json_encode($result,JSON_THROW_ON_ERROR),'created_at'=>now()]);
                return $result;
            }finally{
                $oldUser?$guard->setUser($oldUser):$guard->forgetUser();
                $app->instance('request',$oldRequest);$app->instance('redirect',$oldRedirect);\App\Support\Tenant::clear();$session->invalidate();
            }
        });
    }

    public function create(int $central,array $fields,Request $request):array {
        $allowed=array_merge(['name','price','billing_cycle','status'],array_map(fn($key)=>'feature_'.$key,self::FEATURES),array_map(fn($key)=>'limit_'.$key,self::LIMITS));
        abort_unless(array_diff(array_keys($fields),$allowed)===[],422);
        // All native capabilities begin available unless explicitly restricted.
        // Creating a plan never changes the global default or assigns customers.
        $values=array_replace(['price'=>0,'billing_cycle'=>'monthly','status'=>true],array_fill_keys(array_map(fn($key)=>'feature_'.$key,self::FEATURES),true),$fields,['is_default'=>false]);
        return DB::transaction(function()use($central,$values,$request){
            Organization::where('status','active')->lockForUpdate()->findOrFail($central);
            $lastId=(int)SaasPlan::max('id');
            $request->replace($values);
            $request->session()->forget(['success','error','errors']);
            app(\App\Http\Controllers\SaasController::class)->storePlan($request);
            abort_unless($request->session()->has('success')&&!$request->session()->has('errors')&&!$request->session()->has('error'),422,'Native plan creation was not confirmed.');
            // Fail closed if a concurrent native writer makes discovery ambiguous.
            $created=SaasPlan::where('id','>',$lastId)->where('name',$values['name'])->lockForUpdate()->limit(2)->get();
            abort_unless($created->count()===1,409,'Plan creation changed concurrently.');
            $plan=$created->first();
            abort_unless(!$plan->is_default,500);
            return ['plan_id'=>(int)$plan->id,'revision'=>Tech4LearnPlanAssignment::revision($plan),'name'=>$plan->name];
        });
    }
}
