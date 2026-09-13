<?php
namespace App\Http\Controllers;

use App\Models\Student;
use App\Support\Tenant;
use App\Support\SaasAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Dedicated server credential; never accepts an ExamElite student ID from a caller. */
class Tech4LearnPlatformController extends Controller
{
    protected function configPath(): string { return '/etc/examelite/tech4learn-read.json'; }
    protected function configuration(Request $r): array {
        $token=(string)$r->bearerToken();
        abort_unless(preg_match('/^[a-f0-9]{64}$/D',$token),401);
        abort_unless(is_readable($this->configPath()),503);
        $config=json_decode(file_get_contents($this->configPath()),true,32,JSON_THROW_ON_ERROR);
        $g=$config['_platform'] ?? null;
        abort_unless(is_array($g) && ($g['enabled'] ?? false)===true && is_string($g['token_hash'] ?? null)
            && hash_equals($g['token_hash'],hash('sha256',$token)),401);
        abort_unless(is_int($g['organization_id']) && $g['organization_id']>0
            && $g['organization_id']===(int)Tenant::hostId($r->getHost()),403);
        return $config;
    }
    protected function uuid(string $id): void {
        abort_unless(preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D',$id),422);
    }
    private function cursor(Request $r): int {
        $v=(string)$r->query('after','0'); abort_unless(preg_match('/^\d{1,15}$/D',$v),422); return (int)$v;
    }
    private function examIds(Request $r): array {
        $s=(string)$r->query('exams',''); abort_unless(strlen($s)<=17000 && preg_match('/^(?:[1-9]\d{0,14}(?:,[1-9]\d{0,14})*)?$/D',$s),422);
        $ids=$s==='' ? [] : array_map('intval',explode(',',$s)); abort_unless(count($ids)<=1000,422); return $ids;
    }
    protected function reply(int $tenant,array $data) {
        return response()->json(array_merge(['version'=>1,'organization_id'=>$tenant],$data))->header('Cache-Control','no-store');
    }
    public function status(Request $r) {
        $g=$this->configuration($r)['_platform']; return $this->reply($g['organization_id'],['connected'=>true]);
    }
    public function exams(Request $r, ?string $org=null) {
        $g=$this->configuration($r)['_platform']; if ($org!==null) $this->uuid($org);
        $search=(string)$r->query('search',''); abort_unless(strlen($search)<=600,422);
        $q=DB::table('exams')->where('organization_id',$g['organization_id'])->where('id','>',$this->cursor($r));
        if ($org!==null) $q->whereIn('id',$this->examIds($r));
        if ($search!=='') $q->where('name','like','%'.$search.'%');
        $rows=$q->orderBy('id')->limit(51)->get(['id','name','duration','start_date','end_date']);
        $more=$rows->count()>50; $rows=$rows->take(50)->values();
        return $this->reply($g['organization_id'],['items'=>$rows,'next'=>$more ? $rows->last()->id : null]);
    }
    public function validateExams(Request $r) {
        $g=$this->configuration($r)['_platform']; $ids=$r->input('exam_ids');
        abort_unless(is_array($ids) && count($ids)<=1000,422);
        foreach ($ids as $id) abort_unless(is_int($id) && $id>0,422);
        return $this->reply($g['organization_id'],['valid'=>DB::table('exams')->where('organization_id',$g['organization_id'])->whereIn('id',$ids)->count()===count(array_unique($ids))]);
    }
    public function connect(Request $r,string $org,string $learner) {
        $config=$this->configuration($r); $tenant=$config['_platform']['organization_id'];
        $this->uuid($org); $this->uuid($learner);
        $name=$r->input('name'); abort_unless(is_string($name) && trim($name)!=='' && mb_strlen($name)<=255,422);
        $id=DB::transaction(function() use ($config,$tenant,$org,$learner,$name) {
            // Lock the owning organisation first so provisioning and plan-limit checks are serialized.
            abort_unless(DB::table('organizations')->where('id',$tenant)->lockForUpdate()->first(['id']),404);
            DB::table('tech4learn_student_links')->insertOrIgnore(['tech4learn_organisation'=>$org,'learner'=>$learner,'organization_id'=>$tenant,'student_id'=>null]);
            $link=DB::table('tech4learn_student_links')->where('tech4learn_organisation',$org)->where('learner',$learner)->lockForUpdate()->first();
            abort_unless($link && (int)$link->organization_id===$tenant,409);
            if ($link->student_id!==null) {
                abort_unless(DB::table('students')->where('id',$link->student_id)->where('organization_id',$tenant)->exists(),409);
                return (int)$link->student_id;
            }
            // Preserve only a previously reviewed pilot mapping, never infer ownership from email/name.
            $legacy=$config[$org] ?? [];
            $student=(($legacy['enabled'] ?? false)===true && ($legacy['organization_id'] ?? null)===$tenant) ? ($legacy['learners'][$learner] ?? null) : null;
            if ($student!==null) {
                abort_unless(is_int($student) && $student>0 && DB::table('students')->where('id',$student)->where('organization_id',$tenant)->exists(),409);
                foreach ($config as $key=>$grant) {
                    if ($key!==$org && $key!=='_platform' && ($grant['enabled'] ?? false) && ($grant['organization_id'] ?? null)===$tenant)
                        abort_unless(!in_array($student,array_values($grant['learners'] ?? []),true),409);
                }
            } else {
                SaasAccess::abortIfLimitReached('students');
                $student=(int)Student::create(['organization_id'=>$tenant,'name'=>trim($name),'email'=>null,'phone'=>null,
                    'password'=>Hash::make(bin2hex(random_bytes(32))),'status'=>'Active'])->id;
            }
            DB::table('tech4learn_student_links')->where('tech4learn_organisation',$org)->where('learner',$learner)->update(['student_id'=>$student]);
            return $student;
        });
        return $this->reply($tenant,['tech4learn_organisation_id'=>$org,'learner_id'=>$learner,'student_id'=>$id]);
    }
    public function results(Request $r,string $org,string $learner) {
        $tenant=$this->configuration($r)['_platform']['organization_id']; $this->uuid($org);$this->uuid($learner);
        $student=DB::table('tech4learn_student_links')->where('tech4learn_organisation',$org)->where('learner',$learner)->where('organization_id',$tenant)->value('student_id');
        abort_unless($student!==null,404);
        $rows=DB::table('exam_results as r')->join('exams as e',function($j){$j->on('e.id','=','r.exam_id')->on('e.organization_id','=','r.organization_id');})
            ->where('r.organization_id',$tenant)->where('r.student_id',$student)->whereIn('r.exam_id',$this->examIds($r))
            ->whereNotNull('r.end_time')->where('r.id','>',$this->cursor($r))->orderBy('r.id')->limit(51)
            ->get(['r.id','r.exam_id','e.name as exam_name','r.percent','r.result','r.end_time']);
        $more=$rows->count()>50;$rows=$rows->take(50)->values();
        return $this->reply($tenant,['learner_id'=>$learner,'items'=>$rows,'next'=>$more?$rows->last()->id:null]);
    }
}
