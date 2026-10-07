<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Exam;
use App\Models\Group;
use App\Models\Language;
use App\Models\Organization;
use App\Models\Package;
use App\Services\ExamLanguageService;
use App\Services\ExamScopeService;
use App\Services\ExamTranslationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExamLanguageAndPackageFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_multiple_packages_derive_the_union_of_groups_and_clear_direct_categories(): void
    {
        $tenant = $this->organization('Tenant A');
        $engineering = Group::create(['organization_id' => $tenant->id, 'group_name' => 'Engineering']);
        $jee = Group::create(['organization_id' => $tenant->id, 'group_name' => 'IIT-JEE']);
        [$category, $subcategory] = $this->hierarchy($tenant, [$engineering, $jee]);

        $engineeringPackage = $this->package($tenant, 'Engineering Pack', $category, $subcategory);
        $engineeringPackage->groups()->attach($engineering);
        $jeePackage = $this->package($tenant, 'JEE Pack', $category, $subcategory);
        $jeePackage->groups()->attach($jee);

        $scope = app(ExamScopeService::class)->resolve(
            $tenant->id,
            [$engineeringPackage->id, $jeePackage->id],
            [$engineering->id],
            $category->id,
            $subcategory->id,
        );

        $exam = $this->exam($tenant);
        app(ExamScopeService::class)->sync($exam, $scope);
        $exam->refresh();

        $this->assertEqualsCanonicalizing(
            [$engineering->id, $jee->id],
            $exam->groups()->pluck('groups.id')->all()
        );
        $this->assertEqualsCanonicalizing(
            [$engineeringPackage->id, $jeePackage->id],
            $exam->packages()->pluck('packages.id')->all()
        );
        $this->assertNull($exam->category_level_1);
        $this->assertNull($exam->category_level_2);
    }

    public function test_cross_tenant_package_cannot_be_used_to_expand_exam_scope(): void
    {
        $tenantA = $this->organization('Tenant A');
        $tenantB = $this->organization('Tenant B');
        $groupA = Group::create(['organization_id' => $tenantA->id, 'group_name' => 'A Group']);
        $groupB = Group::create(['organization_id' => $tenantB->id, 'group_name' => 'B Group']);
        [$categoryB, $subcategoryB] = $this->hierarchy($tenantB, [$groupB]);
        $packageB = $this->package($tenantB, 'Tenant B Pack', $categoryB, $subcategoryB);
        $packageB->groups()->attach($groupB);

        $this->expectException(ValidationException::class);
        app(ExamScopeService::class)->resolve(
            $tenantA->id,
            [$packageB->id],
            [$groupA->id],
            null,
            null,
        );
    }

    public function test_exam_language_selection_always_keeps_english_and_only_selected_languages(): void
    {
        $tenant = $this->organization('Tenant A');
        $english = Language::create([
            'organization_id' => $tenant->id, 'name' => 'English',
            'code' => 'en', 'is_enabled' => true,
        ]);
        $hindi = Language::create([
            'organization_id' => $tenant->id, 'name' => 'Hindi',
            'code' => 'hi', 'is_enabled' => true,
        ]);
        Language::create([
            'organization_id' => $tenant->id, 'name' => 'Disabled French',
            'code' => 'fr', 'is_enabled' => false,
        ]);

        $exam = $this->exam($tenant);
        app(ExamLanguageService::class)->sync($exam, [$hindi->id]);

        $this->assertEqualsCanonicalizing(
            [$english->id, $hindi->id],
            app(ExamLanguageService::class)->available($exam->fresh())->pluck('id')->all()
        );
        $this->assertTrue((bool) $exam->fresh()->multi_language);
        $this->assertSame('ready', $exam->fresh()->languages()->whereKey($english->id)->first()->pivot->translation_status);
        $this->assertSame(5, ExamTranslationService::BATCH_SIZE);
    }

    private function organization(string $name): Organization
    {
        return Organization::create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'domain' => str($name)->slug()->append('.test')->toString(),
            'status' => 'active',
        ]);
    }

    private function hierarchy(Organization $tenant, array $groups): array
    {
        $category = Category::create([
            'organization_id' => $tenant->id,
            'title' => 'Entrance Exams',
            'slug' => 'entrance-'.$tenant->id,
            'status' => 1,
        ]);
        $subcategory = Category::create([
            'organization_id' => $tenant->id,
            'parent_id' => $category->id,
            'title' => 'Mock Tests',
            'slug' => 'mock-'.$tenant->id,
            'status' => 1,
        ]);
        $category->groups()->attach(collect($groups)->pluck('id'));
        $subcategory->groups()->attach(collect($groups)->pluck('id'));

        return [$category, $subcategory];
    }

    private function package(
        Organization $tenant,
        string $name,
        Category $category,
        Category $subcategory
    ): Package {
        return Package::create([
            'organization_id' => $tenant->id,
            'name' => $name,
            'slug' => str($name)->slug()->append('-'.$tenant->id)->toString(),
            'description' => $name,
            'package_type' => 'free',
            'expiry_days' => 30,
            'status' => true,
            'category_level_1' => $category->id,
            'category_level_2' => $subcategory->id,
        ]);
    }

    private function exam(Organization $tenant): Exam
    {
        return Exam::create([
            'organization_id' => $tenant->id,
            'name' => 'Test Exam '.$tenant->id,
            'slug' => 'test-exam-'.$tenant->id,
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
