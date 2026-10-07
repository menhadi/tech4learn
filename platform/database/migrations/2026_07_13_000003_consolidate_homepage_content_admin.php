<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pages')) {
            $source = DB::table('pages')->where('action_name', 'features.index')->first();

            if ($source) {
                DB::table('pages')->where('id', $source->id)->update([
                    'page_name' => 'Homepage Content',
                    'action_name' => 'homepage-content.index',
                    'icon' => 'ri-layout-masonry-line',
                    'ordering' => 21,
                    'updated_at' => now(),
                ]);
            } else {
                $id = DB::table('pages')->insertGetId([
                    'page_name' => 'Homepage Content',
                    'action_name' => 'homepage-content.index',
                    'icon' => 'ri-layout-masonry-line',
                    'parent_id' => null,
                    'ordering' => 21,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $settings = DB::table('pages')->where('action_name', 'configurations.website')->first();
                if ($settings && Schema::hasTable('page_rights')) {
                    foreach (DB::table('page_rights')->where('page_id', $settings->id)->get() as $right) {
                        $row = (array) $right;
                        unset($row['id']);
                        $row['page_id'] = $id;
                        $row['created_at'] = now();
                        $row['updated_at'] = now();
                        DB::table('page_rights')->updateOrInsert(
                            ['page_id' => $id, 'ugroup_id' => $right->ugroup_id],
                            $row
                        );
                    }
                }
            }

            $obsolete = DB::table('pages')->whereIn('action_name', [
                'counters.index', 'testimonial.index', 'aboutus.index', 'heroslider.index',
            ])->pluck('id');

            if ($obsolete->isNotEmpty() && Schema::hasTable('page_rights')) {
                $homepagePageId = DB::table('pages')->where('action_name', 'homepage-content.index')->value('id');
                foreach (DB::table('page_rights')->whereIn('page_id', $obsolete)->get() as $right) {
                    $existing = DB::table('page_rights')
                        ->where('page_id', $homepagePageId)
                        ->where('ugroup_id', $right->ugroup_id)
                        ->first();

                    $row = [
                        'view_right' => max((int) ($existing->view_right ?? 0), (int) ($right->view_right ?? 0)),
                        'created_at' => $existing->created_at ?? now(),
                        'updated_at' => now(),
                    ];
                    foreach (['add_right', 'edit_right', 'delete_right'] as $column) {
                        if (Schema::hasColumn('page_rights', $column)) {
                            $row[$column] = max((int) ($existing->{$column} ?? 0), (int) ($right->{$column} ?? 0));
                        }
                    }

                    DB::table('page_rights')->updateOrInsert(
                        ['page_id' => $homepagePageId, 'ugroup_id' => $right->ugroup_id],
                        $row
                    );
                }
                DB::table('page_rights')->whereIn('page_id', $obsolete)->delete();
            }
            DB::table('pages')->whereIn('id', $obsolete)->delete();
        }

        $this->seedAboutPage();
    }

    private function seedAboutPage(): void
    {
        if (!Schema::hasTable('website_pages')) return;

        $legacy = Schema::hasTable('about_us') ? DB::table('about_us')->first() : null;
        $title = $legacy?->title ?: json_encode(['en' => 'About Us']);
        $description = $legacy?->description ?: json_encode(['en' => '<p>Use this page to tell students about your organization, mission and teaching approach.</p>']);

        $normalize = static function ($value, string $fallback): string {
            if (is_string($value) && str_starts_with(trim($value), '{')) return $value;
            return json_encode(['en' => $value ?: $fallback], JSON_UNESCAPED_UNICODE);
        };

        $organizations = Schema::hasColumn('website_pages', 'organization_id') && Schema::hasTable('organizations')
            ? DB::table('organizations')->pluck('id')->all() : [null];

        foreach ($organizations as $organizationId) {
            $query = DB::table('website_pages');
            if (Schema::hasColumn('website_pages', 'organization_id')) $query->where('organization_id', $organizationId);

            $exists = $query->get()->contains(function ($page) {
                $short = json_decode((string) $page->short_title, true);
                return strtolower((string) ($short['en'] ?? $page->short_title)) === 'about-us';
            });
            if ($exists) continue;

            $row = [
                'short_title' => json_encode(['en' => 'about-us']),
                'title' => $normalize($title, 'About Us'),
                'description' => $normalize($description, '<p>About our organization.</p>'),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('website_pages', 'organization_id')) $row['organization_id'] = $organizationId;
            if (Schema::hasColumn('website_pages', 'show_in_menu')) $row['show_in_menu'] = 0;
            if (Schema::hasColumn('website_pages', 'show_in_footer')) $row['show_in_footer'] = 1;
            foreach (['meta_title','meta_description','meta_keywords','canonical_url','og_title','og_description','og_image','robots_meta','seo_schema'] as $column) {
                if (Schema::hasColumn('website_pages', $column)) $row[$column] = $column === 'robots_meta' ? 'index,follow' : ($legacy?->{$column} ?? null);
            }
            DB::table('website_pages')->insert($row);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pages')) {
            DB::table('pages')->where('action_name', 'homepage-content.index')->update([
                'page_name' => 'Features',
                'action_name' => 'features.index',
                'icon' => 'ri-layout-grid-line',
                'ordering' => 22,
                'updated_at' => now(),
            ]);
        }
    }
};