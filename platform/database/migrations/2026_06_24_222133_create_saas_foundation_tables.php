<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('saas_plans')) {
            Schema::create('saas_plans', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->decimal('price', 10, 2)->default(0);
                $table->string('billing_cycle')->default('monthly');
                $table->json('limits')->nullable();
                $table->json('features')->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('status')->default(true);
                $table->timestamps();

                $table->index(['status', 'is_default']);
            });
        }

        if (! Schema::hasTable('organizations')) {
            Schema::create('organizations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('saas_plan_id')->nullable()->constrained('saas_plans')->nullOnDelete();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('domain')->nullable()->unique();
                $table->string('subdomain')->nullable()->unique();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('logo')->nullable();
                $table->string('favicon')->nullable();
                $table->string('status')->default('active');
                $table->timestamp('trial_ends_at')->nullable();
                $table->timestamp('subscription_ends_at')->nullable();
                $table->json('settings')->nullable();
                $table->timestamps();

                $table->index(['status', 'saas_plan_id']);
            });
        }

        if (! Schema::hasTable('organization_users')) {
            Schema::create('organization_users', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('role')->default('admin');
                $table->boolean('status')->default(true);
                $table->timestamps();

                $table->unique(['organization_id', 'user_id']);
                $table->index(['user_id', 'status']);
                $table->index(['organization_id', 'role']);
            });
        }

        $planId = DB::table('saas_plans')->where('slug', 'examelite-default')->value('id');

        if (! $planId) {
            $planId = DB::table('saas_plans')->insertGetId([
                'name' => 'ExamElite Default',
                'slug' => 'examelite-default',
                'price' => 0,
                'billing_cycle' => 'monthly',
                'limits' => json_encode([
                    'organizations' => 1,
                    'admins' => null,
                    'students' => null,
                    'exams' => null,
                    'packages' => null,
                    'questions' => null,
                ]),
                'features' => json_encode([
                    'public_website' => true,
                    'paid_packages' => true,
                    'guest_exams' => true,
                    'ai_generator' => true,
                    'ai_translation' => true,
                    'ai_regeneration' => true,
                    'ai_content_generation' => true,
                    'ai_subjective_analysis' => true,
                    'ai_student_analysis' => true,
                    'ai_seo' => true,
                    'ai_platform_api' => true,
                    'ai_settings' => true,
                    'custom_theme' => true,
                    'student_self_registration' => true,
                ]),
                'is_default' => true,
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! DB::table('organizations')->where('slug', 'examelite')->exists()) {
            DB::table('organizations')->insert([
                'saas_plan_id' => $planId,
                'name' => 'ExamElite',
                'slug' => 'examelite',
                'domain' => 'examelite.com',
                'subdomain' => 'examelite',
                'email' => DB::table('configurations')->value('email'),
                'phone' => Schema::hasColumn('configurations', 'organization_phone')
                    ? DB::table('configurations')->value('organization_phone') : null,
                'logo' => DB::table('configurations')->value('logo'),
                'favicon' => DB::table('configurations')->value('favicon'),
                'status' => 'active',
                'settings' => json_encode([
                    'is_primary_platform' => true,
                    'created_from_existing_site' => true,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_users');
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('saas_plans');
    }
};
