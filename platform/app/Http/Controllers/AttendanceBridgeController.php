<?php

namespace App\Http\Controllers;

use App\Support\AttendanceBridge;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AttendanceBridgeController extends Controller
{
    public function context(Request $request, AttendanceBridge $bridge): JsonResponse
    {
        return response()->json($bridge->context($request))->header('Cache-Control', 'no-store');
    }

    public function records(Request $request, AttendanceBridge $bridge): JsonResponse
    {
        return response()->json($bridge->records($request))->header('Cache-Control', 'no-store');
    }
}
