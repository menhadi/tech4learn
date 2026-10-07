<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_updates', function (Blueprint $table) {
            $table->id();
            $table->string('current_version')->default('v1.0.0');
            $table->timestamps();
        });

        // insert initial version row
        \DB::table('system_updates')->insert([
            'current_version' => 'v1.0.0',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_updates');
    }
};
