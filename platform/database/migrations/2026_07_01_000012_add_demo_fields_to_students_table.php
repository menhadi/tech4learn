<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'is_demo')) {
                $table->boolean('is_demo')->default(false)->after('status')->index();
            }

            if (! Schema::hasColumn('students', 'demo_batch_id')) {
                $table->string('demo_batch_id')->nullable()->after('is_demo')->index();
            }

            if (! Schema::hasColumn('students', 'demo_created_by')) {
                $table->foreignId('demo_created_by')->nullable()->after('demo_batch_id')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('students', 'demo_generated_at')) {
                $table->timestamp('demo_generated_at')->nullable()->after('demo_created_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'demo_created_by')) {
                $table->dropConstrainedForeignId('demo_created_by');
            }

            foreach (['demo_generated_at', 'demo_batch_id', 'is_demo'] as $column) {
                if (Schema::hasColumn('students', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
