<?php

namespace App\Http\Controllers;

use App\Models\QuestionLang;
use App\Models\Question;
use App\Models\Language;
use Illuminate\Http\Request;

class QuestionLangController extends Controller
{
    private function tenantId(): ?int
    {
        return class_exists(\App\Support\Tenant::class) ? \App\Support\Tenant::id() : null;
    }

    private function tenantQuestion($questionId): Question
    {
        return Question::query()
            ->when($this->tenantId(), function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            })
            ->findOrFail($questionId);
    }

    public function create($questionId)
    {
        $question = $this->tenantQuestion($questionId);
        $languages = Language::enabledForOrganization($this->tenantId())->orderBy('name')->get();
        return view('questions_langs.create', compact('question', 'languages'));
    }

    public function store(Request $request, $questionId)
    {
        $question = $this->tenantQuestion($questionId);

        $request->validate([
            'language_id' => 'required|integer|exists:languages,id',
            'question' => 'required|string',
            'option1' => 'nullable|string',
            'option2' => 'nullable|string',
            'option3' => 'nullable|string',
            'option4' => 'nullable|string',
            'option5' => 'nullable|string',
            'option6' => 'nullable|string',
            'hint' => 'nullable|string',
            'explanation' => 'nullable|string',
            'fill_blank' => 'nullable|string',
        ]);

        Language::enabledForOrganization($this->tenantId())->findOrFail($request->language_id);

        QuestionLang::create([
            'question_id' => $question->id,
            'language_id' => $request->language_id,
            'question' => $request->question,
            'option1' => $request->option1,
            'option2' => $request->option2,
            'option3' => $request->option3,
            'option4' => $request->option4,
            'option5' => $request->option5,
            'option6' => $request->option6,
            'hint' => $request->hint,
            'explanation' => $request->explanation,
            'fill_blank' => $request->fill_blank,
        ]);

        return redirect()->route('questions.index')->with('success', 'Question language added successfully.');
    }

    public function check($questionId, $languageId)
    {
        $question = $this->tenantQuestion($questionId);

        $questionLang = QuestionLang::where('question_id', $question->id)
            ->where('language_id', $languageId)
            ->first();

        if ($questionLang) {
            return response()->json(['exists' => true, 'data' => $questionLang]);
        } else {
            return response()->json(['exists' => false]);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'language_id' => 'required|integer|exists:languages,id',
            'question' => 'required|string',
            'option1' => 'nullable|string',
            'option2' => 'nullable|string',
            'option3' => 'nullable|string',
            'option4' => 'nullable|string',
            'option5' => 'nullable|string',
            'option6' => 'nullable|string',
            'hint' => 'nullable|string',
            'explanation' => 'nullable|string',
            'fill_blank' => 'nullable|string',
        ]);

        Language::enabledForOrganization($this->tenantId())->findOrFail($request->language_id);

        $questionLang = QuestionLang::query()
            ->whereHas('question', function ($q) {
                $q->when($this->tenantId(), function ($query, $tenantId) {
                    $query->where('organization_id', $tenantId);
                });
            })
            ->findOrFail($id);
        $questionLang->update([
            'language_id' => $request->language_id,
            'question' => $request->question,
            'option1' => $request->option1,
            'option2' => $request->option2,
            'option3' => $request->option3,
            'option4' => $request->option4,
            'option5' => $request->option5,
            'option6' => $request->option6,
            'hint' => $request->hint,
            'explanation' => $request->explanation,
            'fill_blank' => $request->fill_blank,
        ]);

        return redirect()->route('questions.index')->with('success', 'Question language updated successfully.');
    }
}
