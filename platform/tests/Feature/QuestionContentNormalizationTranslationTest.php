<?php

namespace Tests\Feature;

use App\Models\Diff;
use App\Models\Language;
use App\Models\Organization;
use App\Models\Passage;
use App\Models\PassageLang;
use App\Models\Qtype;
use App\Models\Question;
use App\Models\QuestionLang;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QuestionContentNormalizationTranslationTest extends TestCase
{
    use RefreshDatabase;

    public function test_related_question_and_passage_translations_remain_tenant_scoped(): void
    {
        $tenantA = Organization::create(['name' => 'Tenant A', 'slug' => 'math-a', 'domain' => 'math-a.test', 'status' => 1]);
        $tenantB = Organization::create(['name' => 'Tenant B', 'slug' => 'math-b', 'domain' => 'math-b.test', 'status' => 1]);
        $qtype = Qtype::create(['question_type' => 'Multiple Choice', 'type' => 'M']);
        $difficulty = Diff::create(['diff_level' => 'Easy', 'type' => 'E']);
        $subjectA = Subject::create(['organization_id' => $tenantA->id, 'subject_name' => 'Physics A']);
        $subjectB = Subject::create(['organization_id' => $tenantB->id, 'subject_name' => 'Physics B']);
        $languageA = Language::create(['organization_id' => $tenantA->id, 'name' => 'Math Hindi A', 'is_enabled' => true]);
        $languageB = Language::create(['organization_id' => $tenantB->id, 'name' => 'Math Hindi B', 'is_enabled' => true]);
        $passageA = Passage::create(['organization_id' => $tenantA->id, 'name' => 'Math Passage A']);
        $passageB = Passage::create(['organization_id' => $tenantB->id, 'name' => 'Math Passage B']);
        $mathml = '<p><span class="mathjax-mathml"><math><msup><mi>x</mi><mn>2</mn></msup></math></span></p>';
        $questionA = Question::forceCreate([
            'organization_id' => $tenantA->id, 'qtype_id' => $qtype->id, 'subject_id' => $subjectA->id,
            'diff_id' => $difficulty->id, 'passage_id' => $passageA->id, 'question' => $mathml,
        ]);
        $questionB = Question::forceCreate([
            'organization_id' => $tenantB->id, 'qtype_id' => $qtype->id, 'subject_id' => $subjectB->id,
            'diff_id' => $difficulty->id, 'passage_id' => $passageB->id, 'question' => $mathml,
        ]);
        $questionLangA = QuestionLang::create(['question_id' => $questionA->id, 'language_id' => $languageA->id, 'question' => $mathml]);
        $questionLangB = QuestionLang::create(['question_id' => $questionB->id, 'language_id' => $languageB->id, 'question' => $mathml]);
        $passageLangA = PassageLang::create(['passage_id' => $passageA->id, 'language_id' => $languageA->id, 'passage' => $mathml]);
        $passageLangB = PassageLang::create(['passage_id' => $passageB->id, 'language_id' => $languageB->id, 'passage' => $mathml]);

        try {
            $this->assertSame(0, Artisan::call('questions:normalize-content', [
                '--organization' => $tenantA->id,
                '--question' => [$questionA->id],
                '--apply' => true,
            ]));

            $this->assertStringContainsString('\\(x^{2}\\)', $questionLangA->fresh()->question);
            $this->assertStringContainsString('\\(x^{2}\\)', $passageLangA->fresh()->passage);
            $this->assertSame($mathml, $questionLangB->fresh()->question);
            $this->assertSame($mathml, $passageLangB->fresh()->passage);
            $this->assertSame(3, DB::table('content_normalization_backups')->where('organization_id', $tenantA->id)->count());
            $this->assertSame(0, DB::table('content_normalization_backups')->where('organization_id', $tenantB->id)->count());
        } finally {
            if (preg_match('/Report:\s+([^\r\n]+)/', Artisan::output(), $match) && is_file(trim($match[1]))) {
                @unlink(trim($match[1]));
            }
        }
    }
}
