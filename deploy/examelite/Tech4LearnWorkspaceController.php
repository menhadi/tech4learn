<?php
namespace App\Http\Controllers;
use App\Models\{Organization,Configuration,SaasPlan,User,Student};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Hash};
use App\Support\Tech4LearnWorkspacePolicy;

class Tech4LearnWorkspaceController extends Tech4LearnPlatformController
{
    public const FEATURES=['subjects','questions','exams','taking','results'];
    public function health(Request $r) {
        $tenant=$this->configuration($r)['_platform']['organization_id'];
        $ready=\Illuminate\Support\Facades\Schema::hasTable('tech4learn_workspaces')
            && app()->providerIsLoaded(\App\Providers\Tech4LearnWorkspaceProvider::class);
        return $this->reply($tenant,['ready'=>$ready]);
    }
    /** Native entitlement inventory; it does not claim that every feature has a T4L interface. */
    public function capabilities(Request $r,string $org) {
        $tenant=(int)$this->configuration($r)['_platform']['organization_id'];$this->uuid($org);
        abort_unless($r->query()===[]&&$r->all()===[],422);
        Organization::where('status','active')->findOrFail($tenant);
        $workspace=DB::table('tech4learn_workspaces')->where('id',$org)->where('source_organization_id',$tenant)->first();
        abort_unless($workspace!==null&&$workspace->organization_id!==null&&(int)$workspace->organization_id!==$tenant,404);
        $owner=Organization::with('plan')->where('status','active')->findOrFail($workspace->organization_id);
        abort_unless(($owner->settings['tech4learn_workspace']??null)===$org,404);
        $keys=(new \ReflectionClass(\App\Support\SaasAccess::class))->getConstant('PLAN_FEATURES');
        abort_unless(is_array($keys)&&array_is_list($keys)&&count($keys)<=100,503,'Unsupported native capability catalogue.');
        foreach($keys as $key)abort_unless(is_string($key)&&preg_match('/^[a-z][a-z0-9_]{0,63}$/D',$key),503);
        $keys=array_values(array_unique($keys));sort($keys);
        $restrictions=$this->restrictions(json_decode($workspace->restrictions,true,512,JSON_THROW_ON_ERROR));
        return $this->reply($tenant,[
            'revision'=>(int)$workspace->revision,
            'native_features'=>array_map(fn($key)=>['key'=>$key,'enabled'=>\App\Support\SaasAccess::featureEnabled($key,$owner)],$keys),
            'workspace_features'=>array_map(fn($key)=>['key'=>$key,'enabled'=>Tech4LearnWorkspacePolicy::mayUse($key,$restrictions)],self::FEATURES),
        ]);
    }
    private function restrictions($items): array {
        try { return Tech4LearnWorkspacePolicy::restrictions($items); }
        catch(\InvalidArgumentException $e) { abort(422,'Invalid feature restrictions.'); }
    }
    public function restrict(Request $r,string $org) {
        $tenant=$this->configuration($r)['_platform']['organization_id'];$this->uuid($org);
        $items=$this->restrictions($r->input('restrictions'));
        $revision=$r->input('revision');abort_unless(is_int($revision)&&$revision>0,422);
        DB::transaction(function()use($org,$tenant,$items,$revision){
            DB::table('tech4learn_workspaces')->insertOrIgnore(['id'=>$org,'source_organization_id'=>$tenant,'revision'=>0,'restrictions'=>'[]']);
            $row=DB::table('tech4learn_workspaces')->where('id',$org)->lockForUpdate()->first();
            abort_unless((int)$row->source_organization_id===$tenant,409);
            // Same revision and payload is a safe retry after a lost response.
            abort_unless((int)$row->revision===$revision-1 || ((int)$row->revision===$revision && json_decode($row->restrictions,true)===$items),409);
            DB::table('tech4learn_workspaces')->where('id',$org)->update(['revision'=>$revision,'restrictions'=>json_encode($items)]);
        });return $this->reply($tenant,['saved'=>true]);
    }
    public function launch(Request $r,string $org) {
        $tenant=$this->configuration($r)['_platform']['organization_id'];$this->uuid($org);
        abort_unless($r->input('provision_only')===true,410,'External workspaces have been retired.');
        $actor=$r->input('actor_id');$this->uuid((string)$actor);
        $name=$r->input('organisation_name');$actorName=$r->input('actor_name');
        abort_unless(is_string($name)&&mb_strlen($name)<=160&&is_string($actorName)&&mb_strlen($actorName)<=160,422);
        $feature=$r->input('feature');abort_unless(in_array($feature,self::FEATURES,true),422);
        abort_unless(is_int($r->input('revision'))&&$r->input('revision')>=0,422);
        $learner=$r->input('learner');
        if($feature==='taking'){abort_unless(is_array($learner)&&is_string($learner['name']??null)&&mb_strlen($learner['name'])<=255,422);$this->uuid((string)($learner['id']??''));}
        $host='t4l-'.str_replace('-','',$org).'.examelite.com';
        DB::transaction(function()use($r,$tenant,$org,$actor,$name,$actorName,$feature,$learner,$host){
            DB::table('tech4learn_workspaces')->insertOrIgnore(['id'=>$org,'source_organization_id'=>$tenant,'revision'=>0,'restrictions'=>'[]']);
            $w=DB::table('tech4learn_workspaces')->where('id',$org)->lockForUpdate()->first();
            abort_unless((int)$w->source_organization_id===$tenant,409);
            abort_unless((int)$w->revision===(int)$r->input('revision')&&json_decode($w->restrictions,true)===$this->restrictions($r->input('restrictions')),409);
            abort_unless(!in_array($feature,json_decode($w->restrictions,true),true),403);
            if(!$w->organization_id){
                $source=Organization::findOrFail($tenant);
                $features=(new \ReflectionClass(\App\Support\SaasAccess::class))->getConstant('PLAN_FEATURES');
                abort_unless(is_array($features),503,'Unsupported ExamElite plan configuration.');
                $plan=SaasPlan::firstOrCreate(['slug'=>'tech4learn-exam-workspace'],['name'=>'Tech4Learn exam workspace','price'=>0,'billing_cycle'=>'monthly','limits'=>[],'features'=>array_fill_keys($features,true),'is_default'=>false,'status'=>true]);
                $organization=Organization::create(['name'=>$name,'slug'=>'t4l-'.str_replace('-','',$org),'subdomain'=>'t4l-'.str_replace('-','',$org),'domain'=>$host,'status'=>'active','saas_plan_id'=>$plan->id,'settings'=>['tech4learn_workspace'=>$org]]);
                // Only presentation defaults are inherited. Never copy provider keys or messaging credentials.
                $safe=Configuration::where('organization_id',$tenant)->first()?->only(['timezone','theme_primary_color','theme_secondary_color','theme_header_bg','theme_header_text','theme_body_bg','theme_heading_color','theme_button_text','math_editor','translate','exam_feedback','date_format'])??[];
                Configuration::create(array_merge($safe,['organization_id'=>$organization->id,'name'=>$name,'organization_name'=>$name,'domain_name'=>$host]));
                $w->organization_id=$organization->id;
                DB::table('tech4learn_workspaces')->where('id',$org)->update(['organization_id'=>$organization->id]);
            }
            $kind=$feature==='taking'?'student':'staff';$local=$kind==='student'?$learner['id']:$actor;
            $mapping=DB::table('tech4learn_workspace_users')->where('workspace_id',$org)->where('local_id',$local)->where('kind',$kind)->first();
            if(!$mapping){
                if($kind==='staff'){
                    $key=hash('sha256',$org.':'.$actor);
                    $user=User::create(['name'=>$actorName,'username'=>'t4l-'.$key,'email'=>$key.'@tech4learn.invalid','password'=>Hash::make(bin2hex(random_bytes(32))),'ugroup_id'=>0,'status'=>1,'is_platform_admin'=>false]);
                    // No global admin role: ownership applies only inside the isolated organisation.
                    DB::table('organization_users')->insert(['organization_id'=>$w->organization_id,'user_id'=>$user->id,'role'=>'owner','status'=>1,'created_at'=>now(),'updated_at'=>now()]);
                }else{
                    $user=Student::create(['organization_id'=>$w->organization_id,'name'=>$learner['name'],'email'=>null,'phone'=>null,'password'=>Hash::make(bin2hex(random_bytes(32))),'status'=>'Active']);
                }
                DB::table('tech4learn_workspace_users')->insert(['workspace_id'=>$org,'local_id'=>$local,'kind'=>$kind,'external_id'=>$user->id]);
                $external=$user->id;
            }else $external=$mapping->external_id;
            DB::table('tech4learn_workspace_tickets')->where('expires_at','<',gmdate('Y-m-d H:i:s',time()-86400))->delete();
        });return $this->reply($tenant,['ready'=>true]);
    }
}
