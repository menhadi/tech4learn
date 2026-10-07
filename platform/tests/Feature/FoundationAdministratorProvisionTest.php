<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
use Tests\TestCase;

class FoundationAdministratorProvisionTest extends TestCase
{
    private string $canonical='11111111-1111-4111-8111-111111111111';
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'admin_provision_test','database.connections.admin_provision_test'=>[
            'driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true,
        ],'attendance.api_url'=>'https://api.example.invalid/api/v1']);
        Schema::create('organizations',function(Blueprint $t){$t->id();$t->string('status');$t->json('settings');});
        require_once database_path('migrations/2014_10_12_000000_create_users_table.php');
        (new \CreateUsersTable)->up();
        (require database_path('migrations/2026_07_04_000003_add_platform_admin_flag_to_users_table.php'))->up();
        (require database_path('migrations/2026_08_01_000000_create_foundation_platform_administrators.php'))->up();
        DB::table('organizations')->insert(['id'=>1,'status'=>'active','settings'=>'{"is_primary_platform":true}']);
    }
    protected function tearDown(): void { DB::purge('admin_provision_test');parent::tearDown(); }
    private function runProvision(string $id): void
    {
        $this->artisan('foundation:platform-admin', ['canonical-user-id'=>$id,'--confirm-reviewed-canonical-superadmin'=>true])->assertExitCode(0);
    }
    public function test_initial_account_and_exact_retry_preserve_password_and_identity(): void
    {
        $this->runProvision($this->canonical);
        $before=DB::table('users')->first();
        $this->assertTrue((bool)$before->is_platform_admin);
        $this->runProvision($this->canonical);
        $this->assertEquals($before,DB::table('users')->first());
        $this->assertSame(1,DB::table('foundation_platform_administrators')->count());
        $this->artisan('foundation:platform-admin',['canonical-user-id'=>'22222222-2222-4222-8222-222222222222','--confirm-reviewed-canonical-superadmin'=>true])->assertExitCode(1);
    }
    public function test_revocation_is_not_repaired(): void
    {
        $this->runProvision($this->canonical);
        DB::table('users')->update(['is_platform_admin'=>false]);
        $this->artisan('foundation:platform-admin',['canonical-user-id'=>$this->canonical,'--confirm-reviewed-canonical-superadmin'=>true])->assertExitCode(1);
        $this->assertFalse((bool)DB::table('users')->value('is_platform_admin'));
    }
    public function test_confirmation_and_bridge_are_required(): void
    {
        $this->artisan('foundation:platform-admin',['canonical-user-id'=>$this->canonical])->assertExitCode(1);
        config(['attendance.api_url'=>'']);
        $this->artisan('foundation:platform-admin',['canonical-user-id'=>$this->canonical,'--confirm-reviewed-canonical-superadmin'=>true])->assertExitCode(1);
        $this->assertSame(0,DB::table('users')->count());
    }
    public function test_existing_account_is_never_adopted_or_promoted(): void
    {
        DB::table('users')->insert(['name'=>'Synthetic existing user','username'=>'existing',
            'email'=>'existing@example.invalid','password'=>'unused','status'=>'Active','is_platform_admin'=>false]);
        $this->artisan('foundation:platform-admin',['canonical-user-id'=>$this->canonical,'--confirm-reviewed-canonical-superadmin'=>true])->assertExitCode(1);
        $this->assertSame(0,DB::table('foundation_platform_administrators')->count());
        $this->assertFalse((bool)DB::table('users')->value('is_platform_admin'));
    }
}
