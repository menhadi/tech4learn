<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_activity_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('guest_id')->nullable()->index();
            $table->foreignId('exam_id')->nullable()->constrained('exams')->nullOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('packages')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('exam_result_id')->nullable()->constrained('exam_results')->nullOnDelete();
            $table->string('event_name', 80);
            $table->string('source', 40)->default('web');
            $table->string('url', 2048)->nullable();
            $table->string('referrer', 2048)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['organization_id', 'event_name', 'occurred_at'], 'student_activity_org_event_idx');
            $table->index(['student_id', 'event_name', 'occurred_at'], 'student_activity_student_event_idx');
            $table->index(['exam_id', 'event_name', 'occurred_at'], 'student_activity_exam_event_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_activity_events');
    }
};
