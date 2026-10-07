<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $pages = [
        ['Dashboard', 'dashboard', 'ri-dashboard-line', 1],
        ['Groups', 'groups.index', 'ri-group-line', 2],
        ['Categories', 'category.index', 'ri-node-tree', 3],
        ['Students', 'students.index', 'ri-user-smile-line', 4],
        ['Users', 'users.index', 'ri-user-settings-line', 5],
        ['User Levels', 'ugroups.index', 'ri-shield-user-line', 6],
        ['Subjects', 'subjects.index', 'ri-book-open-line', 7],
        ['Topics', 'topics.index', 'ri-list-check-2', 8],
        ['Sub Topics', 'stopics.index', 'ri-list-check', 9],
        ['Passages', 'passages.index', 'ri-file-text-line', 10],
        ['Questions', 'questions.index', 'ri-question-line', 11],
        ['Question Import Export', 'questions.importExport', 'ri-file-excel-line', 12],
        ['Packages', 'packages.index', 'ri-stack-line', 13],
        ['Coupons', 'coupons.index', 'ri-coupon-line', 14],
        ['Exams', 'exams.index', 'ri-file-list-3-line', 15],
        ['Exam Reports', 'exams.reports', 'ri-alarm-warning-line', 16],
        ['Results', 'results.index', 'ri-medal-line', 17],
        ['Orders', 'orders.index', 'ri-shopping-cart-line', 18],
        ['Transactions', 'transactions.index', 'ri-bank-card-line', 19],
        ['Sales Reports', 'sales-reports.index', 'ri-line-chart-line', 20],
        ['Hero Slider', 'heroslider.index', 'ri-image-line', 21],
        ['Features', 'features.index', 'ri-layout-grid-line', 22],
        ['Counters', 'counters.index', 'ri-bar-chart-line', 23],
        ['Testimonials', 'testimonial.index', 'ri-chat-quote-line', 24],
        ['About Us', 'aboutus.index', 'ri-information-line', 25],
        ['Website Pages', 'websitepages.index', 'ri-pages-line', 26],
        ['Website Settings', 'configurations.website', 'ri-settings-4-line', 27],
        ['General Settings', 'configurations.general', 'ri-settings-line', 28],
        ['AI Settings', 'configurations.ai', 'ri-brain-line', 29],
        ['Email Templates', 'email-templates.index', 'ri-mail-settings-line', 30],
        ['Email Settings', 'email-settings.index', 'ri-mail-line', 31],
        ['Send Email', 'send-email-form', 'ri-send-plane-line', 32],
        ['SMS Templates', 'sms-templates.index', 'ri-message-2-line', 33],
        ['Payment Gateway', 'payment-gateway.index', 'ri-secure-payment-line', 34],
        ['AI Generator', 'ai.generator.form', 'ri-magic-line', 35],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        foreach ($this->pages as [$name, $action, $icon, $ordering]) {
            DB::table('pages')->updateOrInsert(
                ['action_name' => $action],
                [
                    'page_name' => $name,
                    'icon' => $icon,
                    'parent_id' => null,
                    'ordering' => $ordering,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        DB::table('pages')
            ->whereIn('action_name', array_column($this->pages, 1))
            ->delete();
    }
};
