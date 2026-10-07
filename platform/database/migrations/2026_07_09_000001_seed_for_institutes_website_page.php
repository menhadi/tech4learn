<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('website_pages')) {
            return;
        }

        $exists = DB::table('website_pages')->get()->contains(function ($page) {
            $shortTitle = $page->short_title ?? '';
            $decoded = json_decode($shortTitle, true);
            $label = is_array($decoded) ? ($decoded['en'] ?? reset($decoded) ?: '') : $shortTitle;

            return Str::slug((string) $label) === 'for-institutes';
        });

        if ($exists) {
            return;
        }

        $orgId = null;
        if (Schema::hasColumn('website_pages', 'organization_id') && Schema::hasTable('organizations')) {
            $orgId = DB::table('organizations')->where('slug', 'examelite')->value('id')
                ?: DB::table('organizations')->orderBy('id')->value('id');
        }

        $description = <<<'HTML'
<p>ExamElite helps schools, coaching institutes, NGOs, and training teams run online exams, practice tests, study cards, student reports, and branded learning pages from one simple platform.</p>
<p>Use this page to explain your institute plans, features, support, and onboarding process. You can edit this text anytime from Website Settings &gt; Website Pages.</p>
HTML;

        $row = [
            'short_title' => json_encode(['en' => 'for-institutes']),
            'title' => json_encode(['en' => 'For Institutes']),
            'description' => json_encode(['en' => $description]),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('website_pages', 'organization_id')) {
            $row['organization_id'] = $orgId;
        }
        if (Schema::hasColumn('website_pages', 'show_in_menu')) {
            $row['show_in_menu'] = false;
        }
        if (Schema::hasColumn('website_pages', 'show_in_footer')) {
            $row['show_in_footer'] = false;
        }
        if (Schema::hasColumn('website_pages', 'robots_meta')) {
            $row['robots_meta'] = 'index,follow';
        }
        if (Schema::hasColumn('website_pages', 'meta_title')) {
            $row['meta_title'] = 'For Institutes | ExamElite';
        }
        if (Schema::hasColumn('website_pages', 'meta_description')) {
            $row['meta_description'] = 'ExamElite platform for institutes to run online exams, practice tests, study cards, reports, and branded learning pages.';
        }

        DB::table('website_pages')->insert($row);
    }

    public function down(): void
    {
        if (! Schema::hasTable('website_pages')) {
            return;
        }

        DB::table('website_pages')
            ->where('short_title', 'like', '%for-institutes%')
            ->delete();
    }
};