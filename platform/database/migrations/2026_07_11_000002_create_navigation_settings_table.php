<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('navigation_settings')) {
            Schema::create('navigation_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id')->unique();
                $table->boolean('header_enabled')->default(false);
                $table->boolean('footer_enabled')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_settings');
    }
};
