<?php

namespace App\Services;

use App\Models\User;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,DB};

/** Internal canonical delivery only; section mappings require separate explicit review. */
class EnrolledStudentGroup
{
    /** Section identity must be fetched from the scoped canonical API, not request JSON. */
    public function map(Request $request,array $section,string $group,int $expectedVersion): array
    {
        $actor=Auth::user();abort_unless($actor instanceof User,403);
        $organization=Tenant::assertAccess(Tenant::current($request->getHost()),true);
        abort_unless(($section['nativeOrganisationId']??null)===(string)$organization->id,404);
        $id=$section['sectionId']??null;
        abort_unless(is_string($id) && preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/D',$id)
            && preg_match('/^[1-9][0-9]{0,14}$/D',$group) && $expectedVersion>=0,422);
        return DB::transaction(function() use($actor,$organization,$id,$group,$expectedVersion){
            $org=$organization->id;
            abort_unless(DB::table('organizations')->where('id',$org)->where('status','active')->lockForUpdate()->first(),403);
            $stored=DB::table('users')->where('id',$actor->id)->lockForUpdate()->first();
            abort_unless($stored && $stored->status==='Active' && !(bool)$stored->deleted,403);
            abort_unless(DB::table('organization_users')->where('organization_id',$org)->where('user_id',$actor->id)
                ->where('status',1)->whereIn('role',['owner','admin'])->lockForUpdate()->first(),403);
            abort_unless(DB::table('groups')->where('id',$group)->where('organization_id',$org)->lockForUpdate()->first(),404);
            $map=DB::table('foundation_section_groups')->where('organization_id',$org)->where('section_id',$id)->lockForUpdate()->first();
            if ($map) {
                abort_unless((int)$map->version===$expectedVersion && $map->active,409,'Section mapping changed or was revoked; review it before editing.');
                if ((string)$map->group_id!==$group) {
                    DB::table('foundation_section_groups')->where('organization_id',$org)->where('section_id',$id)
                        ->update(['group_id'=>$group,'version'=>$map->version+1,'updated_at'=>now()]);
                    $map->version++;
                }
                return ['sectionId'=>$id,'nativeGroupId'=>$group,'version'=>(int)$map->version];
            }
            abort_unless($expectedVersion===0,409,'Section mapping changed; reload it.');
            DB::table('foundation_section_groups')->insert(['organization_id'=>$org,'section_id'=>$id,'group_id'=>$group,'created_at'=>now(),'updated_at'=>now()]);
            return ['sectionId'=>$id,'nativeGroupId'=>$group,'version'=>1];
        });
    }
    public function apply(Request $request,array $snapshot): array
    {
        $actor=Auth::user();abort_unless($actor instanceof User,403);
        $organization=Tenant::assertAccess(Tenant::current($request->getHost()),true);
        abort_unless(($snapshot['nativeOrganisationId']??null)===(string)$organization->id,404);
        foreach(['learnerId','groupId'] as $field)abort_unless(is_string($snapshot[$field]??null)
            && preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/D',$snapshot[$field]),422);
        abort_unless(is_int($snapshot['revision']??null) && $snapshot['revision']>0 && $snapshot['revision']<1000000000000000 && is_bool($snapshot['archived']??null),422);
        return DB::transaction(function() use($actor,$organization,$snapshot){
            $org=$organization->id;$learner=$snapshot['learnerId'];$section=$snapshot['groupId'];$revision=$snapshot['revision'];
            abort_unless(DB::table('organizations')->where('id',$org)->where('status','active')->lockForUpdate()->first(),403);
            $stored=DB::table('users')->where('id',$actor->id)->lockForUpdate()->first();
            abort_unless($stored && $stored->status==='Active' && !(bool)$stored->deleted,403);
            abort_unless(DB::table('organization_users')->where('organization_id',$org)->where('user_id',$actor->id)
                ->where('status',1)->whereIn('role',['owner','admin'])->lockForUpdate()->first(),403);
            $profile=DB::table('foundation_student_profiles')->where('organization_id',$org)->where('learner_id',$learner)->lockForUpdate()->first();
            abort_unless($profile && (int)$profile->revision===$revision,409,'Group delivery requires the current student profile.');
            $delivery=DB::table('foundation_student_group_deliveries')->where('organization_id',$org)->where('learner_id',$learner)->lockForUpdate()->first();
            abort_if($delivery && ((int)$delivery->student_id!==(int)$profile->student_id || (int)$delivery->revision>$revision),409);
            $owned=null;
            if ($delivery?->pivot_id) {
                $owned=DB::table('student_groups')->where('id',$delivery->pivot_id)->where('student_id',$profile->student_id)
                    ->where('group_id',$delivery->group_id)->lockForUpdate()->first();
                abort_unless($owned,409,'An enrolment-managed group membership changed; explicit review is required.');
            }
            $map=DB::table('foundation_section_groups')->where('organization_id',$org)->where('section_id',$section)->where('active',true)->lockForUpdate()->first();
            $target=$snapshot['archived']?null:($map?->group_id);
            if ($target)abort_unless(DB::table('groups')->where('id',$target)->where('organization_id',$org)->lockForUpdate()->first(),409);
            if ($owned && (int)$owned->group_id!==(int)$target) {
                DB::table('student_groups')->where('id',$owned->id)->where('student_id',$profile->student_id)->where('group_id',$owned->group_id)->delete();
                $owned=null;
            }
            $pivot=$owned?->id;
            if ($target && !$pivot) {
                // Existing manual membership remains manual; never adopt it or remove unrelated groups.
                $manual=DB::table('student_groups')->where('student_id',$profile->student_id)->where('group_id',$target)->exists();
                abort_if(!$manual && $delivery && !$delivery->pivot_id && (int)$delivery->group_id===(int)$target,
                    409,'A manual group membership changed; explicit review is required.');
                if (!$manual)
                    $pivot=DB::table('student_groups')->insertGetId(['student_id'=>$profile->student_id,'group_id'=>$target,'created_at'=>now(),'updated_at'=>now()]);
            }
            $values=['student_id'=>$profile->student_id,'section_id'=>$section,'group_id'=>$target,'pivot_id'=>$pivot,'revision'=>$revision,'updated_at'=>now()];
            if ($delivery)DB::table('foundation_student_group_deliveries')->where('organization_id',$org)->where('learner_id',$learner)->update($values);
            else DB::table('foundation_student_group_deliveries')->insert($values+['organization_id'=>$org,'learner_id'=>$learner,'created_at'=>now()]);
            return ['assigned'=>(bool)$target,'mappingRequired'=>!$snapshot['archived'] && !$map];
        });
    }
}
