<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('page_rights')) {
            return;
        }

        Schema::table('page_rights', function (Blueprint $table) {
            if (! Schema::hasColumn('page_rights', 'add_right')) {
                $table->boolean('add_right')->default(false)->after('view_right');
            }

            if (! Schema::hasColumn('page_rights', 'edit_right')) {
                $table->boolean('edit_right')->default(false)->after('add_right');
            }

            if (! Schema::hasColumn('page_rights', 'delete_right')) {
                $table->boolean('delete_right')->default(false)->after('edit_right');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('page_rights')) {
            return;
        }

        Schema::table('page_rights', function (Blueprint $table) {
            foreach (['delete_right', 'edit_right', 'add_right'] as $column) {
                if (Schema::hasColumn('page_rights', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
