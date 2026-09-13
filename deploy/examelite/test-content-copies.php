<?php
// Uses the installed ExamElite model definitions against an isolated in-memory database.
require $argv[1];
require __DIR__.'/Tech4LearnWorkspacePolicy.php';
require __DIR__.'/Tech4LearnContentCopies.php';
use Illuminate\Database\Capsule\Manager;
use Illuminate\Support\Facades\DB;
use App\Services\Tech4LearnContentCopies;
$db=new Manager();$db->addConnection(['driver'=>'sqlite','database'=>':memory:']);$db->setAsGlobal();$db->bootEloquent();
$db->getContainer()->instance('db',$db->getDatabaseManager());
$files=new class {
 public array $files=['shared/paper.pdf'=>'synthetic PDF','shared/answer.pdf'=>'synthetic answer'];
 public function disk($name){return $this;}
 public function copy($from,$to){if(!isset($this->files[$from]))return false;$this->files[$to]=$this->files[$from];return true;}
 public function delete($path){unset($this->files[$path]);}
};
$db->getContainer()->instance('filesystem',$files);
Illuminate\Support\Facades\Facade::setFacadeApplication($db->getContainer());
$pdo=$db->getConnection()->getPdo();
$tables=[
 'tech4learn_workspaces'=>['source_organization_id','organization_id','restrictions'],
 'tech4learn_workspace_copies'=>['workspace_id','kind','source_id','target_id'],
 'subjects'=>['organization_id','subject_name','ordering'], 'subject_groups'=>['subject_id','group_id'],
 'groups'=>['organization_id','group_name','slug'],
 'category'=>['organization_id','parent_id','name','slug'],
 'topics'=>['subject_id','group_id','name'], 'stopics'=>['subject_id','group_id','topic_id','name'],
 'questions'=>['organization_id','question_code','original_question_id','question','subject_id','topic_id','stopic_id','question_section_id','language_id','passage_id','fill_blank_config','nat_config'],
 'question_groups'=>['question_id','group_id'], 'question_langs'=>['question_id','language_id','question'],
 'question_tags'=>['organization_id','name'], 'question_question_tag'=>['question_id','question_tag_id'],
 'question_taxonomies'=>['organization_id','question_id','group_id','subject_id','topic_id','stopic_id'],
 'languages'=>['organization_id','source_language_id','name','code'],
 'passages'=>['organization_id','name'], 'passage_langs'=>['passage_id','language_id','name'],
 'question_sections'=>['organization_id','name'], 'question_section_groups'=>['question_section_id','group_id'],
 'exams'=>['organization_id','name','slug','duration','created_by_student_id','is_student_practice','category_level_1','category_level_2'],
 'exam_groups'=>['exam_id','group_id'], 'exam_sections'=>['exam_id','question_section_id','name','duration','display_order'],
 'exam_questions'=>['exam_id','question_id','exam_section_id'], 'exam_subject_durations'=>['exam_id','subject_id','duration'],
 'exam_languages'=>['exam_id','language_id','translation_status','last_error','translating_at','translated_at','auto_translate','auto_pdf','translation_approved_at','translation_approved_by'],
 'exam_language_translations'=>['exam_id','language_id','name','instruction','translated_by'],
 'exam_quality_sources'=>['organization_id','exam_id','role','kind','label','storage_disk','file_path','source_url','provider','provider_file_id','provider_uploaded_at','is_active'],
];
foreach($tables as $table=>$columns) {
 $fields=array_map(fn($c)=>'"'.$c.'" '.(str_ends_with($c,'_id')?'INTEGER':'TEXT'),$columns);
 $pdo->exec('CREATE TABLE '.$table.'(id INTEGER PRIMARY KEY AUTOINCREMENT,'.implode(',',$fields).',created_at TEXT,updated_at TEXT)');
}
$pdo->exec("INSERT INTO tech4learn_workspaces(id,source_organization_id,organization_id,restrictions) VALUES(1,10,20,'[]');
 INSERT INTO subjects(id,organization_id,subject_name) VALUES(1,10,'Shared subject'),(2,30,'Other tenant');
 INSERT INTO groups(id,organization_id,group_name,slug) VALUES(1,10,'{\"en\":\"Shared group\"}','shared-group');
 INSERT INTO subject_groups(subject_id,group_id) VALUES(1,1);
 INSERT INTO topics(id,subject_id,name) VALUES(1,1,'Topic');
 INSERT INTO question_sections(id,organization_id,name) VALUES(1,10,'Section');
 INSERT INTO languages(id,organization_id,name,code) VALUES(1,10,'English','en');
 INSERT INTO questions(id,organization_id,question_code,question,subject_id,topic_id,question_section_id,language_id,nat_config) VALUES(1,10,'SOURCE','Original',1,1,1,1,'{\"precision\":2}');
 INSERT INTO question_langs(question_id,language_id,question) VALUES(1,1,'Translated original');
 INSERT INTO question_groups(question_id,group_id) VALUES(1,1);
 INSERT INTO exams(id,organization_id,name,slug,duration) VALUES(1,10,'Shared exam','shared',180);
 INSERT INTO exam_sections(id,exam_id,question_section_id,name,duration,display_order) VALUES(1,1,1,'Section',90,1);
 INSERT INTO exam_questions(exam_id,question_id,exam_section_id) VALUES(1,1,1);
 INSERT INTO exam_subject_durations(exam_id,subject_id,duration) VALUES(1,1,90);
 INSERT INTO exam_languages(exam_id,language_id,translation_status) VALUES(1,1,'ready');
 INSERT INTO exam_quality_sources(organization_id,exam_id,role,kind,storage_disk,file_path,is_active,provider_file_id) VALUES(10,1,'questions','file','local','shared/paper.pdf',1,'provider-private-id');
 INSERT INTO exam_language_translations(exam_id,language_id,name,instruction) VALUES(1,1,'Translated exam','Instructions');");
$service=new Tech4LearnContentCopies();
function check($ok,string $label):void {if(!$ok)throw new RuntimeException($label);}
function denied(callable $fn):void {try{$fn();}catch(DomainException $e){return;}throw new RuntimeException('Expected denial');}
$exam=$service->copy('1',20,'exam',1);
$copy=App\Models\Exam::findOrFail($exam);
check((int)$copy->organization_id===20 && (int)$copy->duration===180,'Owned exam and duration');
$question=$copy->questions->first();
check((int)$question->organization_id===20 && $question->nat_config===['precision'=>2],'Question ownership and numeric answer settings');
check((int)$question->subject->organization_id===20 && (int)$question->topic->subject->organization_id===20,'Owned taxonomy');
check((int)$question->groups->first()->organization_id===20 && $question->groups->first()->slug!=='shared-group','Owned group and distinct slug');
check($copy->sections->count()===1 && (int)$copy->sections->first()->duration===90,'Sections and timing');
check((int)$question->pivot->exam_section_id===(int)$copy->sections->first()->id,'Question assigned to copied section');
check($question->langs->first()->question==='Translated original','Question translation');
check($copy->languageTranslations->first()->name==='Translated exam','Exam translation');
check($copy->languages->first()->pivot->translation_status==='ready' && !$copy->languages->first()->pivot->translation_approved_by,'Translation state without source approver identity');
$asset=App\Models\ExamQualitySource::where('exam_id',$exam)->first();
check((int)$asset->organization_id===20 && $asset->file_path!=='shared/paper.pdf' && $files->files[$asset->file_path]==='synthetic PDF' && $asset->provider_file_id===null,'Source asset is independently copied without provider credentials');
$question->question='Organisation edit';$question->save();
check($service->copy('1',20,'exam',1)===$exam,'Idempotent exam retry');
check($service->copy('1',20,'question',1)===$question->id,'Shared question reuses owned version');
check(App\Models\Question::find(1)->question==='Original','Original unchanged');
check(App\Models\Question::find($question->id)->question==='Organisation edit','Retry preserves organisation edits');
denied(fn()=>$service->copy('1',30,'exam',1));
denied(fn()=>$service->copy('1',20,'subject',2));
$private=App\Models\Exam::create(['organization_id'=>10,'name'=>'Private practice','created_by_student_id'=>999,'is_student_practice'=>true]);
denied(fn()=>$service->copy('1',20,'exam',$private->id));
$broken=App\Models\Exam::create(['organization_id'=>10,'name'=>'Broken sources']);
App\Models\ExamQualitySource::create(['organization_id'=>10,'exam_id'=>$broken->id,'role'=>'questions','kind'=>'file','storage_disk'=>'local','file_path'=>'shared/paper.pdf','is_active'=>true]);
App\Models\ExamQualitySource::create(['organization_id'=>10,'exam_id'=>$broken->id,'role'=>'answers','kind'=>'file','storage_disk'=>'local','file_path'=>'shared/missing.pdf','is_active'=>true]);
$before=$files->files;
denied(fn()=>$service->copy('1',20,'exam',$broken->id));
check($files->files===$before && !DB::table('tech4learn_workspace_copies')->where('kind','exam')->where('source_id',$broken->id)->exists(),'Failed file copy rolls back owned records and files');
DB::table('category')->insert(['id'=>1,'organization_id'=>10,'parent_id'=>1,'name'=>'Circular','slug'=>'circular']);
$circular=App\Models\Exam::create(['organization_id'=>10,'name'=>'Circular','category_level_1'=>1]);
denied(fn()=>$service->copy('1',20,'exam',$circular->id));
DB::table('tech4learn_workspaces')->where('id',1)->update(['restrictions'=>'["exams"]']);
denied(fn()=>$service->copy('1',20,'exam',1));
echo "Content copies: ownership, original preservation, retry, taxonomy, translations, sections, timers and restrictions passed.\n";
