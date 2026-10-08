<?php
namespace Tests\Feature;

use App\Models\{Organization,User};
use App\Support\AttendanceBridge;
use App\Services\AttendanceOnboarding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendanceOnboardingTest extends TestCase
{
    use RefreshDatabase;
    public function test_retry_route_and_pending_table_action_follow_platform_authority(): void
    {
        config(['attendance.api_url'=>'']);
        Organization::where('slug','examelite')->update(['domain'=>'platform-onboarding.test']);
        \Illuminate\Support\Facades\Cache::flush();\App\Support\Tenant::clear();
        $org=Organization::create(['name'=>'Synthetic UI onboarding','slug'=>'ui-onboarding','status'=>'active']);
        DB::table('attendance_onboarding_requests')->insert(['organization_id'=>$org->id,'created_at'=>now(),'updated_at'=>now()]);
        $actor=User::create(['name'=>'Synthetic UI operator','username'=>'synthetic-ui-operator','email'=>'ui-operator@example.invalid','password'=>'unused synthetic password','status'=>'Active','is_platform_admin'=>false]);
        $this->actingAs($actor,'web');
        $url='https://platform-onboarding.test/saas/organizations/'.$org->id.'/attendance-onboarding';
        $this->post($url)->assertForbidden();
        $actor->update(['is_platform_admin'=>true]);
        \Spatie\Permission\Models\Role::findOrCreate('admin','web');
        $actor->assignRole('admin');
        \Illuminate\Support\Facades\Auth::forgetGuards();$this->actingAs($actor->fresh(),'web');
        $this->get('https://platform-onboarding.test/saas')->assertOk()->assertSee('Retry attendance setup');
        $this->mock(AttendanceOnboarding::class,fn($mock)=>$mock->shouldReceive('deliver')->once()->andReturn(false));
        $this->post($url)->assertRedirect()->assertSessionHas('success','Attendance setup remains pending. Sign in to the linked platform account and retry.');
        DB::table('attendance_onboarding_requests')->where('organization_id',$org->id)->update(['status'=>'completed','completed_at'=>now()]);
        $this->get('https://platform-onboarding.test/saas')->assertOk()->assertDontSee('Retry attendance setup');
    }
    public function test_administrator_retry_http_route_rejects_staff_and_accepts_platform_operator(): void
    {
        config(['attendance.api_url'=>'']);
        Organization::where('slug','examelite')->update(['domain'=>'platform-admin-retry.test']);
        \Illuminate\Support\Facades\Cache::flush();\App\Support\Tenant::clear();
        $org=Organization::create(['name'=>'Synthetic retry tenant','slug'=>'retry-tenant','status'=>'active']);
        $user=User::create(['name'=>'Synthetic retry admin','username'=>'retry-admin','email'=>'retry-admin@example.invalid','password'=>'Synthetic unused password 42','status'=>'Active','is_platform_admin'=>false]);
        DB::table('organization_users')->insert(['organization_id'=>$org->id,'user_id'=>$user->id,'role'=>'admin','status'=>1,'created_at'=>now(),'updated_at'=>now()]);
        $actor=User::create(['name'=>'Synthetic retry operator','username'=>'retry-operator','email'=>'retry-operator@example.invalid','password'=>'Synthetic unused password 42','status'=>'Active','is_platform_admin'=>false]);
        $url='https://platform-admin-retry.test/saas/organizations/'.$org->id.'/admin-users/'.$user->id.'/attendance';
        $this->actingAs($actor,'web');$this->post($url,['password'=>'Synthetic retry password 42'])->assertForbidden();
        $actor->update(['is_platform_admin'=>true]);
        \Spatie\Permission\Models\Role::findOrCreate('admin','web');$actor->assignRole('admin');
        \Illuminate\Support\Facades\Auth::forgetGuards();$this->actingAs($actor->fresh(),'web');
        $this->mock(AttendanceBridge::class,fn($mock)=>$mock->shouldReceive('provisionAdministrator')->once()->andReturn(['created'=>false,'userId'=>'22222222-2222-4222-8222-222222222222']));
        $this->post($url,['password'=>'Synthetic retry password 42'])->assertRedirect()->assertSessionHas('success','Attendance administrator account is ready.');
        DB::table('organization_users')->where('user_id',$user->id)->update(['role'=>'staff']);
        $this->post($url,['password'=>'Synthetic retry password 42'])->assertForbidden();
    }

    public function test_stored_authority_and_pending_request_are_required_before_delivery(): void
    {
        $org=Organization::create(['name'=>'Synthetic restricted onboarding','slug'=>'restricted-onboarding','status'=>'active']);
        $actor=User::create(['name'=>'Synthetic ordinary operator','username'=>'synthetic-ordinary','email'=>'ordinary-onboarding@example.invalid','password'=>'unused synthetic password','status'=>'Active','is_platform_admin'=>false]);
        $bridge=\Mockery::mock(AttendanceBridge::class);
        $bridge->shouldNotReceive('provisionCompanion');
        $delivery=new AttendanceOnboarding($bridge);
        $request=Request::create('https://platform.test');
        $denied=function(int $status)use($delivery,$request,$actor,$org) {
            try {$delivery->deliver($request,$actor,$org->id);$this->fail('Denied delivery expected');}
            catch(\Symfony\Component\HttpKernel\Exception\HttpException $error){$this->assertSame($status,$error->getStatusCode());}
        };
        $actor->is_platform_admin=true; // A stale or client-modified object cannot grant authority.
        $denied(403);
        $actor->save();
        $denied(404); // An arbitrary organisation cannot be adopted without its pending request.
        DB::table('attendance_onboarding_requests')->insert(['organization_id'=>$org->id,'created_at'=>now(),'updated_at'=>now()]);
        $org->update(['status'=>'suspended']);
        $denied(403);
        $org->update(['status'=>'active','settings'=>['is_primary_platform'=>true]]);
        $denied(403);
        $this->assertDatabaseHas('attendance_onboarding_requests',['organization_id'=>$org->id,'status'=>'pending','attempts'=>0]);
    }
    public function test_failed_delivery_remains_pending_and_successful_retry_is_not_resent(): void
    {
        $org=Organization::create(['name'=>'Synthetic onboarding','slug'=>'synthetic-onboarding','status'=>'active']);
        $actor=User::create(['name'=>'Synthetic operator','username'=>'synthetic-operator','email'=>'operator@example.invalid','password'=>'unused synthetic password','status'=>'Active','is_platform_admin'=>true]);
        DB::table('attendance_onboarding_requests')->insert(['organization_id'=>$org->id,'created_at'=>now(),'updated_at'=>now()]);
        $bridge=\Mockery::mock(AttendanceBridge::class);
        $bridge->shouldReceive('provisionCompanion')->once()->andThrow(new \RuntimeException('Synthetic unavailable service'));
        $bridge->shouldReceive('provisionCompanion')->once()->andReturn(['created'=>false,'organisationId'=>'11111111-1111-4111-8111-111111111111']);
        $delivery=new AttendanceOnboarding($bridge);
        $request=Request::create('https://platform.test');
        $this->assertFalse($delivery->deliver($request,$actor,$org->id));
        $this->assertDatabaseHas('attendance_onboarding_requests',['organization_id'=>$org->id,'status'=>'pending','attempts'=>1,'completed_at'=>null]);
        $this->assertTrue($delivery->deliver($request,$actor,$org->id));
        $this->assertTrue($delivery->deliver($request,$actor,$org->id));
        $this->assertDatabaseHas('attendance_onboarding_requests',['organization_id'=>$org->id,'status'=>'completed','attempts'=>2]);
        $this->assertNotNull(DB::table('attendance_onboarding_requests')->where('organization_id',$org->id)->value('completed_at'));
    }
}
