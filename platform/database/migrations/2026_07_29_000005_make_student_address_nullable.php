<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('address')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('students')->whereNull('address')->update(['address' => '']);

        Schema::table('students', function (Blueprint $table) {
            $table->string('address')->nullable(false)->change();
        });
    }
};
