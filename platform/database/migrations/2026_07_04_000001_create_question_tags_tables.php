<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 80);
            $table->string('slug', 100);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'slug']);
        });

        Schema::create('question_question_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_tag_id')->constrained('question_tags')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['question_id', 'question_tag_id']);
        });

        $now = now();
        DB::table('question_tags')->insert([
            ['organization_id' => null, 'name' => 'Manual', 'slug' => 'manual', 'status' => true, 'created_at' => $now, 'updated_at' => $now],
            ['organization_id' => null, 'name' => 'AI Generated', 'slug' => 'ai-generated', 'status' => true, 'created_at' => $now, 'updated_at' => $now],
            ['organization_id' => null, 'name' => 'Shared From Platform', 'slug' => 'shared-from-platform', 'status' => true, 'created_at' => $now, 'updated_at' => $now],
            ['organization_id' => null, 'name' => 'Shared From Organization', 'slug' => 'shared-from-organization', 'status' => true, 'created_at' => $now, 'updated_at' => $now],
            ['organization_id' => null, 'name' => 'Needs Review', 'slug' => 'needs-review', 'status' => true, 'created_at' => $now, 'updated_at' => $now],
            ['organization_id' => null, 'name' => 'Verified', 'slug' => 'verified', 'status' => true, 'created_at' => $now, 'updated_at' => $now],
            ['organization_id' => null, 'name' => 'Image Based', 'slug' => 'image-based', 'status' => true, 'created_at' => $now, 'updated_at' => $now],
            ['organization_id' => null, 'name' => 'Passage Based', 'slug' => 'passage-based', 'status' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('question_question_tag');
        Schema::dropIfExists('question_tags');
    }
};
