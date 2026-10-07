<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // <-- Ye line add karna zaroori hai

return new class extends Migration
{
    /**
     * Run the migrations.
     * Naya 4-step tareeka: Rename -> Create -> Copy -> Drop
     */
    public function up(): void
    {
        // --- 1. titles ---
        Schema::table('titles', function (Blueprint $table) {
            $table->renameColumn('title', 'title_old');
            $table->renameColumn('sub_title', 'sub_title_old');
        });
        Schema::table('titles', function (Blueprint $table) {
            $table->json('title')->nullable()->after('id');
            $table->json('sub_title')->nullable()->after('title');
        });
        DB::statement("UPDATE titles SET title = JSON_OBJECT('en', title_old), sub_title = JSON_OBJECT('en', sub_title_old)");
        Schema::table('titles', function (Blueprint $table) {
            $table->dropColumn(['title_old', 'sub_title_old']);
        });

        // --- 2. website_pages ---
        Schema::table('website_pages', function (Blueprint $table) {
            $table->renameColumn('short_title', 'short_title_old');
            $table->renameColumn('title', 'title_old');
            $table->renameColumn('description', 'description_old');
        });
        Schema::table('website_pages', function (Blueprint $table) {
            $table->json('short_title')->nullable()->after('id');
            $table->json('title')->nullable()->after('short_title');
            $table->json('description')->nullable()->after('show_in_menu');
        });
        DB::statement("UPDATE website_pages SET short_title = JSON_OBJECT('en', short_title_old), title = JSON_OBJECT('en', title_old), description = JSON_OBJECT('en', description_old)");
        Schema::table('website_pages', function (Blueprint $table) {
            $table->dropColumn(['short_title_old', 'title_old', 'description_old']);
        });

        // --- 3. features ---
        Schema::table('features', function (Blueprint $table) {
            $table->renameColumn('title', 'title_old');
            $table->renameColumn('description', 'description_old');
        });
        Schema::table('features', function (Blueprint $table) {
            $table->json('title')->nullable()->after('image_url');
            $table->json('description')->nullable()->after('title');
        });
        DB::statement("UPDATE features SET title = JSON_OBJECT('en', title_old), description = JSON_OBJECT('en', description_old)");
        Schema::table('features', function (Blueprint $table) {
            $table->dropColumn(['title_old', 'description_old']);
        });

        // --- 4. counters ---
        Schema::table('counters', function (Blueprint $table) {
            $table->renameColumn('title', 'title_old');
        });
        Schema::table('counters', function (Blueprint $table) {
            $table->json('title')->nullable()->after('image_url');
        });
        DB::statement("UPDATE counters SET title = JSON_OBJECT('en', title_old)");
        Schema::table('counters', function (Blueprint $table) {
            $table->dropColumn('title_old');
        });

        // --- 5. testimonials ---
        Schema::table('testimonials', function (Blueprint $table) {
            $table->renameColumn('name', 'name_old');
            $table->renameColumn('feedback', 'feedback_old');
        });
        Schema::table('testimonials', function (Blueprint $table) {
            $table->json('name')->nullable()->after('image_url');
            $table->json('feedback')->nullable()->after('name');
        });
        DB::statement("UPDATE testimonials SET name = JSON_OBJECT('en', name_old), feedback = JSON_OBJECT('en', feedback_old)");
        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropColumn(['name_old', 'feedback_old']);
        });

        // --- 6. hero_sliders ---
        Schema::table('hero_sliders', function (Blueprint $table) {
            $table->renameColumn('title', 'title_old');
            $table->renameColumn('description', 'description_old');
        });
        Schema::table('hero_sliders', function (Blueprint $table) {
            $table->json('title')->nullable()->after('image_url');
            $table->json('description')->nullable()->after('title');
        });
        DB::statement("UPDATE hero_sliders SET title = JSON_OBJECT('en', title_old), description = JSON_OBJECT('en', description_old)");
        Schema::table('hero_sliders', function (Blueprint $table) {
            $table->dropColumn(['title_old', 'description_old']);
        });

        // --- 7. groups ---
        Schema::table('groups', function (Blueprint $table) {
            $table->dropUnique(['group_name']);
        });
        Schema::table('groups', function (Blueprint $table) {
            $table->renameColumn('group_name', 'group_name_old');
        });
        Schema::table('groups', function (Blueprint $table) {
            $table->json('group_name')->nullable()->after('id');
        });
        DB::statement("UPDATE groups SET group_name = JSON_OBJECT('en', group_name_old)");
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('group_name_old');
            $table->unique('group_name');
        });

        // --- 8. packages ---
        Schema::table('packages', function (Blueprint $table) {
            $table->renameColumn('name', 'name_old');
            $table->renameColumn('description', 'description_old');
        });
        Schema::table('packages', function (Blueprint $table) {
            $table->json('name')->nullable()->after('id');
            $table->json('description')->nullable()->after('slug');
        });
        DB::statement("UPDATE packages SET name = JSON_OBJECT('en', name_old), description = JSON_OBJECT('en', description_old)");
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['name_old', 'description_old']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert karna complex hoga, abhi isse skip karte hain.
    }
};