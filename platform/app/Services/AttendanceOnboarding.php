<?php
namespace App\Services;

use App\Models\{Organization, User};
use App\Support\AttendanceBridge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceOnboarding
{
    public function __construct(private AttendanceBridge $bridge) {}

    public function deliver(Request $request, User $actor, int $organizationId): bool
    {
        return DB::transaction(function () use ($request, $actor, $organizationId) {
            abort_unless(User::whereKey($actor->id)->where('status','Active')->where('deleted',false)->where('is_platform_admin',true)->exists(),403);
            $pending=DB::table('attendance_onboarding_requests')->where('organization_id',$organizationId)->lockForUpdate()->first();
            abort_unless($pending,404);
            $organization=Organization::findOrFail($organizationId);
            abort_unless($organization->status==='active' && !($organization->settings['is_primary_platform'] ?? false),403);
            if ($pending->status==='completed') return true;
            abort_unless($pending->status==='pending',409);
            DB::table('attendance_onboarding_requests')->where('id',$pending->id)->increment('attempts');
            try { $this->bridge->provisionCompanion($request,$actor,$organization); }
            catch (\Throwable $error) {
                // Retain retry evidence without recording credentials or remote error text.
                DB::table('attendance_onboarding_requests')->where('id',$pending->id)->update(['updated_at'=>now()]);
                return false;
            }
            DB::table('attendance_onboarding_requests')->where('id',$pending->id)->update([
                'status'=>'completed','completed_at'=>now(),'updated_at'=>now(),
            ]);
            return true;
        });
    }
}
