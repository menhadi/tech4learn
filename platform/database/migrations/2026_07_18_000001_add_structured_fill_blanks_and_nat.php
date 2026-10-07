<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            if (! Schema::hasColumn('questions', 'fill_blank_config')) {
                $table->json('fill_blank_config')->nullable()->after('fill_blank');
            }
            if (! Schema::hasColumn('questions', 'nat_config')) {
                $table->json('nat_config')->nullable()->after('fill_blank_config');
            }
        });

        DB::table('qtypes')->updateOrInsert(
            ['type' => 'NAT'],
            [
                'question_type' => 'Numerical Answer Type (NAT)',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            if (Schema::hasColumn('questions', 'nat_config')) {
                $table->dropColumn('nat_config');
            }
            if (Schema::hasColumn('questions', 'fill_blank_config')) {
                $table->dropColumn('fill_blank_config');
            }
        });

        $natId = DB::table('qtypes')->where('type', 'NAT')->value('id');
        if ($natId && ! DB::table('questions')->where('qtype_id', $natId)->exists()) {
            DB::table('qtypes')->where('id', $natId)->delete();
        }
    }
};