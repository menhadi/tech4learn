<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\Language;
use App\Models\Organization;
use App\Services\ExamLanguageService;
use App\Services\ExamPdfCacheService;
use App\Services\UiLanguageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultilingualPreferenceAndPdfCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_ui_languages_are_limited_to_enabled_installed_locales(): void
    {
        $organization = $this->organization();
        Language::create(['organization_id' => $organization->id, 'name' => 'English', 'code' => 'en', 'is_enabled' => true]);
        Language::create(['organization_id' => $organization->id, 'name' => 'Hindi', 'code' => 'hi', 'is_enabled' => true]);
        Language::create(['organization_id' => $organization->id, 'name' => 'French', 'code' => 'fr', 'is_enabled' => false]);
        Language::create(['organization_id' => $organization->id, 'name' => 'Vietnamese', 'code' => 'vi', 'is_enabled' => true]);

        $codes = app(UiLanguageService::class)->available($organization->id)->pluck('code');

        $this->assertTrue($codes->contains('en'));
        $this->assertTrue($codes->contains('hi'));
        $this->assertFalse($codes->contains('fr'));
        $this->assertFalse($codes->contains('vi'));
    }

    public function test_vietnamese_database_language_and_exam_assignment_are_removed(): void
    {
        $organization = $this->organization();
        $english = Language::create(['organization_id' => $organization->id, 'name' => 'English', 'code' => 'en', 'is_enabled' => true]);
        $vietnamese = Language::create(['organization_id' => $organization->id, 'name' => 'Vietnamese', 'code' => 'vi', 'is_enabled' => true]);
        $exam = $this->exam($organization);
        app(ExamLanguageService::class)->sync($exam, [$english->id, $vietnamese->id]);

        $migration = require database_path('migrations/2026_08_01_000001_remove_vietnamese_language.php');
        $migration->up();

        $this->assertDatabaseMissing('languages', ['id' => $vietnamese->id]);
        $this->assertDatabaseMissing('exam_languages', ['exam_id' => $exam->id, 'language_id' => $vietnamese->id]);
        $this->assertDatabaseHas('exam_languages', ['exam_id' => $exam->id, 'language_id' => $english->id]);
    }

    public function test_pdf_cache_fingerprint_changes_when_exam_content_changes(): void
    {
        $organization = $this->organization();
        $english = Language::create(['organization_id' => $organization->id, 'name' => 'English', 'code' => 'en', 'is_enabled' => true]);
        $exam = $this->exam($organization);
        app(ExamLanguageService::class)->sync($exam, [$english->id]);
        $cache = app(ExamPdfCacheService::class);

        $before = $cache->fingerprint($exam->fresh(), $english, null, false);
        $exam->forceFill(['name' => 'Updated exam'])->save();
        $after = $cache->fingerprint($exam->fresh(), $english, null, false);

        $this->assertNotSame($before, $after);
        $this->assertStringContainsString('questions', $cache->path($exam, $english, null, false, $after));
        $this->assertStringContainsString('solutions', $cache->path($exam, $english, null, true, $after));
    }

    private function organization(): Organization
    {
        return Organization::create([
            'name' => 'Multilingual Tenant',
            'slug' => 'multilingual-tenant',
            'domain' => 'multilingual.test',
            'status' => 'active',
        ]);
    }

    private function exam(Organization $organization): Exam
    {
        return Exam::create([
            'organization_id' => $organization->id,
            'name' => 'Language Exam',
            'slug' => 'language-exam',
            'passing_percentage' => 50,
            'duration' => 60,
            'attempt_count' => 1,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
            'mode' => 'Exam',
            'status' => 'Active',
        ]);
    }
}
