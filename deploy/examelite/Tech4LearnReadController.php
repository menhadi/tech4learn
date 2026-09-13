<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\Tenant;

/** Additive, read-only API. No existing exam controller is replaced. */
class Tech4LearnReadController extends Controller
{
    protected function configPath(): string { return '/etc/examelite/tech4learn-read.json'; }
    private function grant(Request $request): array
    {
        $org = (string) $request->header('X-Tech4Learn-Organisation');
        $token = (string) $request->bearerToken();
        abort_unless(preg_match('/^[a-f0-9-]{36}$/D', $org) && preg_match('/^[a-f0-9]{64}$/D', $token), 401);
        $path = $this->configPath();
        abort_unless(is_readable($path), 503, 'Connector is not configured.');
        try {
            $config = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            abort(503, 'Connector configuration is invalid.');
        }
        $grant = $config[$org] ?? null;
        abort_unless(is_array($grant) && ($grant['enabled'] ?? false) === true
            && is_string($grant['token_hash'] ?? null)
            && hash_equals($grant['token_hash'], hash('sha256', $token)), 401);
        abort_unless(is_int($grant['organization_id'] ?? null)
            && $grant['organization_id'] > 0
            && $grant['organization_id'] === (int) Tenant::hostId($request->getHost()), 403);
        abort_unless(is_array($grant['exam_ids'] ?? null) && count($grant['exam_ids']) <= 10000
            && is_array($grant['learners'] ?? null) && count($grant['learners']) <= 1000, 503, 'Connector mapping is invalid.');
        foreach ($grant['exam_ids'] as $id) {
            abort_unless(is_int($id) && $id > 0, 503, 'Connector mapping is invalid.');
        }
        return $grant;
    }

    private function cursor(Request $request): int
    {
        $value = (string) $request->query('after', '0');
        abort_unless(preg_match('/^\d{1,15}$/D', $value), 422);
        return (int) $value;
    }

    public function status(Request $request)
    {
        $g = $this->grant($request);
        return response()->json(['version' => 1, 'organization_id' => $g['organization_id'],
            'capabilities' => ['exams.read', 'results.read'],
            'linked_learners' => array_keys($g['learners'])])->header('Cache-Control', 'no-store');
    }

    public function exams(Request $request)
    {
        $g = $this->grant($request);
        $rows = DB::table('exams')->where('organization_id', $g['organization_id'])
            ->whereIn('id', $g['exam_ids'])->where('id', '>', $this->cursor($request))
            ->orderBy('id')->limit(51)->get(['id', 'name', 'duration', 'start_date', 'end_date']);
        $more = $rows->count() > 50;
        $rows = $rows->take(50)->values();
        return response()->json(['version' => 1, 'organization_id' => $g['organization_id'], 'items' => $rows,
            'next' => $more ? $rows->last()->id : null])->header('Cache-Control', 'no-store');
    }

    public function results(Request $request, string $learner)
    {
        $g = $this->grant($request);
        $student = $g['learners'][$learner] ?? null;
        abort_unless(is_int($student) && $student > 0, 404);
        abort_unless(DB::table('students')->where('organization_id', $g['organization_id'])->where('id', $student)->exists(), 404);
        $rows = DB::table('exam_results as r')->join('exams as e', function ($join) {
            $join->on('e.id', '=', 'r.exam_id')->on('e.organization_id', '=', 'r.organization_id');
        })->where('r.organization_id', $g['organization_id'])->where('r.student_id', $student)
            ->whereIn('r.exam_id', $g['exam_ids'])->whereNotNull('r.end_time')
            ->where('r.id', '>', $this->cursor($request))->orderBy('r.id')->limit(51)
            ->get(['r.id', 'r.exam_id', 'e.name as exam_name', 'r.percent', 'r.result', 'r.end_time']);
        $more = $rows->count() > 50;
        $rows = $rows->take(50)->values();
        return response()->json(['version' => 1, 'organization_id' => $g['organization_id'],
            'learner_id' => $learner, 'items' => $rows, 'next' => $more ? $rows->last()->id : null])
            ->header('Cache-Control', 'no-store');
    }
}
