<?php
// Isolated SQLite contract test; no Laravel boot, .env, or production database.
namespace {
    require $argv[1]; // Existing ExamElite vendor/autoload.php
}
namespace App\Support {
    class Tenant { public static int $host = 1; public static function hostId($host) { return self::$host; } }
}
namespace App\Http\Controllers {
    class Controller {}
    function abort($status, $message='') { throw new \RuntimeException($message, $status); }
    function abort_unless($condition, $status, $message='') { if (!$condition) abort($status,$message); }
    function response() { return new class { function json($data) { return new \Illuminate\Http\JsonResponse($data); } }; }
}
namespace {
    require __DIR__.'/Tech4LearnReadController.php';
    $db=new \Illuminate\Database\Capsule\Manager();
    $db->addConnection(['driver'=>'sqlite','database'=>':memory:']);
    $db->setAsGlobal();
    $db->getContainer()->instance('db',$db->getDatabaseManager());
    \Illuminate\Support\Facades\Facade::setFacadeApplication($db->getContainer());
    $pdo=$db->getConnection()->getPdo();
    $pdo->exec('CREATE TABLE exams(id INTEGER,organization_id INTEGER,name TEXT,duration INTEGER,start_date TEXT,end_date TEXT);
      CREATE TABLE students(id INTEGER,organization_id INTEGER);
      CREATE TABLE exam_results(id INTEGER,organization_id INTEGER,student_id INTEGER,exam_id INTEGER,percent NUMERIC,result TEXT,end_time TEXT);');
    $pdo->exec("INSERT INTO exams VALUES(1,1,'Shared exam',60,NULL,NULL),(2,2,'Other tenant',60,NULL,NULL),(3,1,'Not shared',60,NULL,NULL);
      INSERT INTO students VALUES(10,1),(20,2),(30,1);
      INSERT INTO exam_results VALUES(1,1,10,1,75,'Pass','2026-01-01'),(2,2,20,2,90,'Pass','2026-01-01'),(3,1,30,1,80,'Pass','2026-01-01'),(4,1,10,3,70,'Pass','2026-01-01'),(5,1,10,1,40,'Fail',NULL);");
    $org='11111111-1111-4111-8111-111111111111';
    $learner='22222222-2222-4222-8222-222222222222';
    $token=str_repeat('a',64);
    $file=tempnam(sys_get_temp_dir(),'t4l-contract');
    $grant=['enabled'=>true,'organization_id'=>1,'token_hash'=>hash('sha256',$token),'exam_ids'=>[1,2],'learners'=>[$learner=>10]];
    file_put_contents($file,json_encode([$org=>$grant]));
    $controller=new class($file) extends \App\Http\Controllers\Tech4LearnReadController {
      function __construct(private string $path) {}
      protected function configPath(): string { return $this->path; }
    };
    function expect($ok,$message) { if (!$ok) throw new \RuntimeException($message); }
    function deny($callback,$code) {
      try {$callback();} catch (\RuntimeException $e) { expect($e->getCode()===$code,'Wrong denial code');return; }
      throw new \RuntimeException('Access unexpectedly allowed');
    }
    $request=\Illuminate\Http\Request::create('https://examelite.com/api/tech4learn/v1/status','GET');
    $request->headers->set('X-Tech4Learn-Organisation',$org);
    $request->headers->set('Authorization','Bearer '.$token);
    try {
      expect($controller->status($request)->getData(true)['version']===1,'Status');
      $exams=$controller->exams($request)->getData(true);
      expect(count($exams['items'])===1 && $exams['items'][0]['id']===1,'Exam grant or tenant leak');
      $results=$controller->results($request,$learner)->getData(true);
      expect(count($results['items'])===1 && $results['items'][0]['id']===1,'Student, exam, tenant or unfinished result leak');
      deny(fn()=>$controller->results($request,'33333333-3333-4333-8333-333333333333'),404);
      \App\Support\Tenant::$host=2;
      deny(fn()=>$controller->status($request),403);
      \App\Support\Tenant::$host=1;
      $request->headers->set('Authorization','Bearer '.str_repeat('b',64));
      deny(fn()=>$controller->status($request),401);
      $request->headers->set('Authorization','Bearer '.$token);
      $request->query->set('after','1 OR 1=1');
      deny(fn()=>$controller->exams($request),422);
      $request->query->set('after','0');
      for($i=4;$i<=55;$i++) $pdo->exec("INSERT INTO exams VALUES($i,1,'Page exam',30,NULL,NULL)");
      $grant['exam_ids']=range(1,55);file_put_contents($file,json_encode([$org=>$grant]));
      $page=$controller->exams($request)->getData(true);
      expect(count($page['items'])===50 && $page['next']===51,'Cursor first page');
      $request->query->set('after',(string)$page['next']);
      $last=$controller->exams($request)->getData(true);
      expect(count($last['items'])===4 && $last['next']===null,'Cursor final page');
      $grant['enabled']=false;file_put_contents($file,json_encode([$org=>$grant]));
      deny(fn()=>$controller->status($request),401);
      expect((int)$pdo->query('SELECT count(*) FROM exam_results')->fetchColumn()===5,'Read wrote results');
      echo "PASS: credential, revocation, host/tenant, exam grant, learner mapping, finished results and pagination\n";
    } finally {unlink($file);}
}
