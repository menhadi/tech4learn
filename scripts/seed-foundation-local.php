<?php
// Fresh synthetic local pilot only. Never imports production accounts or learners.
if (PHP_SAPI !== 'cli' || $argc !== 2) {
    throw new RuntimeException('Usage: php scripts/seed-foundation-local.php FOUNDATION');
}
$base = realpath($argv[1]);
$local = realpath(__DIR__.'/../.local');
if (!$base || !$local || !str_starts_with(str_replace('\\', '/', $base).'/', str_replace('\\', '/', $local).'/')) {
    throw new RuntimeException('Foundation must be inside this workspace .local.');
}
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!$app->environment('local') || config('database.default') !== 'sqlite'
    || realpath(config('database.connections.sqlite.database')) !== realpath($base.'/database/foundation.sqlite')) {
    throw new RuntimeException('Only the isolated local SQLite database is allowed.');
}
$modelPath = str_replace('\\', '/', (new ReflectionClass(App\Models\User::class))->getFileName());
if (!str_starts_with($modelPath, str_replace('\\', '/', $base).'/app/')) {
    throw new RuntimeException('Application classes must resolve to the copied foundation.');
}
if (App\Models\User::count() || App\Models\Student::count() || App\Models\Exam::count()) {
    throw new RuntimeException('Refusing to seed a non-empty pilot.');
}
$password = bin2hex(random_bytes(18));
Illuminate\Support\Facades\DB::transaction(function () use ($password) {
    $organization = App\Models\Organization::where('slug', 'examelite')->firstOrFail();
    $organization->update(['name' => 'Tech4Learn Local Pilot', 'domain' => '127.0.0.1', 'email' => null, 'phone' => null, 'logo' => null, 'favicon' => null]);
    App\Models\Configuration::updateOrCreate(['organization_id' => $organization->id], [
        'name' => 'Tech4Learn', 'organization_name' => 'Tech4Learn Local Pilot',
        'domain_name' => '127.0.0.1', 'timezone' => 'Asia/Kolkata',
        'powered_by' => 'Tech4Learn', 'powered_link' => 'http://127.0.0.1:8001',
        'front_end' => false, 'guest_login' => false,
    ]);
    $user = App\Models\User::create([
        'name' => 'Synthetic Foundation Administrator', 'username' => 'foundation-admin',
        'email' => 'foundation-admin@example.invalid', 'password' => $password,
        'status' => 'Active', 'is_platform_admin' => true, 'language' => 'en',
    ]);
    $role = Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $user->assignRole($role);
    Illuminate\Support\Facades\DB::table('organization_users')->insert([
        'organization_id' => $organization->id, 'user_id' => $user->id,
        'role' => 'owner', 'status' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $pages = [
        'dashboard' => 'Dashboard', 'subjects.index' => 'Subjects',
        'topics.index' => 'Topics', 'stopics.index' => 'Subtopics',
        'sections.index' => 'Sections', 'questions.index' => 'Questions',
        'exams.index' => 'Exams', 'results.index' => 'Results',
        'students.index' => 'Students', 'packages.index' => 'Packages',
        'languages.index' => 'Languages',
    ];
    foreach ($pages as $route => $label) {
        if (Illuminate\Support\Facades\Route::has($route) && !App\Models\Page::where('action_name', $route)->exists()) {
            $page = new App\Models\Page();
            $page->action_name = $route;
            $page->page_name = $label;
            $page->icon = 'ri-file-list-line';
            $page->parent_id = null;
            $page->ordering = 10;
            $page->save();
        }
    }
});
file_put_contents($base.'/local-pilot-credentials.json', json_encode([
    'username' => 'foundation-admin', 'password' => $password,
    'scope' => 'Synthetic localhost pilot only; never use on a deployed service',
], JSON_PRETTY_PRINT));
@chmod($base.'/local-pilot-credentials.json', 0600);
echo "Synthetic Tech4Learn organisation and local administrator created; credentials stay in the ignored pilot.\n";
