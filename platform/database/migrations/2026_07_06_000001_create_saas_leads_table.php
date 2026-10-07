<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->string('preferred_plan', 80)->nullable()->index();
            $table->string('institute_name', 180);
            $table->string('contact_name', 120);
            $table->string('email', 180)->index();
            $table->string('phone', 40)->nullable();
            $table->string('institute_type', 80)->nullable();
            $table->unsignedInteger('expected_students')->nullable();
            $table->text('message')->nullable();
            $table->string('source_url')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('status', 40)->default('new')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_leads');
    }
};