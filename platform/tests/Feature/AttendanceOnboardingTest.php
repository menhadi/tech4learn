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
