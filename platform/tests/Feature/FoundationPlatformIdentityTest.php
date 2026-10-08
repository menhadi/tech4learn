<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\{AttendanceBridge,Tenant};
use App\Http\Middleware\{VerifyPlatformIdentity,ResolveTenant};
use GuzzleHttp\{Client,HandlerStack,Middleware};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,DB,Schema,Cache};
use Tests\TestCase;

class FoundationPlatformIdentityTest extends TestCase
{
    private array $identity=['nativeOrganisationId'=>'1','nativeUserId'=>'1',
        'userId'=>'11111111-1111-4111-8111-111111111111','version'=>1,'realm'=>'platform'];
    private array $history=[];

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'platform_identity_test','database.connections.platform_identity_test'=>[
            'driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true,
        ],'attendance.api_url'=>'https://api.example.invalid/api/v1','session.driver'=>'array','cache.default'=>'array']);
        Schema::create('organizations',function(Blueprint $table){
            $table->id();foreach(['name','slug','domain','status'] as $field)$table->string($field);
            $table->string('subdomain')->nullable();$table->json('settings')->nullable();
        });
        Schema::create('users',function(Blueprint $table){
            $table->id();$table->string('name');$table->string('status');
            $table->boolean('deleted')->default(false);$table->boolean('is_platform_admin')->default(false);
        });
        DB::table('organizations')->insert(['id'=>1,'name'=>'Synthetic platform','slug'=>'synthetic',
            'domain'=>'platform.example.invalid','status'=>'active','settings'=>'{"is_primary_platform":true}']);
        DB::table('users')->insert([
            ['id'=>1,'name'=>'Synthetic platform administrator','status'=>'Active','is_platform_admin'=>true],
            ['id'=>2,'name'=>'Synthetic ordinary staff','status'=>'Active','is_platform_admin'=>false],
        ]);
        Cache::flush();Tenant::clear();Auth::forgetGuards();
    }

    protected function tearDown(): void
    {
        Tenant::clear();DB::purge('platform_identity_test');
        parent::tearDown();
    }

    private function request(string $path='/exams',string $method='GET'): Request
    {
        $request=Request::create('https://platform.example.invalid'.$path,$method,
            $method==='POST'?['login'=>'synthetic@example.invalid','password'=>'synthetic unused password']:[],
            ['t4l_session'=>str_repeat('a',64)]);
        $request->setLaravelSession(app('session')->driver());
        $request->setUserResolver(fn()=>Auth::guard('web')->user());
        app()->instance('request',$request);
        return $request;
    }

    private function response(array $body,int $status=200,bool $cookie=false): Response
    {
        $headers=['Content-Type'=>'application/json'];
        if($cookie)$headers['Set-Cookie']='t4l_session='.str_repeat('b',64).'; HttpOnly; Path=/';
        return new Response($status,$headers,json_encode($body));
    }

    private function client(array $responses): Client
    {
        $stack=HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));
        return new Client(['handler'=>$stack]);
    }

    private function denied(int $status,callable $action): void
    {
        try{$action();$this->fail('Expected identity denial.');}
        catch(\Symfony\Component\HttpKernel\Exception\HttpException $error){$this->assertSame($status,$error->getStatusCode());}
    }

    public function test_primary_login_uses_platform_mapping_and_session_metadata(): void
    {
        $request=$this->request('/login','POST');
        $client=$this->client([$this->response($this->identity,200,true)]);
        $user=(new AttendanceBridge)->authenticate($request,$client);
        $this->assertSame(1,$user->id);
        $this->assertSame($this->identity,$request->attributes->get('foundation_platform_identity'));
        $this->assertSame('/api/v1/foundation/auth/platform/login',$this->history[0]['request']->getUri()->getPath());
    }

    public function test_native_login_controller_preserves_only_private_identity_metadata(): void
    {
        $client=$this->client([$this->response($this->identity,200,true)]);
        app()->instance(AttendanceBridge::class,new class($client) extends AttendanceBridge {
            public function __construct(private Client $client){}
            public function authenticate(Request $request,?\GuzzleHttp\ClientInterface $http=null): User
            {return parent::authenticate($request,$this->client);}
        });
        $request=$this->request('/login','POST');
        $controller=app(\App\Http\Controllers\Auth\LoginController::class);
        $method=new \ReflectionMethod($controller,'attemptLogin');
        $this->assertTrue($method->invoke($controller,$request));
        $this->assertSame($this->identity,$request->session()->get('foundation_platform_identity'));
        $this->assertSame(1,Auth::guard('web')->id());
    }

    public function test_platform_login_cannot_promote_an_ordinary_native_account(): void
    {
        $identity=$this->identity;$identity['nativeUserId']='2';
        $client=$this->client([$this->response($identity,200,true),$this->response(['ok'=>true])]);
        $this->denied(403,fn()=>(new AttendanceBridge)->authenticate($this->request('/login','POST'),$client));
        $this->assertSame('/api/v1/auth/logout',$this->history[1]['request']->getUri()->getPath());
        $this->assertFalse((bool)DB::table('users')->where('id',2)->value('is_platform_admin'));
    }

    public function test_malformed_platform_metadata_revokes_its_provisional_api_session(): void
    {
        $identity=$this->identity;$identity['realm']='organisation';
        $client=$this->client([$this->response($identity,200,true),$this->response(['ok'=>true])]);
        $this->denied(502,fn()=>(new AttendanceBridge)->authenticate($this->request('/login','POST'),$client));
        $this->assertSame('/api/v1/auth/logout',$this->history[1]['request']->getUri()->getPath());
    }

    private function bindClient(Client $client): void
    {
        app()->instance(AttendanceBridge::class,new class($client) extends AttendanceBridge {
            public function __construct(private Client $client){}
            public function platformIdentity(Request $request,User $actor,?\GuzzleHttp\ClientInterface $http=null): array
            {return parent::platformIdentity($request,$actor,$this->client);}
        });
    }

    public function test_privileged_response_checks_remote_identity_before_and_after_controller(): void
    {
        Auth::guard('web')->setUser(User::findOrFail(1));$request=$this->request();
        $request->session()->put('foundation_platform_identity',$this->identity);
        $this->bindClient($this->client([$this->response($this->identity),$this->response($this->identity)]));
        $response=(new VerifyPlatformIdentity)->handle($request,fn()=>response('synthetic private response'));
        $this->assertSame('synthetic private response',$response->getContent());
        $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
        $this->assertCount(2,$this->history);
    }

    public function test_verified_platform_actor_can_use_native_page_rights_without_a_legacy_role(): void
    {
        $actor=User::findOrFail(1);Auth::guard('web')->setUser($actor);$request=$this->request();
        $request->session()->put('foundation_platform_identity',$this->identity);
        $this->bindClient($this->client([$this->response($this->identity),$this->response($this->identity)]));
        $this->assertFalse(\App\Support\VerifiedPlatformAccess::allowed($request,$actor));
        $response=(new VerifyPlatformIdentity)->handle($request,function($request)use($actor){
            $this->assertTrue(\App\Support\VerifiedPlatformAccess::allowed($request,$actor));
            return (new \App\Http\Middleware\CheckPageRights)->handle($request,fn()=>response('verified exam administration'));
        });
        $this->assertSame('verified exam administration',$response->getContent());
        $this->assertFalse(\App\Support\VerifiedPlatformAccess::allowed($request,$actor));
    }

    public function test_request_permission_marker_is_cleared_after_failure_and_cannot_promote_staff(): void
    {
        $actor=User::findOrFail(1);Auth::guard('web')->setUser($actor);$request=$this->request();
        $request->session()->put('foundation_platform_identity',$this->identity);
        $this->bindClient($this->client([$this->response($this->identity)]));
        try {
            (new VerifyPlatformIdentity)->handle($request,function(){throw new \RuntimeException('synthetic controller failure');});
            $this->fail('Expected controller failure');
        }catch(\RuntimeException $error){$this->assertSame('synthetic controller failure',$error->getMessage());}
        $this->assertFalse($request->attributes->has('foundation_verified_platform_actor'));
        $request->attributes->set('foundation_verified_platform_actor','2');
        $this->assertFalse(\App\Support\VerifiedPlatformAccess::allowed($request,User::findOrFail(2)));
    }

    public function test_revocation_during_controller_withholds_its_private_response(): void
    {
        Auth::guard('web')->setUser(User::findOrFail(1));$request=$this->request();
        $request->session()->put('foundation_platform_identity',$this->identity);
        $this->bindClient($this->client([$this->response($this->identity),$this->response([],403)]));
        $this->denied(403,fn()=>(new VerifyPlatformIdentity)->handle($request,fn()=>response('must not be released')));
    }

    public function test_native_demotion_or_missing_marker_rejects_cached_admin_sessions(): void
    {
        Auth::guard('web')->setUser(User::findOrFail(1));$request=$this->request();
        $request->session()->forget('foundation_platform_identity');
        $this->denied(403,fn()=>(new VerifyPlatformIdentity)->handle($request,fn()=>response('private')));
        $request->session()->put('foundation_platform_identity',$this->identity);
        DB::table('users')->where('id',1)->update(['is_platform_admin'=>false]);
        $this->denied(403,fn()=>(new VerifyPlatformIdentity)->handle($request,fn()=>response('private')));
    }

    public function test_link_version_change_rejects_a_stale_native_session(): void
    {
        Auth::guard('web')->setUser(User::findOrFail(1));$request=$this->request();
        $request->session()->put('foundation_platform_identity',$this->identity);
        $changed=$this->identity;$changed['version']=2;
        $this->bindClient($this->client([$this->response($changed)]));
        $this->denied(403,fn()=>(new VerifyPlatformIdentity)->handle($request,fn()=>response('private')));
    }

    public function test_native_demotion_during_remote_read_rejects_cached_actor(): void
    {
        $actor=User::findOrFail(1);$request=$this->request();
        $client=$this->client([function(){
            DB::table('users')->where('id',1)->update(['is_platform_admin'=>false]);
            return $this->response($this->identity);
        }]);
        $this->denied(403,fn()=>(new AttendanceBridge)->platformIdentity($request,$actor,$client));
    }

    public function test_administrator_provisioning_uses_stored_membership_and_identity(): void
    {
        Schema::table('users',function(Blueprint $table){$table->string('email')->nullable();});
        Schema::create('organization_users',function(Blueprint $table){
            $table->integer('organization_id');$table->integer('user_id');$table->string('role');$table->integer('status');
        });
        DB::table('organizations')->insert(['id'=>2,'name'=>'Synthetic tenant','slug'=>'tenant',
            'domain'=>'tenant.example.invalid','status'=>'active','settings'=>'{}']);
        DB::table('users')->where('id',2)->update(['email'=>'synthetic@example.invalid']);
        DB::table('organization_users')->insert(['organization_id'=>2,'user_id'=>2,'role'=>'staff','status'=>1]);
        $actor=User::findOrFail(1);$user=User::findOrFail(2);$user->name='Untrusted changed name';
        $organization=\App\Models\Organization::findOrFail(2);$request=$this->request('/provision','POST');
        $bridge=new AttendanceBridge;
        $this->denied(403,fn()=>$bridge->provisionAdministrator($request,$actor,$organization,$user,'Synthetic password 42',
            $this->client([$this->response($this->identity)])));
        DB::table('organization_users')->where('user_id',2)->update(['role'=>'admin']);
        $result=['created'=>true,'userId'=>'22222222-2222-4222-8222-222222222222'];
        $client=$this->client([$this->response($this->identity),$this->response($result),$this->response($this->identity)]);
        $this->assertSame($result,$bridge->provisionAdministrator($request,$actor,$organization,$user,'Synthetic password 42',$client));
        $body=json_decode((string)$this->history[2]['request']->getBody(),true);
        $this->assertSame('Synthetic ordinary staff',$body['name']);
        $this->assertSame('admin',$body['nativeRole']);
        $this->assertSame('2',$body['nativeOrganisationId']);
        $this->assertSame('2',$body['nativeUserId']);
        $client=$this->client([$this->response($this->identity),function() use ($result){
            DB::table('organization_users')->where('user_id',2)->update(['status'=>0]);
            return $this->response($result);
        },$this->response($this->identity)]);
        $this->denied(403,fn()=>$bridge->provisionAdministrator($request,$actor,$organization,$user,'Synthetic password 42',$client));
    }

    public function test_verified_platform_identity_enables_rendered_action_controls(): void
    {
        $actor=User::findOrFail(1);Auth::guard('web')->setUser($actor);$request=$this->request();
        $this->assertFalse(user_can_route_action('exams.edit','edit'));
        $request->attributes->set('foundation_verified_platform_actor','1');
        $this->assertTrue(user_can_route_action('exams.edit','edit'));
        DB::table('users')->where('id',1)->update(['is_platform_admin'=>false]);
        $this->assertFalse(user_can_route_action('exams.edit','edit'));
    }

    public function test_verified_primary_administrator_sees_attendance_setup(): void
    {
        $actor=User::findOrFail(1);Auth::guard('web')->setUser($actor);$request=$this->request();
        $request->attributes->set('foundation_verified_platform_actor','1');
        $view=(new \App\Http\Controllers\AttendanceBridgeController)->workspace();
        $this->assertSame('attendance.platform-setup',$view->name());
    }

    public function test_revoked_native_administrator_can_still_log_out(): void
    {
        Auth::guard('web')->setUser(User::findOrFail(1));$request=$this->request('/logout','POST');
        DB::table('users')->where('id',1)->update(['is_platform_admin'=>false]);
        $response=(new ResolveTenant)->handle($request,fn($request)=>(new VerifyPlatformIdentity)->handle($request,fn()=>response('',204)));
        $this->assertSame(204,$response->getStatusCode());
    }

    public function test_enrolment_gateway_does_not_expose_attendance_resources(): void
    {
        $controller=new \App\Http\Controllers\AttendanceBridgeController;
        foreach (['organisations/native-org-2/attendance',
            'organisations/native-org-2/attendance/captures',
            'organisations/native-org-2/attendance/history'] as $path) {
            $this->denied(404,fn()=>$controller->enrolmentGateway(
                $this->request('/enrolment/api/'.$path),$path,new AttendanceBridge));
        }
    }
    public function test_registration_document_gateway_rejects_other_methods_and_paths_before_remote_access(): void
    {
        $org='22222222-2222-4222-8222-222222222222';
        $bridge=new AttendanceBridge;
        foreach ([['GET','draft'],['POST','providers'],['PATCH','draft']] as [$method,$resource]) {
            $path='organisations/'.$org.'/registration-documents/'.$resource;
            $this->denied(405,fn()=>$bridge->gateway($this->request('/enrolment/api/'.$path,$method),$path,null,'enrolment'));
        }
        $path='organisations/'.$org.'/registration-documents/other';
        $this->denied(404,fn()=>$bridge->gateway($this->request('/enrolment/api/'.$path),$path,null,'enrolment'));
    }
    public function test_student_delivery_endpoint_rejects_browser_identity_fields_and_is_post_authenticated(): void
    {
        $controller=new \App\Http\Controllers\AttendanceBridgeController;
        $learner='22222222-2222-4222-8222-222222222222';
        $request=Request::create('https://platform.example.invalid/enrolment/students/'.$learner.'/deliver','POST',
            ['nativeStudentId'=>'99','keyId'=>'untrusted']);
        $this->denied(422,fn()=>$controller->deliverStudent($request,$learner,new AttendanceBridge));
        config(['attendance.student_delivery_enabled'=>false]);
        $request=Request::create('https://platform.example.invalid/enrolment/students/'.$learner.'/deliver','POST');
        $this->denied(503,fn()=>$controller->deliverStudent($request,$learner,new AttendanceBridge));
        $route=app('router')->getRoutes()->getByName('enrolment.student-delivery');
        $this->assertNotNull($route);$this->assertSame(['POST'],$route->methods());
        $this->assertContains('auth',$route->gatherMiddleware());$this->assertContains('web',$route->gatherMiddleware());
    }
    public function test_student_snapshot_rechecks_enrolment_authority_and_exact_delivery_revision(): void
    {
        Auth::guard('web')->setUser(User::findOrFail(1));$request=$this->request('/enrolment');
        $learner='22222222-2222-4222-8222-222222222222';
        $context=['nativeOrganisationId'=>'1','nativeUserId'=>'1','organisation'=>['id'=>$learner],
            'permissions'=>['learners.view'],'scope'=>['type'=>'organisation','ids'=>[]]];
        $snapshot=['nativeOrganisationId'=>'1','nativeUserId'=>'1','organisationId'=>$learner,'learnerId'=>$learner,
            'revision'=>1,'version'=>1,'name'=>'Synthetic Student','code'=>'TEST-001','archived'=>false,'demo'=>true,
            'groupId'=>'33333333-3333-4333-8333-333333333333','password'=>'untrusted extra'];
        $client=$this->client([$this->response($context),$this->response($snapshot),$this->response($context),$this->response($snapshot)]);
        $result=(new AttendanceBridge)->studentDeliverySnapshot($request,$learner,$client);
        $this->assertArrayNotHasKey('password',$result);$this->assertSame(1,$result['revision']);
        $this->assertStringEndsWith('/enrolment-context',(string)$this->history[0]['request']->getUri());
        $before=count($this->history);$client=$this->client([$this->response($context)]);
        $this->denied(403,fn()=>(new AttendanceBridge)->studentDeliverySnapshot($request,$learner,$client,true));
        $this->assertCount($before+1,$this->history);
        $deliveryContext=$context;$deliveryContext['permissions'][]='learners.edit';
        $manual=$snapshot;$manual['reviewRequired']=true;
        $client=$this->client([$this->response($deliveryContext),$this->response($manual)]);
        $before=count($this->history);
        $this->denied(409,fn()=>(new AttendanceBridge)->studentDeliverySnapshot($request,$learner,$client,true));
        $this->assertCount($before+2,$this->history);
        config(['attendance.student_delivery_enabled'=>false]);
        $this->denied(503,fn()=>(new AttendanceBridge)->deliverStudent($request,$learner,$client));
        $changed=$snapshot;$changed['revision']=2;
        $client=$this->client([$this->response($context),$this->response($snapshot),$this->response($context),$this->response($changed)]);
        $this->denied(409,fn()=>(new AttendanceBridge)->studentDeliverySnapshot($request,$learner,$client));
        $client=$this->client([$this->response($context),$this->response($snapshot),$this->response([],403)]);
        $this->denied(403,fn()=>(new AttendanceBridge)->studentDeliverySnapshot($request,$learner,$client));
    }
    public function test_delivery_status_is_scoped_rechecked_and_strips_remote_identity_extras(): void
    {
        Auth::guard('web')->setUser(User::findOrFail(1));$request=$this->request('/enrolment');
        $learner='22222222-2222-4222-8222-222222222222';
        $context=['nativeOrganisationId'=>'1','nativeUserId'=>'1','organisation'=>['id'=>$learner],
            'permissions'=>['learners.view'],'scope'=>['type'=>'organisation','ids'=>[]]];
        $status=['learnerId'=>$learner,'revision'=>1,'state'=>'pending','nativeStudentId'=>'untrusted'];
        config(['attendance.student_delivery_enabled'=>true]);
        $client=$this->client([$this->response($context),$this->response($status),$this->response($context)]);
        $this->assertSame(['learnerId'=>$learner,'revision'=>1,'state'=>'pending'],(new AttendanceBridge)->studentDeliveryStatus($request,$learner,$client));
        $client=$this->client([$this->response($context),$this->response($status),$this->response([],403)]);
        $this->denied(403,fn()=>(new AttendanceBridge)->studentDeliveryStatus($request,$learner,$client));
        $foreign=$status;$foreign['learnerId']='33333333-3333-4333-8333-333333333333';
        $client=$this->client([$this->response($context),$this->response($foreign)]);
        $this->denied(502,fn()=>(new AttendanceBridge)->studentDeliveryStatus($request,$learner,$client));
        config(['attendance.student_delivery_enabled'=>false]);
        $this->denied(503,fn()=>(new AttendanceBridge)->studentDeliveryStatus($request,$learner,$client));
    }
    public function test_section_mapping_endpoint_rejects_authority_fields_and_uses_authenticated_post(): void
    {
        $section='33333333-3333-4333-8333-333333333333';$controller=new \App\Http\Controllers\AttendanceBridgeController;
        $request=Request::create('/enrolment/sections/'.$section.'/exam-group','POST',
            ['nativeGroupId'=>'1','version'=>0,'organisationId'=>'foreign']);
        $this->denied(422,fn()=>$controller->mapSection($request,$section,new AttendanceBridge));
        config(['attendance.student_delivery_enabled'=>false]);
        $request=Request::create('/enrolment/sections/'.$section.'/exam-group','POST',['nativeGroupId'=>'1','version'=>0]);
        $this->denied(503,fn()=>$controller->mapSection($request,$section,new AttendanceBridge));
        $route=app('router')->getRoutes()->getByName('enrolment.section-mapping');
        $this->assertSame(['POST'],$route->methods());$this->assertContains('auth',$route->gatherMiddleware());
        $this->assertContains('web',$route->gatherMiddleware());
    }
}
