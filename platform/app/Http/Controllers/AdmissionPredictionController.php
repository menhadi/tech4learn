<?php

namespace App\Http\Controllers;

use App\Services\AdmissionPredictionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdmissionPredictionController extends Controller
{
    public function __invoke(Request $request, AdmissionPredictionService $predictor): JsonResponse
    {
        $data = $request->validate([
            'exam_code' => ['required', 'string', Rule::exists('admission_exam_definitions', 'code')->where('enabled', true)],
            'marks' => ['nullable', 'numeric', 'min:0'],
            'percentile' => ['nullable', 'numeric', 'between:0,100'],
            'rank' => ['nullable', 'integer', 'min:1'],
            'exam_year' => ['nullable', 'integer', 'between:2000,2100'],
            'session' => ['nullable', 'string', 'max:40'],
            'shift' => ['nullable', 'string', 'max:80'],
            'category' => ['nullable', 'string', 'max:60'],
            'quota' => ['nullable', 'string', 'max:100'],
            'gender_pool' => ['nullable', 'string', 'max:100'],
            'round' => ['nullable', 'string', 'max:60'],
            'domicile_state' => ['nullable', 'string', 'max:100'],
            'institution' => ['nullable', 'string', 'max:255'],
            'program' => ['nullable', 'string', 'max:255'],
        ]);
        if (! collect(['marks', 'percentile', 'rank'])->contains(fn ($field) => array_key_exists($field, $data))) {
            return response()->json([
                'message' => 'Provide marks, percentile, or an official rank.',
                'errors' => ['score' => ['One prediction input is required.']],
            ], 422);
        }

        return response()->json($predictor->predict($data['exam_code'], $data));
    }
}
