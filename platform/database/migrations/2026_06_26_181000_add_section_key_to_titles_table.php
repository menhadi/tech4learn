<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $sections = [
        1 => 'features',
        2 => 'testimonials',
        3 => 'packages',
        4 => 'banner',
        5 => 'top_performers',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('titles')) {
            return;
        }

        if (! Schema::hasColumn('titles', 'section_key')) {
            Schema::table('titles', function (Blueprint $table) {
                $table->string('section_key')->nullable()->after('organization_id')->index();
            });
        }

        foreach ($this->sections as $id => $sectionKey) {
            DB::table('titles')
                ->where('id', $id)
                ->whereNull('section_key')
                ->update(['section_key' => $sectionKey]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('titles') || ! Schema::hasColumn('titles', 'section_key')) {
            return;
        }

        Schema::table('titles', function (Blueprint $table) {
            $table->dropIndex('titles_section_key_index');
            $table->dropColumn('section_key');
        });
    }
};
