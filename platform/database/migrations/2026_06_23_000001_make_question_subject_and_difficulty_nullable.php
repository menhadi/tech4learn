<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->unsignedBigInteger('subject_id')->nullable()->change();
            $table->unsignedBigInteger('diff_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->unsignedBigInteger('subject_id')->nullable(false)->change();
            $table->unsignedBigInteger('diff_id')->nullable(false)->change();
        });
    }
};
