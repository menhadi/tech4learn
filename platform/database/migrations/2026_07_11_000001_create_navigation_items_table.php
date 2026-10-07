<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('navigation_items')) {
            Schema::create('navigation_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id')->index();
                $table->unsignedBigInteger('parent_id')->nullable()->index();
                $table->string('location', 20)->index();
                $table->string('section_label')->nullable();
                $table->string('label');
                $table->string('link_type', 30)->default('custom');
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->string('custom_url')->nullable();
                $table->string('icon')->nullable();
                $table->string('target', 10)->default('_self');
                $table->string('style', 20)->default('link');
                $table->boolean('desktop_visible')->default(true);
                $table->boolean('mobile_visible')->default(true);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index(['organization_id', 'location', 'is_active', 'sort_order'], 'navigation_items_lookup');
            });
        }

        if (Schema::hasTable('pages')) {
            DB::table('pages')->updateOrInsert(['action_name' => 'navigation.index'], [
                'page_name' => 'Navigation Manager', 'icon' => 'ri-navigation-line',
                'parent_id' => null, 'ordering' => 27, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pages')) {
            $id = DB::table('pages')->where('action_name', 'navigation.index')->value('id');
            if ($id && Schema::hasTable('page_rights')) DB::table('page_rights')->where('page_id', $id)->delete();
            DB::table('pages')->where('action_name', 'navigation.index')->delete();
        }
        Schema::dropIfExists('navigation_items');
    }
};
