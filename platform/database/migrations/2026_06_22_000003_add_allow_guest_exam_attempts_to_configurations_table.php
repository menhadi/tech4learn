<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            if (!Schema::hasColumn('configurations', 'allow_guest_exam_attempts')) {
                $table->boolean('allow_guest_exam_attempts')->default(true)->after('timezone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            if (Schema::hasColumn('configurations', 'allow_guest_exam_attempts')) {
                $table->dropColumn('allow_guest_exam_attempts');
            }
        });
    }
};
