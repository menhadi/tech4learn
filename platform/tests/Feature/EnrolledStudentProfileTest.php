<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\EnrolledStudentProfile;
use App\Services\StudentDeliveryReceipt;
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
        Schema::create('groups',function(Blueprint $t){$t->id();$t->unsignedBigInteger('organization_id');$t->string('group_name')->default('');});
        Schema::create('student_groups',function(Blueprint $t){$t->id();$t->unsignedBigInteger('student_id');$t->unsignedBigInteger('group_id');$t->timestamps();});
        (require database_path('migrations/2026_10_08_000007_create_foundation_section_groups.php'))->up();
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

    public function test_receipts_prove_stored_profile_without_contacts_and_recheck_membership(): void
    {
        $this->apply($this->snapshot);
        $original=storage_path();$directory=sys_get_temp_dir().'/student-proof-'.bin2hex(random_bytes(8));
        mkdir($directory.'/app/private',0700,true);app()->useStoragePath($directory);
        $path=$directory.'/app/private/student-delivery-signing.pem';
        try {
            $key=openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($key,$pem);file_put_contents($path,$pem);chmod($path,0600);
            $request=Request::create('https://tenant-1.example.invalid/enrolment');
            $result=(new StudentDeliveryReceipt)->issue($request,$this->snapshot['learnerId'],1);
            $decode=fn($s)=>base64_decode(strtr($s,'-_','+/'));
            $bytes=$decode($result['receipt']);$payload=json_decode($bytes,true);
            $this->assertSame(1,openssl_verify($bytes,$decode($result['signature']),openssl_pkey_get_details($key)['key'],OPENSSL_ALGO_SHA256));
            $this->assertSame(['issuer','nativeOrganisationId','nativeUserId','nativeStudentId','learnerId','revision','issuedAt'],array_keys($payload));
            $this->assertSame('1',$payload['nativeStudentId']);
            $this->denied(409,fn()=>(new StudentDeliveryReceipt)->issue($request,$this->snapshot['learnerId'],2));
            DB::table('organization_users')->update(['status'=>0]);
            $this->denied(403,fn()=>(new StudentDeliveryReceipt)->issue($request,$this->snapshot['learnerId'],1));
        } finally {
            app()->useStoragePath($original);if(is_file($path))unlink($path);
            rmdir($directory.'/app/private');rmdir($directory.'/app');rmdir($directory);
        }
    }

    public function test_installation_key_preparation_is_explicit_and_never_rotates_existing_material(): void
    {
        $original=storage_path();$directory=sys_get_temp_dir().'/student-key-'.bin2hex(random_bytes(8));
        mkdir($directory,0700);app()->useStoragePath($directory);
        $path=$directory.'/app/private/student-delivery-signing.pem';$public=$directory.'/app/private/student-delivery-signing-public.pem';
        try {
            $this->artisan('foundation:student-signing-key')->assertExitCode(1);
            $this->assertFileDoesNotExist($path);
            $this->artisan('foundation:student-signing-key',['--confirm-native-installation'=>true])->assertExitCode(0);
            $private=file_get_contents($path);$publicBytes=file_get_contents($public);
            $this->assertStringContainsString('BEGIN PUBLIC KEY',$publicBytes);
            $this->artisan('foundation:student-signing-key',['--confirm-native-installation'=>true])->assertExitCode(0);
            $this->assertSame($private,file_get_contents($path));
            file_put_contents($public,'invalid synthetic public key');
            $this->artisan('foundation:student-signing-key',['--confirm-native-installation'=>true])->assertExitCode(1);
            $this->assertSame($private,file_get_contents($path));
            $this->assertSame('invalid synthetic public key',file_get_contents($public));
        } finally {
            app()->useStoragePath($original);foreach([$path,$public] as $file)if(is_file($file))unlink($file);
            if(is_dir($directory.'/app/private'))rmdir($directory.'/app/private');
            if(is_dir($directory.'/app'))rmdir($directory.'/app');rmdir($directory);
        }
    }

    public function test_delivery_retries_reuse_profile_after_remote_failure_and_send_only_signed_ids(): void
    {
        $original=storage_path();$directory=sys_get_temp_dir().'/student-delivery-'.bin2hex(random_bytes(8));
        mkdir($directory.'/app/private',0700,true);app()->useStoragePath($directory);
        $path=$directory.'/app/private/student-delivery-signing.pem';
        try {
            config(['attendance.api_url'=>'https://canonical.example.invalid/api/v1','attendance.student_delivery_enabled'=>true]);
            $key=openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($key,$pem);file_put_contents($path,$pem);chmod($path,0600);
            $learner=$this->snapshot['learnerId'];$org='33333333-3333-4333-8333-333333333333';
            $context=['nativeOrganisationId'=>'1','nativeUserId'=>'1','organisation'=>['id'=>$org],
                'permissions'=>['learners.view','learners.edit'],'scope'=>['type'=>'organisation','ids'=>[]]];
            $snapshot=$this->snapshot+['nativeUserId'=>'1','organisationId'=>$org,'version'=>1,'groupId'=>$org];
            $request=Request::create('https://tenant-1.example.invalid/enrolment','POST',[],['t4l_session'=>str_repeat('a',64)]);
            $request->setUserResolver(fn()=>Auth::user());app()->instance('request',$request);
            $history=[];$response=fn($v,$status=200)=>new \GuzzleHttp\Psr7\Response($status,['Content-Type'=>'application/json'],json_encode($v));
            $client=function($ack) use($context,$snapshot,$response,&$history) {
                $queue=[];for($i=0;$i<2;$i++)array_push($queue,$response($context),$response($snapshot),$response($context),$response($snapshot));
                $queue[]=$ack;$stack=\GuzzleHttp\HandlerStack::create(new \GuzzleHttp\Handler\MockHandler($queue));
                $stack->push(\GuzzleHttp\Middleware::history($history));return new \GuzzleHttp\Client(['handler'=>$stack]);
            };
            $bridge=new \App\Support\AttendanceBridge;
            $this->denied(502,fn()=>$bridge->deliverStudent($request,$learner,$client($response([],503))));
            $this->assertSame(1,DB::table('students')->count());$password=DB::table('students')->first()->password;
            $result=$bridge->deliverStudent($request,$learner,$client($response(['nativeStudentId'=>'1','learnerId'=>$learner,'revision'=>1,'delivered'=>true])));
            $this->assertTrue($result['delivered']);$this->assertSame(1,DB::table('students')->count());
            $this->assertSame($password,DB::table('students')->first()->password);
            $last=end($history)['request'];$this->assertSame('POST',$last->getMethod());
            $body=json_decode((string)$last->getBody(),true);$this->assertSame(['keyId','receipt','signature'],array_keys($body));
            $bytes=base64_decode(strtr($body['receipt'],'-_','+/'));
            $this->assertSame(1,openssl_verify($bytes,base64_decode(strtr($body['signature'],'-_','+/')),openssl_pkey_get_details($key)['key'],OPENSSL_ALGO_SHA256));
            $this->assertArrayNotHasKey('name',json_decode($bytes,true));
        } finally {
            app()->useStoragePath($original);if(is_file($path))unlink($path);
            rmdir($directory.'/app/private');rmdir($directory.'/app');rmdir($directory);
        }
    }
    public function test_section_delivery_owns_only_its_pivot_and_preserves_manual_memberships(): void
    {
        $snapshot=$this->snapshot+['groupId'=>'33333333-3333-4333-8333-333333333333'];
        $this->apply($snapshot);$student=DB::table('students')->first()->id;
        DB::table('groups')->insert([['id'=>1,'organization_id'=>1],['id'=>2,'organization_id'=>1],['id'=>3,'organization_id'=>2]]);
        DB::table('foundation_section_groups')->insert(['organization_id'=>1,'section_id'=>$snapshot['groupId'],'group_id'=>1]);
        $manual=DB::table('student_groups')->insertGetId(['student_id'=>$student,'group_id'=>2]);
        $request=Request::create('https://tenant-1.example.invalid/enrolment');$service=new \App\Services\EnrolledStudentGroup;
        $this->assertTrue($service->apply($request,$snapshot)['assigned']);$service->apply($request,$snapshot);
        $this->assertSame(2,DB::table('student_groups')->count());
        $owned=DB::table('foundation_student_group_deliveries')->first()->pivot_id;$this->assertNotNull($owned);
        DB::table('foundation_section_groups')->update(['group_id'=>2]);$service->apply($request,$snapshot);
        $this->assertSame(1,DB::table('student_groups')->count());$this->assertSame($manual,DB::table('student_groups')->first()->id);
        $this->assertNull(DB::table('foundation_student_group_deliveries')->first()->pivot_id);
        DB::table('student_groups')->where('id',$manual)->delete();
        $this->denied(409,fn()=>$service->apply($request,$snapshot));
        $this->assertSame(0,DB::table('student_groups')->count());
        DB::table('student_groups')->insert(['id'=>$manual,'student_id'=>$student,'group_id'=>2]);
        $snapshot['revision']=2;$snapshot['archived']=true;$this->apply($snapshot);$service->apply($request,$snapshot);
        $this->assertSame($manual,DB::table('student_groups')->first()->id);
    }
    public function test_deleted_owned_membership_requires_review_and_foreign_mapping_is_rejected(): void
    {
        $snapshot=$this->snapshot+['groupId'=>'33333333-3333-4333-8333-333333333333'];$this->apply($snapshot);
        DB::table('groups')->insert([['id'=>1,'organization_id'=>1],['id'=>3,'organization_id'=>2]]);
        try {DB::table('foundation_section_groups')->insert(['organization_id'=>1,'section_id'=>$snapshot['groupId'],'group_id'=>3]);$this->fail('Foreign group mapping must fail.');}
        catch(\Illuminate\Database\QueryException $e){$this->assertSame(0,DB::table('foundation_section_groups')->count());}
        DB::table('foundation_section_groups')->insert(['organization_id'=>1,'section_id'=>$snapshot['groupId'],'group_id'=>1]);
        $request=Request::create('https://tenant-1.example.invalid/enrolment');$service=new \App\Services\EnrolledStudentGroup;
        $service->apply($request,$snapshot);DB::table('student_groups')->delete();
        $this->denied(409,fn()=>$service->apply($request,$snapshot));$this->assertSame(0,DB::table('student_groups')->count());
        DB::table('organization_users')->update(['status'=>0]);$this->denied(403,fn()=>$service->apply($request,$snapshot));
    }
    public function test_explicit_section_mapping_is_tenant_bound_versioned_and_never_repairs_revocation(): void
    {
        DB::table('groups')->insert([['id'=>1,'organization_id'=>1],['id'=>2,'organization_id'=>1],['id'=>3,'organization_id'=>2]]);
        $section=['nativeOrganisationId'=>'1','sectionId'=>'33333333-3333-4333-8333-333333333333'];
        $request=Request::create('https://tenant-1.example.invalid/enrolment');$service=new \App\Services\EnrolledStudentGroup;
        $this->denied(404,fn()=>$service->map($request,$section,'3',0));
        $first=$service->map($request,$section,'1',0);$this->assertSame(1,$first['version']);
        $options=$service->options($request,$section);$this->assertCount(2,$options['groups']);
        $this->assertSame('1',$options['mapping']['nativeGroupId']);
        $this->assertNotContains('3',array_column($options['groups'],'id'));
        $this->assertSame($first,$service->map($request,$section,'1',1));
        $this->denied(409,fn()=>$service->map($request,$section,'2',0));
        $this->assertSame(2,$service->map($request,$section,'2',1)['version']);
        DB::table('foundation_section_groups')->update(['active'=>false]);
        $this->denied(409,fn()=>$service->map($request,$section,'1',2));
        DB::table('organization_users')->update(['role'=>'staff']);
        $this->denied(403,fn()=>$service->map($request,$section,'1',2));
        $this->assertSame(0,DB::table('student_groups')->count());
    }
    public function test_mapping_bridge_requires_current_canonical_section_scope_and_edit_authority(): void
    {
        config(['attendance.api_url'=>'https://canonical.example.invalid/api/v1','attendance.student_delivery_enabled'=>true]);
        DB::table('groups')->insert(['id'=>1,'organization_id'=>1]);
        $section='33333333-3333-4333-8333-333333333333';$org='22222222-2222-4222-8222-222222222222';
        $context=['nativeOrganisationId'=>'1','nativeUserId'=>'1','organisation'=>['id'=>$org],
            'permissions'=>['groups.view','groups.create'],'scope'=>['type'=>'groups','ids'=>[$section]]];
        $source=['id'=>$section,'organisation_id'=>$org,'archived'=>false,'name'=>'Synthetic section'];
        $request=Request::create('https://tenant-1.example.invalid/enrolment','POST',[],['t4l_session'=>str_repeat('a',64)]);
        $request->setUserResolver(fn()=>Auth::user());app()->instance('request',$request);
        $response=fn($v,$status=200)=>new \GuzzleHttp\Psr7\Response($status,['Content-Type'=>'application/json'],json_encode($v));
        $client=fn($queue)=>new \GuzzleHttp\Client(['handler'=>\GuzzleHttp\HandlerStack::create(new \GuzzleHttp\Handler\MockHandler($queue))]);
        $bridge=new \App\Support\AttendanceBridge;
        $this->denied(404,fn()=>$bridge->mapSection($request,$section,'1',0,$client([$response($context),$response([])])));
        $this->denied(403,fn()=>$bridge->mapSection($request,$section,'1',0,$client([$response($context),$response([$source]),$response([],403)])));
        $readonly=$context;$readonly['permissions']=['groups.view'];
        $this->denied(403,fn()=>$bridge->mapSection($request,$section,'1',0,$client([$response($readonly)])));
        $this->assertSame(0,DB::table('foundation_section_groups')->count());
        $this->assertSame(1,$bridge->mapSection($request,$section,'1',0,$client([$response($context),$response([$source]),$response($context),$response([$source])]))['version']);
        $this->assertSame(0,DB::table('student_groups')->count());
    }
}
