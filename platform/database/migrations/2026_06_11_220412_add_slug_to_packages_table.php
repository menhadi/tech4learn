<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('packages', 'slug')) {
            Schema::table('packages', function (Blueprint $table) {
                $table->string('slug')->nullable()->unique()->after('name');
            });
        }

        // Generate slugs for existing packages
        $packages = DB::table('packages')->get();
        foreach ($packages as $package) {
            $slug = Str::slug($package->name);
            $originalSlug = $slug;
            $counter = 1;
            
            while (DB::table('packages')->where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter++;
            }
            
            DB::table('packages')->where('id', $package->id)->update(['slug' => $slug]);
        }
    }

    public function down()
    {
        // The slug column is owned by the earlier 2025_10_08 migration.
    }
};
