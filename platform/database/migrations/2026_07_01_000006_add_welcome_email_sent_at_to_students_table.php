<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('students', 'welcome_email_sent_at')) {
            Schema::table('students', function (Blueprint $table) {
                $table->timestamp('welcome_email_sent_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('students', 'welcome_email_sent_at')) {
            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn('welcome_email_sent_at');
            });
        }
    }
};
