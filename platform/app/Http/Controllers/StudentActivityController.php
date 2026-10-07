<?php

namespace App\Http\Controllers;

use App\Services\StudentActivityTracker;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StudentActivityController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_name' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'exam_id' => ['nullable', 'integer'],
            'package_id' => ['nullable', 'integer'],
            'exam_result_id' => ['nullable', 'integer'],
            'source' => ['nullable', 'string', 'max:40'],
            'page_url' => ['nullable', 'url', 'max:2000'],
            'metadata' => ['nullable', 'array', 'max:30'],
        ]);

        $guestId = auth('student')->check()
            ? null
            : (session('guest_id') ?: $request->cookie('guest_id') ?: (string) Str::uuid());
        if ($guestId) {
            session(['guest_id' => $guestId]);
        }

        StudentActivityTracker::track($validated['event_name'], [
            'guest_id' => $guestId,
            'exam_id' => $validated['exam_id'] ?? null,
            'package_id' => $validated['package_id'] ?? null,
            'exam_result_id' => $validated['exam_result_id'] ?? null,
            'source' => $validated['source'] ?? (auth('student')->check() ? 'student' : 'web'),
            'metadata' => array_filter(array_merge(
                (array) ($validated['metadata'] ?? []),
                ['page_url' => $validated['page_url'] ?? null]
            ), fn ($value) => $value !== null && $value !== ''),
        ], $request);

        return response()->noContent();
    }
}
