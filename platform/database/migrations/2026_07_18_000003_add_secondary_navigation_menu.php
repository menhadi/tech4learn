<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('navigation_settings', 'secondary_enabled')) {
            Schema::table('navigation_settings', function (Blueprint $table) {
                $table->boolean('secondary_enabled')->default(true)->after('header_enabled');
            });
        }

        $organizationIds = collect();
        foreach (['navigation_settings', 'category', 'packages'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'organization_id')) {
                $organizationIds = $organizationIds->merge(DB::table($table)->whereNotNull('organization_id')->distinct()->pluck('organization_id'));
            }
        }

        foreach ($organizationIds->unique()->filter() as $organizationId) {
            $now = now();
            $existingSettings = DB::table('navigation_settings')->where('organization_id', $organizationId)->exists();
            if ($existingSettings) {
                DB::table('navigation_settings')->where('organization_id', $organizationId)->update(['secondary_enabled' => true, 'updated_at' => $now]);
            } else {
                DB::table('navigation_settings')->insert(['organization_id' => $organizationId, 'header_enabled' => false, 'secondary_enabled' => true, 'footer_enabled' => false, 'created_at' => $now, 'updated_at' => $now]);
            }

            $candidates = collect();
            if (Schema::hasTable('category')) {
                $categories = DB::table('category')->where('organization_id', $organizationId)->whereNull('parent_id')->where('status', 1)
                    ->orderByRaw('CASE WHEN display_order IS NULL OR display_order = 0 THEN 1 ELSE 0 END')->orderBy('display_order')->orderBy('title')->orderBy('id')->limit(5)->get(['id', 'title']);
                $candidates = $categories->map(fn ($category) => ['type' => 'category', 'id' => $category->id, 'label' => $this->plainLabel($category->title)]);
            }

            if ($candidates->count() < 5 && Schema::hasTable('packages')) {
                $packages = DB::table('packages')->where('organization_id', $organizationId)->where('status', 1)
                    ->orderByRaw('CASE WHEN display_order IS NULL OR display_order = 0 THEN 1 ELSE 0 END')->orderBy('display_order')->orderBy('name')->orderBy('id')->limit(5 - $candidates->count())->get(['id', 'name']);
                $candidates = $candidates->concat($packages->map(fn ($package) => ['type' => 'package', 'id' => $package->id, 'label' => $this->plainLabel($package->name)]));
            }

            foreach ($candidates->values() as $index => $candidate) {
                $key = ['organization_id' => $organizationId, 'location' => 'secondary', 'link_type' => $candidate['type'], 'reference_id' => $candidate['id']];
                if (! DB::table('navigation_items')->where($key)->exists()) {
                    DB::table('navigation_items')->insert($key + ['parent_id' => null, 'label' => $candidate['label'], 'target' => '_self', 'style' => 'link', 'desktop_visible' => true, 'mobile_visible' => true, 'is_active' => true, 'sort_order' => $index + 1, 'created_at' => $now, 'updated_at' => $now]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('navigation_settings', 'secondary_enabled')) {
            Schema::table('navigation_settings', function (Blueprint $table) {
                $table->dropColumn('secondary_enabled');
            });
        }
    }

    private function plainLabel($value): string
    {
        $decoded = is_string($value) ? json_decode($value, true) : null;
        if (is_array($decoded)) return (string) ($decoded['en'] ?? reset($decoded) ?: 'Featured');
        return (string) ($value ?: 'Featured');
    }
};