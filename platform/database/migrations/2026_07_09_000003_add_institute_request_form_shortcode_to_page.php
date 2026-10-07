<?php

use App\Models\WebsitePage;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $page = WebsitePage::where('short_title', 'for-institutes')->first();

        if (! $page) {
            return;
        }

        $description = (string) ($page->description ?? '');

        if (str_contains($description, '[institute_request_form]')) {
            return;
        }

        $formSection = <<<'HTML'

<section>
    <h2>Request a demo</h2>
    <p>Tell us about your institute, expected students, and the exams or study cards you want to run. This form sends the request to the SaaS Control Center.</p>
    [institute_request_form]
</section>
HTML;

        $page->description = trim($description) . $formSection;
        $page->save();
    }

    public function down(): void
    {
        $page = WebsitePage::where('short_title', 'for-institutes')->first();

        if (! $page) {
            return;
        }

        $section = <<<'HTML'

<section>
    <h2>Request a demo</h2>
    <p>Tell us about your institute, expected students, and the exams or study cards you want to run. This form sends the request to the SaaS Control Center.</p>
    [institute_request_form]
</section>
HTML;

        $page->description = str_replace($section, '', (string) $page->description);
        $page->save();
    }
};
