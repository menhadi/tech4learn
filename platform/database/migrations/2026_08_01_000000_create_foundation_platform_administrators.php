<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('foundation_platform_administrators', function (Blueprint $table) {
            $table->foreignId('organization_id')->primary()->constrained('organizations')->restrictOnDelete();
            $table->uuid('canonical_user_id')->unique();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('foundation_platform_administrators'); }
};
