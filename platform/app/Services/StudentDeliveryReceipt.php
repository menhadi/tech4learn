<?php

namespace App\Services;

use App\Models\User;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,DB};

/** ID-only installation proof. No browser input chooses a key or native student ID. */
class StudentDeliveryReceipt
{
    public function assertConfigured(): void { $this->signingKey(); }
    private function signingKey(): array
    {
        $path=storage_path('app/private/student-delivery-signing.pem');
        abort_unless(is_file($path) && !is_link($path) && filesize($path)<=8192,503,'Native student delivery signing is not configured.');
        if (PHP_OS_FAMILY!=='Windows')abort_unless((fileperms($path)&0077)===0,503,'Native student signing key permissions require repair.');
        $key=@openssl_pkey_get_private(file_get_contents($path));
        $details=$key?openssl_pkey_get_details($key):false;
        abort_unless($details && $details['type']===OPENSSL_KEYTYPE_RSA && $details['bits']>=2048,503,'Native student delivery signing is not configured.');
        return [$key,$details];
    }
    public function issue(Request $request, string $learner, int $revision): array
    {
        $actor=Auth::user();abort_unless($actor instanceof User,403);
        $organization=Tenant::assertAccess(Tenant::current($request->getHost()),true);
        abort_unless(preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/D',$learner)
            && $revision>0 && $revision<1000000000000000,422);
        return DB::transaction(function() use($actor,$organization,$learner,$revision) {
            abort_unless(DB::table('organizations')->where('id',$organization->id)->where('status','active')->lockForUpdate()->first(),403);
            $stored=DB::table('users')->where('id',$actor->id)->lockForUpdate()->first();
            abort_unless($stored && $stored->status==='Active' && !(bool)$stored->deleted,403);
            abort_unless(DB::table('organization_users')->where('organization_id',$organization->id)->where('user_id',$actor->id)
                ->where('status',1)->whereIn('role',['owner','admin'])->lockForUpdate()->first(),403);
            $link=DB::table('foundation_student_profiles')->where('organization_id',$organization->id)->where('learner_id',$learner)->lockForUpdate()->first();
            abort_unless($link && (int)$link->revision===$revision
                && DB::table('students')->where('id',$link->student_id)->where('organization_id',$organization->id)->exists(),409,'Student proof requires the current stored profile.');
            [$key,$details]=$this->signingKey();
            $der=base64_decode(preg_replace('/-----[^-]+-----|\s/','',$details['key']),true);
            abort_unless(is_string($der),503);
            $receipt=json_encode(['issuer'=>'tech4learn-native-student-v1','nativeOrganisationId'=>(string)$organization->id,
                'nativeUserId'=>(string)$actor->id,'nativeStudentId'=>(string)$link->student_id,'learnerId'=>$learner,
                'revision'=>$revision,'issuedAt'=>time()],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
            abort_unless(openssl_sign($receipt,$signature,$key,OPENSSL_ALGO_SHA256),503,'Native student delivery signing failed.');
            $encode=fn(string $bytes)=>rtrim(strtr(base64_encode($bytes),'+/','-_'),'=');
            return ['keyId'=>hash('sha256',$der),'receipt'=>$encode($receipt),'signature'=>$encode($signature)];
        });
    }
}
