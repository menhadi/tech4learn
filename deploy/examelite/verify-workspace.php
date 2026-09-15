<?php
require '/home/examelite/public_html/vendor/autoload.php';
$app=require '/home/examelite/public_html/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!$app->providerIsLoaded(\App\Providers\Tech4LearnWorkspaceProvider::class)) throw new RuntimeException('Workspace provider is not loaded.');
foreach([\App\Services\Tech4LearnExamTranslations::class,\App\Services\ExamTranslationService::class,\App\Http\Controllers\Tech4LearnTranslationController::class] as $service)if(!class_exists($service))throw new RuntimeException('Native translation review adapter is missing.');
foreach(['exam_language_translations','question_langs'] as $table)if(!\Illuminate\Support\Facades\Schema::hasColumns($table,['source_fingerprint','source_field_fingerprints']))throw new RuntimeException('Native translation fingerprints are missing.');
if(!method_exists(\App\Http\Controllers\ExamDocumentController::class,'generate'))throw new RuntimeException('Native PDF generation controller is missing.');
foreach([\App\Services\Tech4LearnExamDocuments::class,\App\Services\ExamDocumentLifecycleService::class,\App\Http\Controllers\Tech4LearnDocumentController::class] as $service)if(!class_exists($service))throw new RuntimeException('Native approved document adapter is missing.');
if(!\Illuminate\Support\Facades\Schema::hasColumns('exam_pdf_builds',['organization_id','exam_id','package_id','language_id','document_type','status','current_path','updated_at']))throw new RuntimeException('Native PDF publication schema is incomplete.');
if(!class_exists(\App\Http\Controllers\LanguageController::class)||!\Illuminate\Support\Facades\Schema::hasColumns('languages',['organization_id','source_language_id','name','code','value1','value2','is_enabled']))throw new RuntimeException('Native language management requires the current ExamElite language controller and schema.');
if(!class_exists(\App\Http\Controllers\PackageController::class)||!\Illuminate\Support\Facades\Schema::hasColumns('packages',['organization_id','name','slug','package_type','status','display_order','expiry_days','auto_enroll_on_registration','show_pdf_download','show_solution_pdf_download','flashcards_enabled','guest_flashcards_enabled','ai_flashcard_generation_enabled']))throw new RuntimeException('Native package management requires the current ExamElite package controller and schema.');
foreach(['package_groups','package_tags','package_tag_package','exam_packages'] as $table)if(!\Illuminate\Support\Facades\Schema::hasTable($table))throw new RuntimeException('Native package relationships are incomplete.');
foreach(['pdf','solution_pdf'] as $prefix)foreach(['title','header','footer','watermark'] as $part)if(!\Illuminate\Support\Facades\Schema::hasColumn('packages',$prefix.'_'.$part.'_text'))throw new RuntimeException('Native package document settings are incomplete.');
foreach(['tech4learn_workspaces','tech4learn_workspace_users','tech4learn_workspace_tickets','tech4learn_workspace_copies','tech4learn_content_transfers','tech4learn_authoring_requests','tech4learn_attempt_requests','tech4learn_attempt_clocks','tech4learn_proctor_evidence'] as $table) {
    if(!\Illuminate\Support\Facades\Schema::hasTable($table))throw new RuntimeException('Workspace migration is incomplete.');
}
foreach([\App\Http\Controllers\Tech4LearnProctorController::class,\App\Services\Tech4LearnProctorEvidence::class,\App\Services\Tech4LearnAttemptClock::class,\App\Services\Tech4LearnQuestionMedia::class,\App\Services\Tech4LearnAttemptAnswers::class,\App\Services\Tech4LearnAttemptPayload::class,\App\Services\Tech4LearnStudentContext::class,\App\Services\Tech4LearnStudentAttempts::class] as $service)if(!class_exists($service))throw new RuntimeException('Student attempt adapter is missing.');
foreach([
 ['GET','api/tech4learn/v1/translations/11111111-1111-1111-1111-111111111111/exams/1/languages/1','Tech4LearnTranslationController@review'],
 ['GET','api/tech4learn/v1/translations/11111111-1111-1111-1111-111111111111/exams/1/languages/1/media/0/'.str_repeat('a',64),'Tech4LearnTranslationController@media'],
 ['GET','api/tech4learn/v1/documents/11111111-1111-1111-1111-111111111111/exams/1/questions','Tech4LearnDocumentController@read'],
 ['GET','api/tech4learn/v1/documents/11111111-1111-1111-1111-111111111111/exams/1/questions/status','Tech4LearnDocumentController@documentStatus'],
 ['POST','api/tech4learn/v1/student/11111111-1111-1111-1111-111111111111/history','Tech4LearnStudentController@attempt'],
 ['GET','api/tech4learn/v1/results/11111111-1111-1111-1111-111111111111/learners/22222222-2222-2222-2222-222222222222/attempts/1/media/1/'.str_repeat('a',64),'Tech4LearnResultController@media'],
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
 ['POST','api/tech4learn/v1/authoring/11111111-1111-1111-1111-111111111111/questions/1/image','Tech4LearnAuthoringController@questionImageWrite'],
 ['GET','api/tech4learn/v1/authoring/11111111-1111-1111-1111-111111111111/questions/1/media/'.str_repeat('a',64),'Tech4LearnAuthoringController@questionMedia'],
 ['POST','api/tech4learn/v1/authoring/11111111-1111-1111-1111-111111111111/taxonomy/subjects/new','Tech4LearnAuthoringController@saveTaxonomy'],
 ['POST','api/tech4learn/v1/authoring/11111111-1111-1111-1111-111111111111/taxonomy/languages/1/disable','Tech4LearnAuthoringController@disableLanguage'],
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
