<?php
namespace { require $argv[1]; }
namespace App\Support {
    class Tenant { public static int $host=1; public static function hostId($host) {return self::$host;} }
    class SaasAccess { public static bool $allowed=true; public static function abortIfLimitReached($resource) { if(!self::$allowed) throw new \RuntimeException('Limit',403); } }
}
namespace App\Models {
    class Student { public static function create($values) { return (object)['id'=>\Illuminate\Support\Facades\DB::table('students')->insertGetId($values)]; } }
}
namespace App\Http\Controllers {
    class Controller {}
    function abort_unless($condition,$status,$message='') {if(!$condition) throw new \RuntimeException($message,$status);}
    function response(){return new class{function json($data){return new \Illuminate\Http\JsonResponse($data);}};}
}
namespace {
    require __DIR__.'/Tech4LearnPlatformController.php';
    $db=new \Illuminate\Database\Capsule\Manager();$db->addConnection(['driver'=>'sqlite','database'=>':memory:']);$db->setAsGlobal();
    $db->getContainer()->instance('db',$db->getDatabaseManager());
    $db->getContainer()->instance('hash',new \Illuminate\Hashing\BcryptHasher(['rounds'=>4]));
    \Illuminate\Support\Facades\Facade::setFacadeApplication($db->getContainer());
    $pdo=$db->getConnection()->getPdo();
    $pdo->exec('CREATE TABLE organizations(id INTEGER PRIMARY KEY); INSERT INTO organizations VALUES(1),(2);
      CREATE TABLE students(id INTEGER PRIMARY KEY AUTOINCREMENT,organization_id INTEGER,name TEXT,email TEXT,phone TEXT,password TEXT,status TEXT);
      INSERT INTO students(id,organization_id,name,email,status) VALUES(10,1,\'Existing\',\'existing@example.test\',\'Active\'),(20,2,\'Other\',null,\'Active\');
      CREATE TABLE tech4learn_student_links(tech4learn_organisation TEXT,learner TEXT,organization_id INTEGER,student_id INTEGER,PRIMARY KEY(tech4learn_organisation,learner),UNIQUE(organization_id,student_id));
      CREATE TABLE exams(id INTEGER,organization_id INTEGER,name TEXT,duration INTEGER,start_date TEXT,end_date TEXT);
      INSERT INTO exams VALUES(1,1,\'Shared\',60,null,null),(2,2,\'Private\',60,null,null);
      CREATE TABLE exam_results(id INTEGER,organization_id INTEGER,student_id INTEGER,exam_id INTEGER,percent INTEGER,result TEXT,end_time TEXT);
      INSERT INTO exam_results VALUES(1,1,10,1,80,\'Pass\',\'2026-01-01\'),(2,1,10,1,0,\'Fail\',null),(3,2,10,2,10,\'Fail\',\'2026-01-01\');');
    $org='11111111-1111-4111-8111-111111111111';$other='22222222-2222-4222-8222-222222222222';$learner='33333333-3333-4333-8333-333333333333';$fresh='44444444-4444-4444-8444-444444444444';
    $token=str_repeat('a',64);$file=tempnam(sys_get_temp_dir(),'ee-test-');
    $config=['_platform'=>['enabled'=>true,'organization_id'=>1,'token_hash'=>hash('sha256',$token)],$org=>['enabled'=>true,'organization_id'=>1,'learners'=>[$learner=>10]]];
    file_put_contents($file,json_encode($config));
    $controller=new class($file) extends \App\Http\Controllers\Tech4LearnPlatformController {
        public function __construct(private string $file){} protected function configPath():string{return $this->file;}
    };
    $request=function($params=[])use($token){$r=\Illuminate\Http\Request::create('https://examelite.com/api/tech4learn/v1/platform','POST',$params);$r->headers->set('Authorization','Bearer '.$token);return $r;};
    $get=function($params=[])use($token){$r=\Illuminate\Http\Request::create('https://examelite.com/api/tech4learn/v1/platform','GET',$params);$r->headers->set('Authorization','Bearer '.$token);return $r;};
    $check=function($ok,$label){if(!$ok)throw new \RuntimeException('FAIL: '.$label);};
    $reject=function($code,$fn)use($check){try{$fn();throw new \RuntimeException('Expected rejection');}catch(\RuntimeException $e){$check($e->getCode()===$code,'rejection '.$code);}};
    try {
        $first=$controller->connect($request(['name'=>'Local name']),$org,$learner)->getData(true);
        $check($first['student_id']===10,'reviewed link reused');
        $created=$controller->connect($request(['name'=>'New student']),$org,$fresh)->getData(true);
        $repeat=$controller->connect($request(['name'=>'Changed input']),$org,$fresh)->getData(true);
        $check($created['student_id']===$repeat['student_id'],'idempotent retry');
        $check($pdo->query('SELECT count(*) FROM students')->fetchColumn()==3,'no duplicate or reverse import');
        $check($pdo->query('SELECT email FROM students WHERE id='.$created['student_id'])->fetchColumn()===null,'no borrowed email');
        $check($pdo->query('SELECT name FROM students WHERE id=10')->fetchColumn()==='Existing','existing student not edited');
        $result=$controller->results($get(['exams'=>'1']),$org,$learner)->getData(true);
        $check(count($result['items'])===1 && $result['items'][0]['id']===1,'only shared finished result');
        $check(count($controller->results($get(['exams'=>'']),$org,$learner)->getData(true)['items'])===0,'empty grant');
        $reject(404,fn()=>$controller->results($get(['exams'=>'1']),$other,$learner));
        $check(count($controller->exams($get())->getData(true)['items'])===1,'tenant catalogue');
        $check($controller->validateExams($request(['exam_ids'=>[2]]))->getData(true)['valid']===false,'foreign exam rejected');
        \App\Support\SaasAccess::$allowed=false;
        $reject(403,fn()=>$controller->connect($request(['name'=>'Over limit']),$other,$fresh));
        $check($pdo->query("SELECT count(*) FROM tech4learn_student_links WHERE tech4learn_organisation='$other'")->fetchColumn()==0,'failed creation rolled back');
        \App\Support\Tenant::$host=2;$reject(403,fn()=>$controller->status($get()));\App\Support\Tenant::$host=1;
        $config['_platform']['enabled']=false;file_put_contents($file,json_encode($config));$reject(401,fn()=>$controller->status($get()));
        echo "PASS: platform isolation, reviewed linking, idempotency, minimal identity, limits rollback and revocation\n";
    } finally {unlink($file);}
}
