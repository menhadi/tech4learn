<?php

use App\Services\BotTrafficDetector;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('student_activity_events')) {
            Schema::table('student_activity_events', function (Blueprint $table) {
                if (! Schema::hasColumn('student_activity_events', 'is_bot')) {
                    $table->boolean('is_bot')->default(false)->index()->after('user_agent');
                }
                if (! Schema::hasColumn('student_activity_events', 'bot_name')) {
                    $table->string('bot_name', 100)->nullable()->after('is_bot');
                }
                if (! Schema::hasColumn('student_activity_events', 'bot_reason')) {
                    $table->string('bot_reason', 255)->nullable()->after('bot_name');
                }
            });

            foreach (BotTrafficDetector::databasePatterns() as $pattern) {
                DB::table('student_activity_events')
                    ->whereRaw('LOWER(COALESCE(user_agent, ?)) LIKE ?', ['', '%'.strtolower($pattern).'%'])
                    ->update(['is_bot' => true, 'bot_reason' => 'historical_user_agent:'.$pattern]);
            }
        }

        if (Schema::hasTable('exam_results')) {
            Schema::table('exam_results', function (Blueprint $table) {
                if (! Schema::hasColumn('exam_results', 'guest_is_bot')) {
                    $table->boolean('guest_is_bot')->default(false)->index()->after('guest_user_agent');
                }
                if (! Schema::hasColumn('exam_results', 'guest_bot_reason')) {
                    $table->string('guest_bot_reason', 255)->nullable()->after('guest_is_bot');
                }
            });

            foreach (BotTrafficDetector::databasePatterns() as $pattern) {
                DB::table('exam_results')
                    ->whereNotNull('guest_id')
                    ->whereRaw('LOWER(COALESCE(guest_user_agent, ?)) LIKE ?', ['', '%'.strtolower($pattern).'%'])
                    ->update(['guest_is_bot' => true, 'guest_bot_reason' => 'historical_user_agent:'.$pattern]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('student_activity_events')) {
            foreach (['bot_reason', 'bot_name', 'is_bot'] as $column) {
                if (Schema::hasColumn('student_activity_events', $column)) {
                    Schema::table('student_activity_events', fn (Blueprint $table) => $table->dropColumn($column));
                }
            }
        }

        if (Schema::hasTable('exam_results')) {
            foreach (['guest_bot_reason', 'guest_is_bot'] as $column) {
                if (Schema::hasColumn('exam_results', $column)) {
                    Schema::table('exam_results', fn (Blueprint $table) => $table->dropColumn($column));
                }
            }
        }
    }
};