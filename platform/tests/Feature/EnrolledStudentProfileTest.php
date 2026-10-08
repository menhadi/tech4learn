<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\EnrolledStudentProfile;
use App\Support\Tenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,Cache,DB,Schema};
use Tests\TestCase;

class EnrolledStudentProfileTest extends TestCase
{
    private array $snapshot=['nativeOrganisationId'=>'1','learnerId'=>'11111111-1111-4111-8111-111111111111',
        'revision'=>1,'name'=>'Synthetic Student','code'=>'TEST-001','archived'=>false,'demo'=>true];
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'student_profile_test','database.connections.student_profile_test'=>[
            'driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true],
            'app.url'=>'https://platform.example.invalid','cache.default'=>'array','hashing.bcrypt.rounds'=>4]);
        Schema::create('organizations',function(Blueprint $t){$t->id();foreach(['name','slug','domain','status'] as $f)$t->string($f);$t->string('subdomain')->nullable();$t->json('settings')->nullable();});
        Schema::create('users',function(Blueprint $t){$t->id();$t->string('name');$t->string('status');$t->boolean('deleted')->default(false);$t->boolean('is_platform_admin')->default(false);});
        Schema::create('organization_users',function(Blueprint $t){$t->integer('organization_id');$t->integer('user_id');$t->string('role');$t->integer('status');});
        Schema::create('students',function(Blueprint $t){$t->id();$t->unsignedBigInteger('organization_id');
            foreach(['name','password','address','status'] as $f)$t->string($f);foreach(['email','phone','enroll'] as $f)$t->string($f)->nullable();$t->boolean('is_demo')->default(false);$t->timestamps();});
        (require database_path('migrations/2026_10_08_000006_create_foundation_student_profiles.php'))->up();
        foreach([1,2] as $id)DB::table('organizations')->insert(['id'=>$id,'name'=>'Synthetic tenant '.$id,'slug'=>'tenant-'.$id,'domain'=>'tenant-'.$id.'.example.invalid','status'=>'active']);
        DB::table('users')->insert(['id'=>1,'name'=>'Synthetic owner','status'=>'Active']);
        DB::table('organization_users')->insert(['organization_id'=>1,'user_id'=>1,'role'=>'admin','status'=>1]);
        Cache::flush();Tenant::clear();Auth::forgetGuards();Auth::guard('web')->setUser(User::findOrFail(1));
    }
    protected function tearDown(): void
    {
        Tenant::clear();Auth::forgetGuards();DB::purge('student_profile_test');parent::tearDown();
    }
    private function apply(array $snapshot): array
    {
        $request=Request::create('https://tenant-1.example.invalid/enrolment');app()->instance('request',$request);
        return (new EnrolledStudentProfile)->apply($request,$snapshot);
    }
    private function denied(int $status,callable $fn): void
    {
        try{$fn();$this->fail('Expected denial.');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame($status,$e->getStatusCode());}
    }
    public function test_profile_delivery_is_idempotent_and_does_not_enable_login_or_invent_contacts(): void
    {
        $result=$this->apply($this->snapshot);$this->assertSame($result,$this->apply($this->snapshot));
        $this->assertSame(1,DB::table('students')->count());
        $student=DB::table('students')->first();$password=$student->password;
        $this->assertSame('Suspend',$student->status);$this->assertNull($student->email);$this->assertNull($student->phone);
        DB::table('students')->where('id',$student->id)->update(['email'=>'synthetic@example.invalid','phone'=>'9000000001','status'=>'Active']);
        $changed=$this->snapshot;$changed['revision']=2;$changed['name']='Synthetic Updated';
        $this->assertSame($result['nativeStudentId'],$this->apply($changed)['nativeStudentId']);
        $student=DB::table('students')->first();$this->assertSame($password,$student->password);$this->assertSame('synthetic@example.invalid',$student->email);$this->assertSame('Active',$student->status);
        $changed['revision']=3;$changed['archived']=true;$this->apply($changed);
        $changed['revision']=4;$changed['archived']=false;$this->apply($changed);
        $this->assertSame('Suspend',DB::table('students')->first()->status);
    }
    public function test_stale_or_changed_retries_and_unlinked_code_collisions_do_not_merge_students(): void
    {
        $this->apply($this->snapshot);$changed=$this->snapshot;$changed['name']='Changed';
        $this->denied(409,fn()=>$this->apply($changed));$changed['revision']=2;$this->apply($changed);
        $this->denied(409,fn()=>$this->apply($this->snapshot));
        $other=$this->snapshot;$other['learnerId']='22222222-2222-4222-8222-222222222222';
        $this->denied(409,fn()=>$this->apply($other));$this->assertSame(1,DB::table('students')->count());
    }
    public function test_host_tenant_and_current_stored_administrator_membership_are_required(): void
    {
        $other=$this->snapshot;$other['nativeOrganisationId']='2';$this->denied(404,fn()=>$this->apply($other));
        DB::table('organization_users')->update(['role'=>'staff']);$this->denied(403,fn()=>$this->apply($this->snapshot));
        DB::table('organization_users')->update(['role'=>'admin','status'=>0]);$this->denied(403,fn()=>$this->apply($this->snapshot));
        $this->assertSame(0,DB::table('students')->count());
    }
    public function test_failed_mapping_write_rolls_back_the_new_native_profile(): void
    {
        DB::statement("CREATE TRIGGER reject_synthetic_profile BEFORE INSERT ON foundation_student_profiles BEGIN SELECT RAISE(ABORT,'synthetic mapping failure'); END");
        try {$this->apply($this->snapshot);$this->fail('Expected mapping failure.');}
        catch (\Illuminate\Database\QueryException $error) {$this->assertSame(0,DB::table('students')->count());}
        $this->assertSame(0,DB::table('foundation_student_profiles')->count());
    }
}
