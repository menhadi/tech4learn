<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('student_hidden_packages')) {
            return;
        }

        Schema::create('student_hidden_packages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->unsignedBigInteger('student_id')->index();
            $table->unsignedBigInteger('package_id')->index();
            $table->timestamp('hidden_at')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'package_id'], 'student_hidden_packages_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_hidden_packages');
    }
};
