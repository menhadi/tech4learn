<?php

namespace App\Http\Controllers;

use App\Support\AttendanceBridge;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AttendanceBridgeController extends Controller
{
    public function enrolment(Request $request, AttendanceBridge $bridge)
    {
        $bridge->context($request,null,'enrolment');
        return $this->workspace();
    }
    public function enrolmentContext(Request $request, AttendanceBridge $bridge): JsonResponse
    {
        return response()->json($bridge->context($request,null,'enrolment')+[
            'studentDeliveryEnabled'=>(bool)config('attendance.student_delivery_enabled',false)
        ])->header('Cache-Control','no-store');
    }
    public function deliverStudent(Request $request, string $learner, AttendanceBridge $bridge): JsonResponse
    {
        // Only the canonical UUID in the route is used. Client profile/ID/key fields are rejected.
        abort_unless(count($request->except('_token'))===0,422,'Student delivery does not accept profile or identity fields.');
        return response()->json($bridge->deliverStudent($request,$learner))->header('Cache-Control','no-store');
    }
    public function studentDeliveryStatus(Request $request, string $learner, AttendanceBridge $bridge): JsonResponse
    {
        return response()->json($bridge->studentDeliveryStatus($request,$learner))->header('Cache-Control','no-store');
    }
    public function sectionMappingOptions(Request $request,string $section,AttendanceBridge $bridge): JsonResponse
    {
        return response()->json($bridge->sectionMappingOptions($request,$section))->header('Cache-Control','no-store');
    }
    public function mapSection(Request $request,string $section,AttendanceBridge $bridge): JsonResponse
    {
        $body=$request->except('_token');
        abort_unless(count($body)===2 && is_string($body['nativeGroupId']??null)
            && is_int($body['version']??null) && $body['version']>=0,422);
        return response()->json($bridge->mapSection($request,$section,$body['nativeGroupId'],$body['version']))->header('Cache-Control','no-store');
    }
    public function enrolmentGateway(Request $request, string $path, AttendanceBridge $bridge)
    {
        abort_if(preg_match('#/attendance(?:/|$)#',$path),404);
        return $bridge->gateway($request,$path,null,'enrolment');
    }
    public function workspace()
    {
        $organization=\App\Support\Tenant::assertAccess(\App\Support\Tenant::current(),true);
        if (($organization->settings['is_primary_platform'] ?? false)
            && \App\Support\VerifiedPlatformAccess::allowed(request(),\Illuminate\Support\Facades\Auth::user())) {
            return view('attendance.platform-setup');
        }
        $path=public_path('attendance-ui/manifest.json');
        $manifest=is_file($path)?json_decode(file_get_contents($path),true):[];
        $assets=$manifest['src/foundation-attendance.tsx']??null;
        abort_unless($assets && preg_match('#^assets/[A-Za-z0-9_.-]+\.js$#D',$assets['file']??''),503,'Attendance assets need to be built.');
        return response()->view('attendance.workspace',['attendanceAssets'=>$assets])->header('Cache-Control','no-store');
    }
    public function context(Request $request, AttendanceBridge $bridge): JsonResponse
    {
        return response()->json($bridge->context($request))->header('Cache-Control', 'no-store');
    }

    public function records(Request $request, AttendanceBridge $bridge): JsonResponse
    {
        return response()->json($bridge->records($request))->header('Cache-Control', 'no-store');
    }
    public function gateway(Request $request, string $path, AttendanceBridge $bridge)
    {
        return $bridge->gateway($request,$path);
    }
}
