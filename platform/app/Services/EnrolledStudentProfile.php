<?php

namespace App\Services;

use App\Models\User;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,DB,Hash};
use Illuminate\Support\Str;

/** Internal consumer only: snapshot must come from the authorised canonical bridge, never request JSON.
 * No route exposes this service. Remote acknowledgement/proof and section assignment remain separate.
 */
class EnrolledStudentProfile
{
    public function apply(Request $request, array $snapshot): array
    {
        $actor=Auth::user();abort_unless($actor instanceof User,403);
        $organization=Tenant::assertAccess(Tenant::current($request->getHost()),true);
        abort_unless(($snapshot['nativeOrganisationId']??null)===(string)$organization->id,404);
        $learner=$snapshot['learnerId']??null;$revision=$snapshot['revision']??null;
        abort_unless(is_string($learner) && preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/D',$learner)
            && is_int($revision) && $revision>0 && $revision<1000000000000000,422);
        $name=$snapshot['name']??null;$code=$snapshot['code']??null;
        abort_unless(is_string($name) && trim($name)!=='' && mb_strlen($name)<=120 && !preg_match('/[\x00-\x1f\x7f]/',$name)
            && is_string($code) && preg_match('/^[A-Z0-9][A-Z0-9_-]{0,39}$/D',$code)
            && is_bool($snapshot['archived']??null) && is_bool($snapshot['demo']??null),422);
        $fields=['name'=>$name,'enroll'=>$code,'archived'=>$snapshot['archived'],'is_demo'=>$snapshot['demo']];
        $fingerprint=hash('sha256',json_encode($fields,JSON_THROW_ON_ERROR));
        return DB::transaction(function() use($actor,$organization,$learner,$revision,$fields,$fingerprint) {
            abort_unless(DB::table('organizations')->where('id',$organization->id)->where('status','active')->lockForUpdate()->first(),403);
            $stored=DB::table('users')->where('id',$actor->id)->lockForUpdate()->first();
            abort_unless($stored && $stored->status==='Active' && !(bool)$stored->deleted,403);
            abort_unless(DB::table('organization_users')->where('organization_id',$organization->id)->where('user_id',$actor->id)
                ->where('status',1)->whereIn('role',['owner','admin'])->lockForUpdate()->first(),403);
            $link=DB::table('foundation_student_profiles')->where('organization_id',$organization->id)->where('learner_id',$learner)->lockForUpdate()->first();
            abort_if(DB::table('students')->where('organization_id',$organization->id)->where('enroll',$fields['enroll'])
                ->when($link,fn($query)=>$query->where('id','<>',$link->student_id))->exists(),409,'Student code needs explicit identity review.');
            if ($link) {
                abort_unless($revision>=(int)$link->revision,409,'Student delivery is stale.');
                abort_unless(DB::table('students')->where('id',$link->student_id)->where('organization_id',$organization->id)->exists(),409);
                if ($revision===(int)$link->revision) {
                    abort_unless(hash_equals($link->fingerprint,$fingerprint),409,'Student delivery changed; reload it.');
                    return ['nativeStudentId'=>(string)$link->student_id,'learnerId'=>$learner,'revision'=>$revision];
                }
                $id=$link->student_id;
                $update=['name'=>$fields['name'],'enroll'=>$fields['enroll'],'is_demo'=>$fields['is_demo'],'updated_at'=>now()];
                if ($fields['archived'])$update['status']='Suspend';
                DB::table('students')->where('id',$id)->where('organization_id',$organization->id)->update($update);
                DB::table('foundation_student_profiles')->where('organization_id',$organization->id)->where('learner_id',$learner)
                    ->update(['revision'=>$revision,'fingerprint'=>$fingerprint,'updated_at'=>now()]);
            } else {
                // A new profile has no working login or invented contacts, and sends no lifecycle messages.
                $id=DB::table('students')->insertGetId(['organization_id'=>$organization->id,'name'=>$fields['name'],
                    'email'=>null,'phone'=>null,'address'=>'','enroll'=>$fields['enroll'],'is_demo'=>$fields['is_demo'],
                    'password'=>Hash::make(Str::random(64)),'status'=>'Suspend','created_at'=>now(),'updated_at'=>now()]);
                DB::table('foundation_student_profiles')->insert(['organization_id'=>$organization->id,'learner_id'=>$learner,
                    'student_id'=>$id,'revision'=>$revision,'fingerprint'=>$fingerprint,'created_at'=>now(),'updated_at'=>now()]);
            }
            return ['nativeStudentId'=>(string)$id,'learnerId'=>$learner,'revision'=>$revision];
        });
    }
}
