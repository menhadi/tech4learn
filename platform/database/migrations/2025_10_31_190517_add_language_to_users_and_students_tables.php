<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 'users' table (admin/staff ke liye)
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                // 'status' column ke baad 'language' column add karega
                $table->string('language', 5)->nullable()->default('en')->after('status');
            });
        }

        // 'students' table (students ke liye)
        if (Schema::hasTable('students')) {
            Schema::table('students', function (Blueprint $table) {
                // 'status' column ke baad 'language' column add karega
                $table->string('language', 5)->nullable()->default('en')->after('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('language');
            });
        }
        
        if (Schema::hasTable('students')) {
            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn('language');
            });
        }
    }
};