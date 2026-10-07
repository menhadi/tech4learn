<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            // 'name' column ke baad ek 'slug' column add karega
            $table->string('slug')->nullable()->unique()->after('name');
            
            // 'package_type' column ke baad 'status' column add karega
            $table->boolean('status')->default(1)->after('package_type')->comment('1=Active, 0=Inactive');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('slug');
            $table->dropColumn('status');
        });
    }
};