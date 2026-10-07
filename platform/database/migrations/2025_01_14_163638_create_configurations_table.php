<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('configurations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('organization_name')->nullable();
            $table->string('domain_name')->nullable();
            $table->string('email')->nullable();
            $table->text('meta_title')->nullable();
            $table->text('meta_keyword')->nullable();
            $table->text('meta_content')->nullable();
            $table->string('timezone')->nullable();
            $table->string('author')->nullable();
            $table->boolean('sms_notification')->nullable();
            $table->boolean('email_notification')->nullable();
            $table->boolean('guest_login')->nullable();
            $table->boolean('front_end')->nullable();
            $table->boolean('slides')->nullable();
            $table->boolean('translate')->nullable();
            $table->boolean('paid_exam')->nullable();
            $table->boolean('leader_board')->nullable();
            $table->boolean('math_editor')->nullable();
            $table->boolean('certificate')->nullable();
            $table->text('contact')->nullable();
            $table->text('email_contact')->nullable();
            $table->string('logo')->nullable();
            $table->string('signature')->nullable();
            $table->string('favicon')->nullable();
            $table->string('date_format')->nullable();
            $table->integer('exam_expiry')->nullable();
            $table->boolean('exam_feedback')->nullable();
            $table->integer('tolerance_count')->nullable();
            $table->string('powered_by')->nullable();
            $table->string('powered_link')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configurations');
    }
};
