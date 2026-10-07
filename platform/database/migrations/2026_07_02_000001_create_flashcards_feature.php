<?php

use App\Models\SaasPlan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            if (! Schema::hasColumn('packages', 'flashcards_enabled')) {
                $table->boolean('flashcards_enabled')->default(false)->after('show_pdf_download');
            }

            if (! Schema::hasColumn('packages', 'ai_flashcard_generation_enabled')) {
                $table->boolean('ai_flashcard_generation_enabled')->default(false)->after('flashcards_enabled');
            }
        });

        if (! Schema::hasTable('flashcard_sets')) {
            Schema::create('flashcard_sets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('package_id')->constrained()->cascadeOnDelete();
                $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
                $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
                $table->foreignId('topic_id')->nullable()->constrained('topics')->nullOnDelete();
                $table->foreignId('stopic_id')->nullable()->constrained('stopics')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('title');
                $table->string('source_type')->default('manual');
                $table->string('source_file')->nullable();
                $table->boolean('status')->default(true);
                $table->timestamps();

                $table->index(['organization_id', 'package_id', 'status']);
            });
        }

        if (! Schema::hasTable('flashcards')) {
            Schema::create('flashcards', function (Blueprint $table) {
                $table->id();
                $table->foreignId('flashcard_set_id')->constrained('flashcard_sets')->cascadeOnDelete();
                $table->text('front');
                $table->text('back');
                $table->text('explanation')->nullable();
                $table->string('hint')->nullable();
                $table->string('difficulty')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('status')->default(true);
                $table->timestamps();

                $table->index(['flashcard_set_id', 'status', 'sort_order']);
            });
        }

        if (! Schema::hasTable('flashcard_reviews')) {
            Schema::create('flashcard_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
                $table->foreignId('package_id')->nullable()->constrained('packages')->nullOnDelete();
                $table->foreignId('flashcard_id')->constrained('flashcards')->cascadeOnDelete();
                $table->string('response')->default('reviewed');
                $table->unsignedSmallInteger('points')->default(0);
                $table->timestamp('reviewed_at')->useCurrent();
                $table->timestamps();

                $table->index(['student_id', 'package_id', 'reviewed_at']);
            });
        }

        if (Schema::hasTable('saas_plans')) {
            SaasPlan::query()->get()->each(function (SaasPlan $plan) {
                $features = is_array($plan->features) ? $plan->features : [];
                $features['flashcards'] = $features['flashcards'] ?? true;
                $features['ai_flashcard_generation'] = $features['ai_flashcard_generation'] ?? (bool) ($features['ai_content_generation'] ?? false);
                $plan->features = $features;
                $plan->save();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('flashcard_reviews');
        Schema::dropIfExists('flashcards');
        Schema::dropIfExists('flashcard_sets');

        Schema::table('packages', function (Blueprint $table) {
            if (Schema::hasColumn('packages', 'ai_flashcard_generation_enabled')) {
                $table->dropColumn('ai_flashcard_generation_enabled');
            }

            if (Schema::hasColumn('packages', 'flashcards_enabled')) {
                $table->dropColumn('flashcards_enabled');
            }
        });
    }
};
