<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('configurations')) {
            return;
        }

        Schema::table('configurations', function (Blueprint $table) {
            if (! Schema::hasColumn('configurations', 'quick_quiz_show_hero')) {
                $table->boolean('quick_quiz_show_hero')->default(true);
            }
            if (! Schema::hasColumn('configurations', 'quick_quiz_prompt_frequency')) {
                $table->string('quick_quiz_prompt_frequency', 20)->default('daily');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('configurations')) {
            return;
        }

        Schema::table('configurations', function (Blueprint $table) {
            if (Schema::hasColumn('configurations', 'quick_quiz_prompt_frequency')) {
                $table->dropColumn('quick_quiz_prompt_frequency');
            }
            if (Schema::hasColumn('configurations', 'quick_quiz_show_hero')) {
                $table->dropColumn('quick_quiz_show_hero');
            }
        });
    }
};
