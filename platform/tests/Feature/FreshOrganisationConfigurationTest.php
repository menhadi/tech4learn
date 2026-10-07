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
}
