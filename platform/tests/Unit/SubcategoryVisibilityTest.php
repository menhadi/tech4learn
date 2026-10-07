<?php

namespace Tests\Unit;

use App\Models\Configuration;
use PHPUnit\Framework\TestCase;

class SubcategoryVisibilityTest extends TestCase
{
    public function test_configuration_casts_the_toggle_and_defaults_migration_to_enabled(): void
    {
        $configuration = new Configuration(['subcategories_enabled' => 0]);

        $this->assertFalse($configuration->subcategories_enabled);
        $this->assertStringContainsString(
            "boolean('subcategories_enabled')->default(true)",
            $this->source('database/migrations/2026_07_30_000001_add_subcategories_enabled_to_configurations_table.php')
        );
    }

    public function test_admin_setting_and_direct_route_guard_are_present(): void
    {
        $settings = $this->source('resources/views/configurations/website.blade.php');
        $controller = $this->source('app/Http/Controllers/CategoryController.php');

        $this->assertStringContainsString('name="subcategories_enabled"', $settings);
        $this->assertStringContainsString('ensureSubcategoriesEnabled', $controller);
        $this->assertStringContainsString('if(!subcategories_enabled())abort(404)', $controller);
    }

    public function test_primary_admin_student_and_public_surfaces_follow_the_shared_flag(): void
    {
        foreach ([
            'resources/views/exams/index.blade.php',
            'resources/views/questions/index.blade.php',
            'resources/views/students/practice_builder/index.blade.php',
            'resources/views/website/courses.blade.php',
            'resources/views/website/navbar.blade.php',
            'resources/views/website/dashboard.blade.php',
        ] as $path) {
            $this->assertStringContainsString('data-subcategory-ui', $this->source($path), $path);
        }

        $websiteController = $this->source('app/Http/Controllers/WebsiteController.php');
        $this->assertStringContainsString('if (! subcategories_enabled() || $categoryLevel2->isEmpty())', $websiteController);
        $this->assertStringContainsString("request->query->remove('subcategory')", $websiteController);
    }

    private function source(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path));
    }
}