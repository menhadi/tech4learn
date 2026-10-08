<?php

namespace App\Services;

use App\Models\Student;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Internal signup transaction only; records intent, never enrols or activates a student. */
class StudentAdmission
{
    public function completeBinding(Request $request, string $admission): void
    {
        $organization=Tenant::assertAccess(Tenant::current($request->getHost()),true);
        $actor=\Illuminate\Support\Facades\Auth::user();abort_unless($actor instanceof \App\Models\User,403);
        DB::transaction(function() use($organization,$actor,$admission){
            abort_unless(DB::table('organizations')->where('id',$organization->id)->where('status','active')->lockForUpdate()->first(),403);
            $stored=DB::table('users')->where('id',$actor->id)->lockForUpdate()->first();
            abort_unless($stored && $stored->status==='Active' && !(bool)$stored->deleted,403);
            abort_unless(DB::table('organization_users')->where('organization_id',$organization->id)->where('user_id',$actor->id)
                ->where('status',1)->whereIn('role',['owner','admin'])->lockForUpdate()->first(),403);
            $origin=DB::table('foundation_student_admissions')->where('organization_id',$organization->id)->where('admission_id',$admission)->lockForUpdate()->first();
            abort_unless($origin && in_array($origin->state,['bound','linked'],true)
                && DB::table('foundation_student_profiles')->where('organization_id',$organization->id)->where('learner_id',$admission)->where('student_id',$origin->student_id)->exists(),409);
            DB::table('foundation_student_admissions')->where('organization_id',$organization->id)->where('admission_id',$admission)->update(['state'=>'linked','updated_at'=>now()]);
        });
    }

    public function pending(Request $request): array
    {
        $organization=Tenant::assertAccess(Tenant::current($request->getHost()),true);
        $actor=\Illuminate\Support\Facades\Auth::user();abort_unless($actor instanceof \App\Models\User,403);
        return DB::transaction(function() use($organization,$actor){
            abort_unless(DB::table('organizations')->where('id',$organization->id)->where('status','active')->lockForUpdate()->first(),403);
            $stored=DB::table('users')->where('id',$actor->id)->lockForUpdate()->first();
            abort_unless($stored && $stored->status==='Active' && !(bool)$stored->deleted,403);
            abort_unless(DB::table('organization_users')->where('organization_id',$organization->id)->where('user_id',$actor->id)
                ->where('status',1)->whereIn('role',['owner','admin'])->lockForUpdate()->first(),403);
            $rows=DB::table('foundation_student_admissions as a')->join('students as s',function($join){
                $join->on('s.id','=','a.student_id')->on('s.organization_id','=','a.organization_id');
            })->where('a.organization_id',$organization->id)->whereIn('a.state',['awaiting_review','bound'])
                ->orderBy('a.created_at')->orderBy('a.admission_id')->limit(51)->get(['a.admission_id','a.state','s.name']);
            return ['items'=>$rows->take(50)->map(fn($row)=>['admissionId'=>$row->admission_id,'state'=>$row->state,'name'=>$row->name])->all(),
                'hasMore'=>$rows->count()>50];
        });
    }

    /** Only a rechecked canonical binding snapshot may invoke this; never pass browser profile/IDs. */
    public function bindTrustedSnapshot(Request $request, array $snapshot): array
    {
        $admission=$snapshot['admissionId']??null;
        abort_unless(is_string($admission) && preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/D',$admission)
            && ($snapshot['learnerId']??null)===$admission && ($snapshot['state']??null)==='awaiting_native',422);
        $organization=Tenant::assertAccess(Tenant::current($request->getHost()),true);
        abort_unless(($snapshot['nativeOrganisationId']??null)===(string)$organization->id,404);
        $actor=\Illuminate\Support\Facades\Auth::user();abort_unless($actor instanceof \App\Models\User,403);
        $name=$snapshot['name']??null;$code=$snapshot['code']??null;$revision=$snapshot['revision']??null;
        abort_unless(is_string($name) && trim($name)!=='' && mb_strlen($name)<=120 && !preg_match('/[\x00-\x1f\x7f]/',$name)
            && is_string($code) && preg_match('/^[A-Z0-9][A-Z0-9_-]{0,39}$/D',$code)
            && is_int($revision) && $revision>0 && $revision<1000000000000000
            && ($snapshot['archived']??null)===false && ($snapshot['demo']??null)===false,422);
        return DB::transaction(function () use ($request,$snapshot,$organization,$actor,$admission,$name,$code,$revision) {
            abort_unless(DB::table('organizations')->where('id',$organization->id)->where('status','active')->lockForUpdate()->first(),403);
            $stored=DB::table('users')->where('id',$actor->id)->lockForUpdate()->first();
            abort_unless($stored && $stored->status==='Active' && !(bool)$stored->deleted,403);
            abort_unless(DB::table('organization_users')->where('organization_id',$organization->id)->where('user_id',$actor->id)
                ->where('status',1)->whereIn('role',['owner','admin'])->lockForUpdate()->first(),403);
            $origin=DB::table('foundation_student_admissions')->where('organization_id',$organization->id)->where('admission_id',$admission)->lockForUpdate()->first();
            abort_unless($origin && (string)$origin->student_id===($snapshot['nativeStudentId']??null)
                && (int)$origin->version===($snapshot['originVersion']??null),409,'Admission origin changed or is unavailable.');
            $profile=DB::table('foundation_student_profiles')->where('organization_id',$organization->id)->where('learner_id',$admission)->lockForUpdate()->first();
            if ($origin->state==='bound') {
                abort_unless($profile && (int)$profile->student_id===(int)$origin->student_id,409);
                return app(EnrolledStudentProfile::class)->apply($request,$snapshot);
            }
            abort_unless($origin->state==='awaiting_review' && !$profile,409);
            $source=$this->reviewSnapshot($request,$admission);
            abort_unless(hash_equals(hash('sha256',json_encode($source,JSON_THROW_ON_ERROR)),(string)($snapshot['originFingerprint']??'')),409,'Admission profile changed; review it again.');
            abort_if(DB::table('foundation_student_profiles')->where('student_id',$origin->student_id)->exists()
                || DB::table('students')->where('organization_id',$organization->id)->where('enroll',$code)->where('id','<>',$origin->student_id)->exists(),409);
            $fields=['name'=>$name,'enroll'=>$code,'archived'=>false,'is_demo'=>false];
            DB::table('students')->where('organization_id',$organization->id)->where('id',$origin->student_id)
                ->update(['name'=>$name,'enroll'=>$code,'is_demo'=>false,'updated_at'=>now()]);
            DB::table('foundation_student_profiles')->insert(['organization_id'=>$organization->id,'learner_id'=>$admission,'student_id'=>$origin->student_id,
                'revision'=>$revision,'fingerprint'=>hash('sha256',json_encode($fields,JSON_THROW_ON_ERROR)),'created_at'=>now(),'updated_at'=>now()]);
            DB::table('foundation_student_admissions')->where('organization_id',$organization->id)->where('admission_id',$admission)
                ->update(['state'=>'bound','updated_at'=>now()]);
            return ['nativeStudentId'=>(string)$origin->student_id,'learnerId'=>$admission,'revision'=>$revision];
        });
    }

    public function reviewSnapshot(Request $request, string $admission): array
    {
        abort_unless(preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/D',$admission),404);
        $organization=Tenant::assertAccess(Tenant::current($request->getHost()),true);
        $actor=\Illuminate\Support\Facades\Auth::user();
        abort_unless($actor instanceof \App\Models\User,403);
        return DB::transaction(function () use ($organization,$actor,$admission) {
            abort_unless(DB::table('organizations')->where('id',$organization->id)->where('status','active')->lockForUpdate()->first(),403);
            $stored=DB::table('users')->where('id',$actor->id)->lockForUpdate()->first();
            abort_unless($stored && $stored->status==='Active' && !(bool)$stored->deleted,403);
            abort_unless(DB::table('organization_users')->where('organization_id',$organization->id)->where('user_id',$actor->id)
                ->where('status',1)->whereIn('role',['owner','admin'])->lockForUpdate()->first(),403);
            $row=DB::table('foundation_student_admissions')->where('organization_id',$organization->id)
                ->where('admission_id',$admission)->lockForUpdate()->first();
            abort_unless($row && $row->state==='awaiting_review',404);
            $student=DB::table('students')->where('organization_id',$organization->id)->where('id',$row->student_id)->first();
            abort_unless($student,409);
            // No credentials, contact details or guest-selected group becomes section authority.
            return ['admissionId'=>$row->admission_id,'nativeOrganisationId'=>(string)$organization->id,
                'nativeStudentId'=>(string)$student->id,'version'=>(int)$row->version,
                'name'=>$student->name,'enroll'=>$student->enroll];
        });
    }

    public function recordNewSignup(Request $request, Student $student): string
    {
        abort_unless(DB::transactionLevel()>0 && $student->wasRecentlyCreated,409,
            'Admissions must be recorded with a new signup transaction.');
        $organization=Tenant::assertAccess(Tenant::current($request->getHost()),true);
        abort_unless(DB::table('organizations')->where('id',$organization->id)->where('status','active')->lockForUpdate()->first(),403);
        Tenant::assertAccess($organization,true);
        abort_unless(DB::table('students')->where('id',$student->getKey())->where('organization_id',$organization->id)->exists(),404);
        abort_if(DB::table('foundation_student_profiles')->where('organization_id',$organization->id)->where('student_id',$student->getKey())->exists(),409);
        $current=DB::table('foundation_student_admissions')->where('organization_id',$organization->id)
            ->where('student_id',$student->getKey())->lockForUpdate()->first();
        if ($current) return $current->admission_id;
        $id=(string)Str::uuid();
        DB::table('foundation_student_admissions')->insert(['organization_id'=>$organization->id,'admission_id'=>$id,
            'student_id'=>$student->getKey(),'state'=>'awaiting_review','version'=>1,'created_at'=>now(),'updated_at'=>now()]);
        return $id;
    }
}
