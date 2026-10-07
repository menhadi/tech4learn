<?php

namespace App\Http\Controllers;

use App\Services\ExamPdfPublicationService;
use App\Support\Tenant;
use Illuminate\Http\Request;

class ExamPdfPublicationController extends Controller
{
    public function store(Request $request, ExamPdfPublicationService $publisher)
    {
        abort_unless(user_can_route_action('exams.edit', 'edit'), 403);
        $data = $request->validate([
            'exam_ids' => ['required', 'array', 'min:1', 'max:500'],
            'exam_ids.*' => ['required', 'integer'],
            'category_mode' => ['required', 'in:keep,assign,create'],
            'category_id' => ['nullable', 'integer', 'required_if:category_mode,assign'],
            'new_category' => ['nullable', 'string', 'max:200', 'required_if:category_mode,create'],
            'group_id' => ['nullable', 'integer'],
        ]);
        if ($data['category_mode'] === 'create') abort_unless(user_can_route_action('category.create', 'add'), 403);
        $count = $publisher->publish((int) Tenant::id(), $data['exam_ids'], $data);
        return back()->with('success', "$count paper(s) published. Papers without active questions offer PDF download only. Extraction remains a separate action.");
    }
}
