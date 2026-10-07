<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('packages', 'auto_enroll_on_registration')) {
            Schema::table('packages', function (Blueprint $table) {
                $table->boolean('auto_enroll_on_registration')
                    ->default(false)
                    ->after('package_type');
            });


        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('packages', 'auto_enroll_on_registration')) {
            Schema::table('packages', fn (Blueprint $table) => $table->dropColumn('auto_enroll_on_registration'));
        }
    }
};
