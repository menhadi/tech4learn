<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('groups', function (Blueprint $table) {
            if (!Schema::hasColumn('groups', 'display_order')) {
                $table->integer('display_order')->default(0)->after('id');
            }
        });
    }

    public function down()
    {
        Schema::table('groups', function (Blueprint $table) {
            if (Schema::hasColumn('groups', 'display_order')) {
                $table->dropColumn('display_order');
            }
        });
    }
};
