<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('question_langs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->onDelete('cascade');
            $table->foreignId('language_id')->constrained()->onDelete('cascade');
            $table->text('question');
            $table->text('option1')->nullable();
            $table->text('option2')->nullable();
            $table->text('option3')->nullable();
            $table->text('option4')->nullable();
            $table->text('option5')->nullable();
            $table->text('option6')->nullable();
            $table->text('hint')->nullable();
            $table->text('explanation')->nullable();
            $table->text('fill_blank')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('question_langs');
    }
};
