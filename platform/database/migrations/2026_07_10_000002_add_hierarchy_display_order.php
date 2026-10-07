<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('category', function (Blueprint $table) {
            if (! Schema::hasColumn('category', 'display_order')) {
                $table->unsignedInteger('display_order')->nullable()->index();
            }
        });

        Schema::table('category_groups', function (Blueprint $table) {
            if (! Schema::hasColumn('category_groups', 'display_order')) {
                $table->unsignedInteger('display_order')->nullable()->index();
            }
        });

        Schema::table('packages', function (Blueprint $table) {
            if (! Schema::hasColumn('packages', 'display_order')) {
                $table->unsignedInteger('display_order')->nullable()->index();
            }
        });

        Schema::table('exam_packages', function (Blueprint $table) {
            if (! Schema::hasColumn('exam_packages', 'display_order')) {
                $table->unsignedInteger('display_order')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        foreach ([
            'exam_packages' => 'display_order',
            'packages' => 'display_order',
            'category_groups' => 'display_order',
            'category' => 'display_order',
        ] as $tableName => $column) {
            if (Schema::hasColumn($tableName, $column)) {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};