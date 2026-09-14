<?php
require '/home/examelite/public_html/vendor/autoload.php';
$app=require '/home/examelite/public_html/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!$app->providerIsLoaded(\App\Providers\Tech4LearnWorkspaceProvider::class)) throw new RuntimeException('Workspace provider is not loaded.');
foreach(['tech4learn_workspaces','tech4learn_workspace_users','tech4learn_workspace_tickets','tech4learn_workspace_copies','tech4learn_content_transfers','tech4learn_authoring_requests','tech4learn_attempt_requests','tech4learn_attempt_clocks','tech4learn_proctor_evidence'] as $table) {
    if(!\Illuminate\Support\Facades\Schema::hasTable($table))throw new RuntimeException('Workspace migration is incomplete.');
}
foreach([\App\Http\Controllers\Tech4LearnProctorController::class,\App\Services\Tech4LearnProctorEvidence::class,\App\Services\Tech4LearnAttemptClock::class,\App\Services\Tech4LearnQuestionMedia::class,\App\Services\Tech4LearnAttemptAnswers::class,\App\Services\Tech4LearnAttemptPayload::class,\App\Services\Tech4LearnStudentContext::class,\App\Services\Tech4LearnStudentAttempts::class] as $service)if(!class_exists($service))throw new RuntimeException('Student attempt adapter is missing.');
foreach([
 ['GET','api/tech4learn/v1/results/11111111-1111-1111-1111-111111111111/learners/22222222-2222-2222-2222-222222222222/attempts','Tech4LearnResultController@attempts'],
 ['GET','api/tech4learn/v1/results/11111111-1111-1111-1111-111111111111/learners/22222222-2222-2222-2222-222222222222/attempts/1','Tech4LearnResultController@review'],
 ['POST','api/tech4learn/v1/results/11111111-1111-1111-1111-111111111111/learners/22222222-2222-2222-2222-222222222222/attempts/1','Tech4LearnResultController@save'],
 ['GET','api/tech4learn/v1/review/11111111-1111-1111-1111-111111111111/learners/22222222-2222-2222-2222-222222222222/attempts','Tech4LearnProctorController@attempts'],
 ['GET','api/tech4learn/v1/review/11111111-1111-1111-1111-111111111111/learners/22222222-2222-2222-2222-222222222222/attempts/1/captures/33333333-3333-3333-3333-333333333333','Tech4LearnProctorController@captures'],
 ['POST','api/tech4learn/v1/student/11111111-1111-1111-1111-111111111111/media','Tech4LearnStudentController@attempt'],
 ['POST','api/tech4learn/v1/student/11111111-1111-1111-1111-111111111111/result','Tech4LearnStudentController@attempt'],
 ['POST','api/tech4learn/v1/student/11111111-1111-1111-1111-111111111111/prepare','Tech4LearnStudentController@attempt'],
 ['POST','api/tech4learn/v1/student/11111111-1111-1111-1111-111111111111/start','Tech4LearnStudentController@attempt'],
 ['GET','tech4learn/launch','Tech4LearnNativeController@launch'],
 ['POST','tech4learn/launch','Tech4LearnNativeController@accept'],
 ['GET','tech4learn/library','Tech4LearnLibraryController@index'],
 ['POST','tech4learn/library/exam/1/copy','Tech4LearnLibraryController@copy'],
 ['GET','api/tech4learn/v1/workspace/status','Tech4LearnWorkspaceController@health'],
 ['GET','api/tech4learn/v1/content/11111111-1111-1111-1111-111111111111/questions','Tech4LearnContentController@questions'],
 ['POST','api/tech4learn/v1/content/11111111-1111-1111-1111-111111111111/transfer','Tech4LearnContentController@transfer'],
 ['GET','api/tech4learn/v1/authoring/11111111-1111-1111-1111-111111111111/choices/groups','Tech4LearnAuthoringController@choices'],
 ['POST','api/tech4learn/v1/authoring/11111111-1111-1111-1111-111111111111/exams/1/actions/add-questions','Tech4LearnAuthoringController@examAction'],
 ['POST','api/tech4learn/v1/authoring/11111111-1111-1111-1111-111111111111/questions','Tech4LearnAuthoringController@create'],
 ['POST','api/tech4learn/v1/authoring/11111111-1111-1111-1111-111111111111/taxonomy/subjects/new','Tech4LearnAuthoringController@saveTaxonomy'],
 ['POST','api/tech4learn/v1/authoring/11111111-1111-1111-1111-111111111111/questions/1','Tech4LearnAuthoringController@save'],
] as [$method,$path,$expected]) {
    $request=\Illuminate\Http\Request::create('https://examelite.com/'.$path,$method);
    $route=$app['router']->getRoutes()->match($request);
    if(!str_ends_with($route->getActionName(),$expected)) throw new RuntimeException('Workspace route is shadowed: '.$path);
}
$kernel=$app->make(\Illuminate\Contracts\Http\Kernel::class);
if(!in_array(\App\Http\Middleware\Tech4LearnWorkspaceGate::class,$kernel->getMiddlewareGroups()['web'],true))throw new RuntimeException('Workspace session gate is not registered.');
$csrf=$app->make(\App\Http\Middleware\VerifyCsrfToken::class);
$reflection=new ReflectionObject($csrf);$property=$reflection->getProperty('except');$property->setAccessible(true);
foreach($property->getValue($csrf) as $except) {
    if(\Illuminate\Support\Str::is(trim($except,'/'),'tech4learn/launch'))throw new RuntimeException('Launch must not be excluded from CSRF checks.');
}
foreach(['launch','library','navigation'] as $view) {
    if(!view()->exists('tech4learn::'.$view)) throw new RuntimeException('Missing workspace view.');
    $compiled=\Illuminate\Support\Facades\Blade::compileString(file_get_contents(resource_path('views/tech4learn/'.$view.'.blade.php')));
    $temp=tempnam(sys_get_temp_dir(),'t4l-blade-');file_put_contents($temp,$compiled);
    try {passthru('php -l '.escapeshellarg($temp),$code);if($code!==0)throw new RuntimeException('Workspace view does not compile.');}
    finally {unlink($temp);}
}
echo "Workspace provider, routes, middleware, schema and Blade templates verified.\n";
