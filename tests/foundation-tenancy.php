<?php
// Isolated native guard fixture. Uses an in-memory database and synthetic actors.
if (PHP_SAPI !== 'cli' || $argc < 2) { exit(1); }
$base = realpath($argv[1]);
$local = realpath(__DIR__.'/../.local');
if (!$base || !$local || !str_starts_with(str_replace('\\', '/', $base).'/', str_replace('\\', '/', $local).'/')) {
    throw new RuntimeException('Only the local foundation dependency loader is allowed.');
}
$loader = require $base.'/vendor/autoload.php';
$loader->addClassMap([
    'App\\Support\\Tenant' => realpath(__DIR__.'/../platform/app/Support/Tenant.php'),
    'App\\Http\\Middleware\\ResolveTenant' => realpath(__DIR__.'/../platform/app/Http/Middleware/ResolveTenant.php'),
]);
$app = require $base.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error): void {
    fwrite(STDERR, get_class($error).': '.$error->getMessage()."\n");
    exit(1);
});
if (!$app->environment('local')) { throw new RuntimeException('Local environment required'); }
config(['database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array', 'session.driver' => 'array']);
Illuminate\Support\Facades\DB::purge('sqlite');
$db = Illuminate\Support\Facades\DB::connection('sqlite');
$db->statement('CREATE TABLE organizations (id INTEGER PRIMARY KEY, name TEXT, slug TEXT, domain TEXT, subdomain TEXT, status TEXT)');
$db->statement('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, status TEXT, deleted INTEGER, is_platform_admin INTEGER)');
$db->statement('CREATE TABLE students (id INTEGER PRIMARY KEY, name TEXT, organization_id INTEGER, status TEXT)');
$db->statement('CREATE TABLE organization_users (id INTEGER PRIMARY KEY, organization_id INTEGER, user_id INTEGER, role TEXT, status INTEGER)');
$db->table('organizations')->insert([
    ['id'=>1,'name'=>'Synthetic One','slug'=>'one','domain'=>'one.example.invalid','status'=>'active'],
    ['id'=>2,'name'=>'Synthetic Two','slug'=>'two','domain'=>'two.example.invalid','status'=>'active'],
]);
$db->table('users')->insert([
    ['id'=>1,'name'=>'Synthetic Platform','status'=>'Active','deleted'=>0,'is_platform_admin'=>1],
    ['id'=>2,'name'=>'Synthetic Tenant Admin','status'=>'Active','deleted'=>0,'is_platform_admin'=>0],
]);
$db->table('organization_users')->insert(['id'=>1,'organization_id'=>2,'user_id'=>2,'role'=>'admin','status'=>1]);
$db->table('students')->insert(['id'=>10,'name'=>'Synthetic Student','organization_id'=>2,'status'=>'Active']);
function actor(int $id): void {
    Illuminate\Support\Facades\Auth::forgetGuards();
    Illuminate\Support\Facades\Auth::shouldUse('web');
    $user = App\Models\User::findOrFail($id);
    $user->setRelation('roles', collect([new Spatie\Permission\Models\Role(['name'=>'admin','guard_name'=>'web'])]));
    Illuminate\Support\Facades\Auth::guard('web')->setUser($user);
    App\Support\Tenant::clear();
}
function denied(callable $action): void {
    try { $action(); } catch (Symfony\Component\HttpKernel\Exception\HttpException $error) {
        if (in_array($error->getStatusCode(), [403,404], true)) { return; }
        throw $error;
    } catch (Illuminate\Database\Eloquent\ModelNotFoundException $error) { return; }
    throw new RuntimeException('Expected native access denial');
}
actor(2);
if (($argv[2] ?? '') === '--baseline') {
    if (App\Support\Tenant::resolve('one.example.invalid')->id !== 1 || !Illuminate\Support\Facades\Auth::user()->hasRole('admin')) {
        throw new RuntimeException('Baseline did not reproduce');
    }
    echo "REPRODUCED: non-platform tenant admin resolves a foreign host despite belonging only to tenant two.\n";
    exit;
}
denied(fn()=>App\Support\Tenant::resolve('one.example.invalid'));
App\Support\Tenant::clear();
if (App\Support\Tenant::resolve('two.example.invalid')->id !== 2) { throw new RuntimeException('Own tenant failed'); }
$db->table('organization_users')->where('id',1)->update(['status'=>0]);
denied(fn()=>App\Support\Tenant::assertAccess(App\Support\Tenant::current(), true));
$db->table('organization_users')->where('id',1)->update(['status'=>1]);
actor(2);
denied(function () use ($db) {
    $request = Illuminate\Http\Request::create('https://two.example.invalid/dashboard');
    (new App\Http\Middleware\ResolveTenant)->handle($request, function () use ($db) {
        $db->table('organization_users')->where('id',1)->update(['status'=>0]);
        return new Symfony\Component\HttpFoundation\Response('Synthetic private response');
    });
});
$db->table('organization_users')->where('id',1)->update(['status'=>1]);
actor(1);
if (App\Support\Tenant::resolve('two.example.invalid')->id !== 2) { throw new RuntimeException('Stored platform admin failed'); }
$db->table('users')->where('id',1)->update(['status'=>'Suspended']);
denied(fn()=>App\Support\Tenant::assertAccess(App\Support\Tenant::current(), true));
$db->table('users')->where('id',1)->update(['status'=>'Active']);
actor(2);
$organization=App\Support\Tenant::resolve('two.example.invalid');
$db->table('organizations')->where('id',2)->update(['status'=>'suspended']);
denied(fn()=>App\Support\Tenant::assertAccess($organization, true));
$db->table('organizations')->where('id',2)->update(['status'=>'active']);
Illuminate\Support\Facades\Auth::forgetGuards();
Illuminate\Support\Facades\Auth::shouldUse('student');
Illuminate\Support\Facades\Auth::guard('student')->setUser(App\Models\Student::findOrFail(10));
App\Support\Tenant::clear();
denied(fn()=>App\Support\Tenant::resolve('one.example.invalid'));
App\Support\Tenant::clear();
if (App\Support\Tenant::resolve('two.example.invalid')->id !== 2) { throw new RuntimeException('Own student tenant failed'); }
echo "PASS: native tenant/legacy-admin isolation, stored platform authority, membership/user/organisation revocation and student isolation.\n";
