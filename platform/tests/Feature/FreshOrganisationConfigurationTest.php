<?php
namespace Tests\Feature;
use App\Models\{Organization,Configuration};
use App\Http\Controllers\SaasController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class FreshOrganisationConfigurationTest extends TestCase
{
    use RefreshDatabase;
    public function test_successful_creation_saves_independent_configuration_and_scoped_audit(): void
    {
        $request=Request::create('https://platform.test/saas/organizations','POST',['name'=>'Synthetic complete creation','domain'=>'complete-creation.test','status'=>'active']);
        $response=app(SaasController::class)->storeOrganization($request);
        $this->assertSame(302,$response->getStatusCode());
        $org=Organization::where('domain','complete-creation.test')->sole();
        $this->assertFalse((bool)$org->settings['is_primary_platform']);
        $config=Configuration::where('organization_id',$org->id)->sole();
        $this->assertSame($org->name,$config->organization_name);
        $this->assertEmpty($config->openai_api_key);
        $audit=\App\Models\AuditLog::where('action','organization.created')->where('auditable_id',$org->id)->sole();
        $this->assertSame($org->id,(int)$audit->organization_id);
        $this->assertSame(Organization::class,$audit->auditable_type);
        $this->assertSame(['name'=>$org->name],$audit->metadata);
    }
    public function test_new_staff_account_does_not_receive_global_admin_role(): void
    {
        $org=Organization::create(['name'=>'Synthetic staff tenant','slug'=>'staff-tenant','domain'=>'staff-tenant.test','status'=>'active']);
        \Spatie\Permission\Models\Role::findOrCreate('admin','web');
        $request=Request::create('https://platform.test/saas/organizations','POST',['name'=>'Synthetic ordinary staff','email'=>'ordinary@example.invalid','password'=>'long synthetic password','organization_role'=>'staff','status'=>'Active']);
        app(SaasController::class)->storeOrganizationAdmin($request,$org);
        $user=\App\Models\User::where('email','ordinary@example.invalid')->sole();
        $this->assertFalse($user->hasRole('admin'));
        $this->assertFalse((bool)$user->is_platform_admin);
        $this->assertDatabaseHas('organization_users',['organization_id'=>$org->id,'user_id'=>$user->id,'role'=>'staff']);
    }
    public function test_new_configuration_does_not_copy_another_organisations_credentials(): void
    {
        $source=Organization::create(['name'=>'Synthetic source','slug'=>'synthetic-source','domain'=>'source.test','status'=>'active']);
        Configuration::create(['organization_id'=>$source->id,'name'=>'Synthetic source','openai_api_key'=>'synthetic-private-source','google_gemini_api_key'=>'synthetic-private-google']);
        $fresh=Organization::create(['name'=>'Synthetic fresh','slug'=>'synthetic-fresh','domain'=>'fresh.test','status'=>'active']);
        $method=new \ReflectionMethod(SaasController::class,'ensureOrganizationConfiguration');
        $method->invoke(app(SaasController::class),$fresh);
        $config=Configuration::where('organization_id',$fresh->id)->sole();
        $this->assertSame('Synthetic fresh',$config->name);
        $this->assertSame('fresh.test',$config->domain_name);
        $this->assertEmpty($config->openai_api_key);
        $this->assertEmpty($config->google_gemini_api_key);
    }
    public function test_configuration_failure_rolls_back_organisation_creation(): void
    {
        $event='eloquent.creating: '.Configuration::class;
        Event::listen($event,fn()=>throw new \RuntimeException('Synthetic configuration failure'));
        try {
            $request=Request::create('https://platform.test/saas/organizations','POST',['name'=>'Synthetic rollback','domain'=>'rollback.test','status'=>'active']);
            try {app(SaasController::class)->storeOrganization($request);$this->fail('Failure expected');}
            catch(\RuntimeException $error){$this->assertSame('Synthetic configuration failure',$error->getMessage());}
            $this->assertDatabaseMissing('organizations',['domain'=>'rollback.test']);
        } finally {Event::forget($event);}
    }
    public function test_audit_failure_rolls_back_organisation_and_configuration(): void
    {
        $event='eloquent.creating: '.\App\Models\AuditLog::class;
        Event::listen($event,fn()=>throw new \RuntimeException('Synthetic audit failure'));
        $before=Configuration::count();
        try {
            $request=Request::create('https://platform.test/saas/organizations','POST',['name'=>'Synthetic audit rollback','domain'=>'audit-rollback.test','status'=>'active']);
            try {app(SaasController::class)->storeOrganization($request);$this->fail('Failure expected');}
            catch(\RuntimeException $error){$this->assertSame('Synthetic audit failure',$error->getMessage());}
            $this->assertDatabaseMissing('organizations',['domain'=>'audit-rollback.test']);
            $this->assertSame($before,Configuration::count());
        } finally {Event::forget($event);}
    }
    public function test_platform_provider_lookup_never_falls_back_to_a_tenant_configuration(): void
    {
        Organization::query()->update(['settings'=>json_encode(['is_primary_platform'=>false])]);
        $tenant=Organization::create(['name'=>'Synthetic provider tenant','slug'=>'provider-tenant','domain'=>'provider.test','status'=>'active','settings'=>['is_primary_platform'=>false]]);
        Configuration::create(['organization_id'=>$tenant->id,'openai_api_key'=>'synthetic-tenant-private']);
        $method=new \ReflectionMethod(\App\Support\AiProvider::class,'platformConfiguration');
        $this->assertNull($method->invoke(null));
        $platform=Organization::create(['name'=>'Synthetic platform','slug'=>'provider-platform','domain'=>'platform.test','status'=>'active','settings'=>['is_primary_platform'=>true]]);
        $this->assertNull($method->invoke(null));
        $config=Configuration::create(['organization_id'=>$platform->id,'openai_api_key'=>'synthetic-platform-private']);
        $this->assertSame($config->id,$method->invoke(null)->id);
        $tenant->update(['settings'=>['is_primary_platform'=>true]]);
        $this->assertNull($method->invoke(null));
    }
    public function test_missing_tenant_configuration_returns_neutral_defaults(): void
    {
        \Illuminate\Support\Facades\Cache::flush();\App\Support\Tenant::clear();
        $source=Organization::create(['name'=>'Synthetic private source','slug'=>'private-source','domain'=>'private-source.test','status'=>'active']);
        Configuration::create(['organization_id'=>$source->id,'name'=>'Synthetic private source','openai_api_key'=>'synthetic-private-source']);
        Configuration::create(['organization_id'=>null,'name'=>'Synthetic private source','openai_api_key'=>'synthetic-legacy-private']);
        Organization::create(['name'=>'Synthetic unconfigured','slug'=>'unconfigured','domain'=>'unconfigured.test','status'=>'active']);
        foreach(['unconfigured.test','unknown.example.invalid'] as $host) {
            app()->instance('request',Request::create('https://'.$host));\App\Support\Tenant::clear();
            $config=getConfiguration();
            $this->assertFalse($config->exists);
            $this->assertEmpty($config->openai_api_key);
            $this->assertNotSame('Synthetic private source',$config->name);
        }
    }
}
