<?php

namespace App\Http\Controllers;

use App\Models\SourceQuestionAdapterProfile;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SourceQuestionAdapterController extends Controller
{
    public function edit(SourceQuestionAdapterProfile $adapter)
    {
        $this->authorizeTenant($adapter);
        return view('source-question-import.adapter', compact('adapter'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        SourceQuestionAdapterProfile::create($this->attributes($data) + ['organization_id' => Tenant::id(), 'version' => 1]);
        return redirect()->route('source-question-import.index')->with('success', 'Source adapter created. It is now available in the import dropdown.');
    }

    public function update(Request $request, SourceQuestionAdapterProfile $adapter)
    {
        $this->authorizeTenant($adapter);
        $data = $this->validated($request, $adapter);
        $adapter->update($this->attributes($data) + ['version' => $adapter->version + 1]);
        return redirect()->route('source-question-import.index')->with('success', 'Source adapter updated. Future imports and audits will use version '.$adapter->fresh()->version.'.');
    }

    public function destroy(SourceQuestionAdapterProfile $adapter)
    {
        $this->authorizeTenant($adapter);
        $adapter->update(['enabled' => false]);
        return back()->with('success', 'Source adapter disabled. Existing audit history was preserved.');
    }

    private function validated(Request $request, ?SourceQuestionAdapterProfile $adapter = null): array
    {
        $tenantId = (int) Tenant::id();
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9][a-z0-9_-]*$/', Rule::notIn(['auto', 'examside']), Rule::unique('source_question_adapter_profiles', 'key')->where('organization_id', $tenantId)->ignore($adapter?->id)],
            'url_pattern' => ['required', 'string', 'max:500'],
            'container_selector' => ['nullable', 'string', 'max:1000'],
            'question_selector' => ['required', 'string', 'max:1000'],
            'options_selector' => ['nullable', 'string', 'max:1000'],
            'answer_selector' => ['nullable', 'string', 'max:1000'],
            'explanation_selector' => ['nullable', 'string', 'max:1000'],
            'marks_selector' => ['nullable', 'string', 'max:1000'],
            'negative_marks_selector' => ['nullable', 'string', 'max:1000'],
            'question_type_selector' => ['nullable', 'string', 'max:1000'],
            'group_selector' => ['nullable', 'string', 'max:1000'],
            'category_selector' => ['nullable', 'string', 'max:1000'],
            'subcategory_selector' => ['nullable', 'string', 'max:1000'],
            'package_selector' => ['nullable', 'string', 'max:1000'],
            'exam_selector' => ['nullable', 'string', 'max:1000'],
            'subject_selector' => ['nullable', 'string', 'max:1000'],
            'section_selector' => ['nullable', 'string', 'max:1000'],
            'topic_selector' => ['nullable', 'string', 'max:1000'],
            'subtopic_selector' => ['nullable', 'string', 'max:1000'],
            'difficulty_level_selector' => ['nullable', 'string', 'max:1000'],
            'language_selector' => ['nullable', 'string', 'max:1000'],
            'images_selector' => ['nullable', 'string', 'max:1000'],
            'enabled' => ['nullable', 'boolean'],
        ]);
    }

    private function attributes(array $data): array
    {
        return [
            'name' => $data['name'], 'key' => $data['key'], 'url_pattern' => $data['url_pattern'],
            'enabled' => (bool) ($data['enabled'] ?? true),
            'selectors' => [
                'container' => $data['container_selector'] ?? null, 'question' => $data['question_selector'],
                'options' => $data['options_selector'] ?? null, 'answer' => $data['answer_selector'] ?? null,
                'explanation' => $data['explanation_selector'] ?? null, 'marks' => $data['marks_selector'] ?? null,
                'negative_marks' => $data['negative_marks_selector'] ?? null, 'question_type' => $data['question_type_selector'] ?? null,
                'group' => $data['group_selector'] ?? null, 'category' => $data['category_selector'] ?? null,
                'subcategory' => $data['subcategory_selector'] ?? null, 'package' => $data['package_selector'] ?? null,
                'exam' => $data['exam_selector'] ?? null, 'subject' => $data['subject_selector'] ?? null,
                'section' => $data['section_selector'] ?? null, 'topic' => $data['topic_selector'] ?? null,
                'subtopic' => $data['subtopic_selector'] ?? null, 'difficulty_level' => $data['difficulty_level_selector'] ?? null,
                'language' => $data['language_selector'] ?? null, 'images' => $data['images_selector'] ?? null,
            ],
        ];
    }

    private function authorizeTenant(SourceQuestionAdapterProfile $adapter): void
    {
        abort_unless((int) $adapter->organization_id === (int) Tenant::id(), 404);
    }
}
