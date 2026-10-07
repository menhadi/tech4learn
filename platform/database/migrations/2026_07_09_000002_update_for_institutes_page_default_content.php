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

        $page = DB::table('website_pages')->get()->first(function ($page) {
            $shortTitle = $page->short_title ?? '';
            $decoded = json_decode($shortTitle, true);
            $label = is_array($decoded) ? ($decoded['en'] ?? reset($decoded) ?: '') : $shortTitle;

            return Str::slug((string) $label) === 'for-institutes';
        });

        if (! $page) {
            return;
        }

        $description = $page->description ?? '';
        $decodedDescription = json_decode($description, true);
        $currentHtml = is_array($decodedDescription) ? ($decodedDescription['en'] ?? reset($decodedDescription) ?: '') : $description;

        if (trim(strip_tags((string) $currentHtml)) !== '' && ! str_contains((string) $currentHtml, 'Use this page to explain your institute plans')) {
            return;
        }

        $html = <<<'HTML'
<section>
    <p>ExamElite helps schools, coaching institutes, NGOs, and training teams run online exams, practice tests, study cards, reports, and branded learning pages from one simple platform.</p>
</section>

<section>
    <h2>Who can use ExamElite?</h2>
    <ul>
        <li>NGOs and small institutes that want a simple exam system for fewer than 50 students.</li>
        <li>Schools that need student registration, online exams, results, and reports.</li>
        <li>Coaching institutes that publish mock tests, previous year papers, study cards, leaderboards, and paid or free packages.</li>
    </ul>
</section>

<section>
    <h2>Plans</h2>
    <h3>Free Access</h3>
    <p>For NGOs and very small institutes with fewer than 50 students. Includes branded exam pages, student login, free exam packages, basic reports, and study cards.</p>

    <h3>Growth</h3>
    <p>For institutes that need more students, more exam packages, reports, controlled website sections, and optional paid course flow.</p>

    <h3>Professional</h3>
    <p>For larger institutes that need advanced controls, AI tools, question sharing, student performance analysis, SMS/email options, and priority setup support.</p>
</section>

<section>
    <h2>Important Features</h2>
    <ul>
        <li>Online exams with live timer, language selection, PDF options, and instant result flow.</li>
        <li>Study cards linked with groups, packages, subjects, topics, and subtopics.</li>
        <li>Student dashboard with exams, reports, practice tests, study progress, and leaderboard.</li>
        <li>Admin panel for questions, packages, students, results, website pages, email templates, and plan controls.</li>
        <li>Optional AI tools for content, questions, subjective review, and performance guidance.</li>
    </ul>
</section>

<section>
    <h2>How to start</h2>
    <p>Contact ExamElite with your institute type, expected student count, and the kind of exams you want to run. We will help you choose the correct setup.</p>
</section>
HTML;

        DB::table('website_pages')->where('id', $page->id)->update([
            'description' => json_encode(['en' => $html]),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Keep admin-edited page content intact.
    }
};