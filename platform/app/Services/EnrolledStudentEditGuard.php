<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Schema};

/** Protect canonical identity without changing native login/contact administration. */
class EnrolledStudentEditGuard
{
    public function withIdentityLock(Request $request, Student $student, callable $write)
    {
        $organization=\App\Support\Tenant::assertAccess(\App\Support\Tenant::current($request->getHost()),true);
        return DB::transaction(function () use ($request,$student,$write,$organization) {
            abort_unless(DB::table('organizations')->where('id',$organization->id)->where('status','active')->lockForUpdate()->first(),403);
            \App\Support\Tenant::assertAccess($organization,true);
            abort_unless(DB::table('students')->where('id',$student->getKey())->where('organization_id',$organization->id)->exists(),404);
            $this->assertIdentityUnchanged($request,$student);
            return $write();
        });
    }

    public function assertUnmanaged(Student $student): void
    {
        if (!Schema::hasTable('foundation_student_profiles')) return;
        $stored=DB::table('students')->where('id',$student->getKey())->first();
        abort_unless($stored,404);
        abort_if(DB::table('foundation_student_profiles')->where('organization_id',$stored->organization_id)
            ->where('student_id',$stored->id)->exists(),409,
            'Manage this student and section through Enrolment; native removal or bulk reassignment is unavailable.');
    }

    public function assertGroupsUnchanged(Request $request, Student $student): void
    {
        if (!Schema::hasTable('foundation_student_profiles')) return;
        $stored=DB::table('students')->where('id',$student->getKey())->first();
        abort_unless($stored,404);
        if (!DB::table('foundation_student_profiles')->where('organization_id',$stored->organization_id)
            ->where('student_id',$stored->id)->exists()) return;
        $current=DB::table('student_groups')->where('student_id',$stored->id)->pluck('group_id')->map(fn($id)=>(string)$id)->all();
        $submitted=$request->input('group_ids');
        abort_unless(is_array($submitted),422);
        $submitted=array_map('strval',$submitted);sort($current);sort($submitted);
        abort_unless($current===$submitted,409,'Change managed exam enrolment through the shared section mapping.');
    }

    public function assertIdentityUnchanged(Request $request, Student $student): void
    {
        if (!Schema::hasTable('foundation_student_profiles')) return;
        $stored=DB::table('students')->where('id',$student->getKey())->first();
        abort_unless($stored,404);
        $managed=DB::table('foundation_student_profiles')->where('organization_id',$stored->organization_id)
            ->where('student_id',$stored->id)->exists();
        if (!$managed) return;
        foreach (['name','enroll','organization_id','is_demo'] as $field) {
            if ($request->exists($field)) abort_unless((string)$request->input($field)===(string)$stored->$field,
                409,'Change student identity in Enrolment, then deliver the updated exam profile.');
        }
    }
}
