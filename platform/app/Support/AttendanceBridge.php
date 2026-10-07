<?php

namespace App\Support;

use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

class AttendanceBridge
{
    public function learnerIdentity(Request $request, \App\Models\Student $student, ?ClientInterface $http = null): array
    {
        $organisation=Tenant::assertAccess(Tenant::current(),true);
        $actor=$request->user();
        abort_unless($actor instanceof \App\Models\User,403);
        abort_unless((int)$student->organization_id===(int)$organisation->id && $student->status==='Active',404);
        $path='/foundation/organisations/'.$organisation->id.'/staff/'.$actor->id.'/students/'.$student->id.'/learner-identity';
        $identity=$this->read($request,$path,[],$http);
        abort_unless(($identity['nativeOrganisationId']??null)===(string)$organisation->id
            && ($identity['nativeUserId']??null)===(string)$actor->id
            && ($identity['nativeStudentId']??null)===(string)$student->id
            && is_int($identity['version']??null) && $identity['version']>0,502);
        foreach (['organisationId','learnerId'] as $key) {
            abort_unless(is_string($identity[$key]??null)
                && preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',$identity[$key]),502);
        }
        abort_unless($this->read($request,$path,[],$http)===$identity,403);
        Tenant::assertAccess($organisation,true);
        abort_unless(\App\Models\Student::whereKey($student->id)->where('organization_id',$organisation->id)->where('status','Active')->exists(),404);
        return array_intersect_key($identity,array_flip(['nativeOrganisationId','nativeUserId','nativeStudentId','organisationId','learnerId','version']));
    }
    public function authenticate(Request $request, ?ClientInterface $http = null): User
    {
        $organization=Tenant::current();
        $platform=(bool)($organization->settings['is_primary_platform']??false);
        $headers=[];
        $identity=$this->read($request,$platform?'/foundation/auth/platform/login':'/foundation/auth/login',[],$http,'POST',[
            'email'=>$request->input('login'),'password'=>$request->input('password'),
            'nativeOrganisationId'=>(string)$organization->id,
        ],false,$headers);
        $name=$this->cookieName();$token=null;
        foreach ($headers['set-cookie']??[] as $cookie) {
            if (preg_match('/^'.preg_quote($name,'/').'=([a-f0-9]{64});/D',$cookie,$match)) { $token=$match[1]; }
        }
        abort_unless($token,502,'Sign-in did not establish an attendance session.');
        $guard=Auth::guard('web');$previous=$guard->user();
        try {
            abort_unless(($identity['nativeOrganisationId']??null)===(string)$organization->id
                && is_string($identity['nativeUserId']??null) && preg_match('/^[1-9][0-9]{0,14}$/D',$identity['nativeUserId']),502);
            if ($platform) { $this->validatePlatformIdentity($identity,$organization->id,$identity['nativeUserId']); }
            $user=User::findOrFail($identity['nativeUserId']);
            $stored=\Illuminate\Support\Facades\DB::table('users')->where('id',$user->id)->first();
            abort_unless($stored && (bool)$stored->is_platform_admin===$platform,403);
            $guard->setUser($user);
            Tenant::assertAccess($organization,true);
            if ($platform) {
                $request->attributes->set('foundation_platform_identity',$this->platformIdentityValues($identity));
            }
        } catch (\Throwable $error) {
            $old=$request->cookie($name);$request->cookies->set($name,$token);
            try { $this->signOut($request,$http); } finally {
                if ($old===null) { $request->cookies->remove($name); } else { $request->cookies->set($name,$old); }
            }
            throw $error;
        } finally {
            if ($previous) { $guard->setUser($previous); } else { $guard->forgetUser(); }
            Tenant::clear();
        }
        Cookie::queue(new \Symfony\Component\HttpFoundation\Cookie($name,$token,time()+43200,'/',null,app()->environment('production'),true,false,'lax'));
        return $user;
    }

    private function validatePlatformIdentity(array $identity, int|string $organisation, int|string $user): void
    {
        abort_unless(($identity['realm']??null)==='platform'
            && ($identity['nativeOrganisationId']??null)===(string)$organisation
            && ($identity['nativeUserId']??null)===(string)$user
            && is_int($identity['version']??null) && $identity['version']>0
            && is_string($identity['userId']??null)
            && preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',$identity['userId']),502);
    }

    private function platformIdentityValues(array $identity): array
    {
        return ['nativeOrganisationId'=>$identity['nativeOrganisationId'],'nativeUserId'=>$identity['nativeUserId'],
            'userId'=>$identity['userId'],'version'=>$identity['version'],'realm'=>'platform'];
    }

    public function platformIdentity(Request $request, User $actor, ?ClientInterface $http = null): array
    {
        // Resolve the primary realm from stored native configuration, not a client ID.
        $primary=\App\Models\Organization::where('status','active')->where('settings->is_primary_platform',true)->sole();
        $this->assertPlatformActor($actor);
        $identity=$this->read($request,'/foundation/platforms/'.$primary->id.'/staff/'.$actor->id.'/identity',[],$http);
        $this->validatePlatformIdentity($identity,$primary->id,$actor->id);
        $this->assertPlatformActor($actor);
        abort_unless(\App\Models\Organization::whereKey($primary->id)->where('status','active')->where('settings->is_primary_platform',true)->exists(),403);
        return $this->platformIdentityValues($identity);
    }

    private function assertPlatformActor(User $actor): void
    {
        abort_unless(\Illuminate\Support\Facades\DB::table('users')->where('id',$actor->id)
            ->where('status','Active')->where('deleted',false)->where('is_platform_admin',true)->exists(),403);
    }

    public function signOut(Request $request, ?ClientInterface $http = null): void
    {
        try {
            if (config('attendance.api_url')) { $this->read($request,'/auth/logout',[],$http,'POST',[]); }
        } catch (\Throwable $error) {
            // Always clear the browser cookie even when the service is unavailable.
        }
        Cookie::queue(new \Symfony\Component\HttpFoundation\Cookie($this->cookieName(),'',time()-300,'/',null,app()->environment('production'),true,false,'lax'));
    }

    private function cookieName(): string
    {
        return app()->environment('production') ? '__Host-t4l_session' : 't4l_session';
    }
    public function context(Request $request, ?ClientInterface $http = null): array
    {
        $organization = Tenant::assertAccess(Tenant::current(), true);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $context = $this->read($request, '/foundation/organisations/'.$organization->id.
            '/staff/'.$actor->id.'/attendance-context', [], $http);
        abort_unless(($context['nativeOrganisationId'] ?? null) === (string)$organization->id
            && ($context['nativeUserId'] ?? null) === (string)$actor->id
            && is_string($context['organisation']['id'] ?? null)
            && preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $context['organisation']['id'])
            && is_array($context['permissions'] ?? null) && is_array($context['scope'] ?? null), 502);
        return $context;
    }

    public function records(Request $request, ?ClientInterface $http = null): array
    {
        $date = $request->query('date');
        $parsed = is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)
            ? \DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
        abort_unless($parsed && $parsed->format('Y-m-d') === $date, 422);
        $context = $this->context($request, $http);
        $id = $context['organisation']['id'] ?? '';
        abort_unless(is_string($id) && preg_match('/^[a-f0-9-]{36}$/D', $id), 502);
        $data = $this->read($request, '/organisations/'.$id.'/attendance', ['date' => $date], $http);
        // Recheck both identity maps and permissions after remote work before releasing records.
        abort_unless($this->context($request, $http) === $context, 403);
        return $data;
    }

    public function gateway(Request $request, string $path, ?ClientInterface $http = null): \Symfony\Component\HttpFoundation\Response
    {
        abort_unless(strlen($path)<300 && preg_match('#^organisations/([a-f0-9-]{36})/(centres|groups(?:/[a-f0-9-]{36}(?:/archive)?)?|academic-years(?:/[a-f0-9-]{36}/archive)?|classes(?:/[a-f0-9-]{36}/archive)?|attendance(?:/[A-Za-z0-9-]+)*)$#D',$path,$match),404);
        abort_unless(in_array($request->method(),['GET','POST','PATCH'],true),405);
        $resource=$match[2];
        if ($resource==='centres') abort_unless($request->method()==='GET',405);
        elseif (!str_starts_with($resource,'attendance')) {
            $methods=str_ends_with($resource,'/archive') ? ['POST']
                : (str_starts_with($resource,'groups/') ? ['PATCH'] : ['GET','POST']);
            abort_unless(in_array($request->method(),$methods,true),405);
        }
        $context=$this->context($request,$http);
        abort_unless($match[1]===($context['organisation']['id']??null),404);
        $body=null;
        if ($request->method()!=='GET') {
            abort_unless($request->isJson() && strlen($request->getContent())<=1048576,422);
            try { $body=json_decode($request->getContent(),false,64,JSON_THROW_ON_ERROR); }
            catch (\JsonException $error) { abort(400,'Use a valid JSON request.'); }
            abort_unless($body instanceof \stdClass,422);
        }
        $result=$this->read($request,'/'.$path,$request->query(),$http,$request->method(),$body,true,$headers,true);
        abort_unless($this->context($request,$http)===$context,403);
        return new \Symfony\Component\HttpFoundation\Response($result['body'],$result['status'],[
            'Content-Type'=>$result['content_type'],'Cache-Control'=>'no-store',
            'X-Content-Type-Options'=>'nosniff',
        ]);
    }

    private function read(Request $request, string $path, array $query, ?ClientInterface $http,
        string $method='GET', array|\stdClass|null $payload=null, bool $needsSession=true, ?array &$responseHeaders=null, bool $opaque=false): array
    {
        $base = rtrim((string) config('attendance.api_url'), '/');
        $parts = parse_url($base);
        $scheme = $parts['scheme'] ?? '';
        $local = in_array($parts['host'] ?? '', ['127.0.0.1', 'localhost', '::1'], true);
        abort_unless($parts && ($scheme === 'https' || ($scheme === 'http' && $local && app()->environment('local')))
            && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['query']) && !isset($parts['fragment'])
            && ($parts['path'] ?? '') === '/api/v1', 503, 'Attendance connection is not configured.');
        $name = $this->cookieName();
        $token = $request->cookie($name);
        if ($needsSession) { abort_unless(is_string($token) && preg_match('/^[a-f0-9]{64}$/D', $token), 401, 'Sign in to your linked attendance account.'); }
        $requestHeaders=['Accept'=>'application/json'];
        if ($needsSession) { $requestHeaders['Cookie']=$name.'='.$token; }
        if (in_array($method,['POST','PATCH'],true)) {
            $requestHeaders['Origin']=$request->getSchemeAndHttpHost();
            $requestHeaders['Host']=$request->getHttpHost();
            $requestHeaders['X-Tech4Learn-Request']='1';
        }
        try {
            $options=[
                'headers' => $requestHeaders,
                'query' => $query, 'allow_redirects' => false, 'http_errors' => false,
                'connect_timeout' => 3,
                'timeout' => $method === 'POST' && preg_match('#^/organisations/[a-f0-9-]{36}/attendance/[a-f0-9-]{36}/analyse$#D', $path) ? 45 : 8,
                'stream' => true,
            ];
            if ($payload!==null) { $options['json']=$payload?:new \stdClass; }
            $response = ($http ?? new Client())->request($method, $base.$path, $options);
            $responseHeaders=['set-cookie'=>$response->getHeader('Set-Cookie')];
            $status = $response->getStatusCode();
            if (!in_array($status,[200,201],true) && !($opaque && in_array($status,[400,401,403,404,409,422,429],true))) {
                $response->getBody()->close();
                abort(in_array($status, [401,403,404], true) ? $status : 502, 'Attendance access is unavailable.');
            }
            $stream = $response->getBody();
            $body = '';
            try {
                while (!$stream->eof() && strlen($body) <= 1048576) {
                    $chunk = $stream->read(8192);
                    if ($chunk === '' && !$stream->eof()) { throw new \RuntimeException('Incomplete response'); }
                    $body .= $chunk;
                }
            } finally { $stream->close(); }
            abort_if(strlen($body) > 1048576, 502);
            if ($opaque) {
                $mime=strtolower(explode(';',$response->getHeaderLine('Content-Type'))[0]);
                abort_unless(in_array($mime,['application/json','image/jpeg'],true),502);
                if ($mime==='application/json') { json_decode($body,true,32,JSON_THROW_ON_ERROR); }
                abort_unless($status<400 || $mime==='application/json',502);
                return ['body'=>$body,'status'=>$status,'content_type'=>$mime];
            }
            $data = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
            abort_unless(is_array($data), 502);
            return $data;
        } catch (\GuzzleHttp\Exception\GuzzleException | \JsonException | \RuntimeException $error) {
            if ($error instanceof \Symfony\Component\HttpKernel\Exception\HttpException) { throw $error; }
            // Never expose remote response text, token or connection URL in an error.
            abort(502, 'Attendance connection could not complete the request.');
        }
    }
}
