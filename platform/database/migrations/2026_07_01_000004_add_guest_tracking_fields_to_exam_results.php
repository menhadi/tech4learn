<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('exam_results')) {
            return;
        }

        if (! Schema::hasColumn('exam_results', 'guest_id')) {
            Schema::table('exam_results', function (Blueprint $table) {
                $table->string('guest_id')->nullable()->after('exam_id')->index();
            });
        }

        Schema::table('exam_results', function (Blueprint $table) {
            if (! Schema::hasColumn('exam_results', 'guest_name')) {
                $table->string('guest_name')->nullable()->after('guest_id');
            }

            if (! Schema::hasColumn('exam_results', 'guest_email')) {
                $table->string('guest_email')->nullable()->after('guest_name');
            }

            if (! Schema::hasColumn('exam_results', 'guest_ip_address')) {
                $table->string('guest_ip_address', 45)->nullable()->after('guest_email');
            }

            if (! Schema::hasColumn('exam_results', 'guest_user_agent')) {
                $table->text('guest_user_agent')->nullable()->after('guest_ip_address');
            }

            if (! Schema::hasColumn('exam_results', 'guest_result_prompted_at')) {
                $table->timestamp('guest_result_prompted_at')->nullable()->after('guest_user_agent');
            }

            if (! Schema::hasColumn('exam_results', 'guest_result_viewed_at')) {
                $table->timestamp('guest_result_viewed_at')->nullable()->after('guest_result_prompted_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('exam_results')) {
            return;
        }

        Schema::table('exam_results', function (Blueprint $table) {
            $columns = [
                'guest_name',
                'guest_email',
                'guest_ip_address',
                'guest_user_agent',
                'guest_result_prompted_at',
                'guest_result_viewed_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('exam_results', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
