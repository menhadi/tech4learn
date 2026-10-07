<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('languages')) {
            return;
        }

        try {
            Schema::table('languages', function (Blueprint $table) {
                $table->dropUnique('languages_name_unique');
            });
        } catch (\Throwable $e) {
            //
        }

        try {
            Schema::table('languages', function (Blueprint $table) {
                $table->dropUnique('languages_code_unique');
            });
        } catch (\Throwable $e) {
            //
        }

        if (! Schema::hasColumn('languages', 'organization_id')) {
            Schema::table('languages', function (Blueprint $table) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('id')->index();
            });
        }

        $organizations = Schema::hasTable('organizations')
            ? DB::table('organizations')->select('id', 'slug')->get()
            : collect();

        $defaultOrganizationId = $organizations->firstWhere('slug', 'examelite')->id
            ?? $organizations->first()->id
            ?? null;

        if ($defaultOrganizationId) {
            DB::table('languages')->whereNull('organization_id')->update([
                'organization_id' => $defaultOrganizationId,
            ]);
        }

        $sourceLanguages = DB::table('languages')
            ->when($defaultOrganizationId, fn ($query) => $query->where('organization_id', $defaultOrganizationId))
            ->get();

        foreach ($organizations as $organization) {
            foreach ($sourceLanguages as $language) {
                $exists = DB::table('languages')
                    ->where('organization_id', $organization->id)
                    ->where('code', $language->code)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('languages')->insert([
                    'organization_id' => $organization->id,
                    'name' => $language->name,
                    'code' => $language->code,
                    'value1' => $language->value1,
                    'value2' => $language->value2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        try {
            Schema::table('languages', function (Blueprint $table) {
                $table->unique(['organization_id', 'name']);
                $table->unique(['organization_id', 'code']);
            });
        } catch (\Throwable $e) {
            //
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('languages') || ! Schema::hasColumn('languages', 'organization_id')) {
            return;
        }

        try {
            Schema::table('languages', function (Blueprint $table) {
                $table->dropUnique(['organization_id', 'name']);
            });
        } catch (\Throwable $e) {
            //
        }

        try {
            Schema::table('languages', function (Blueprint $table) {
                $table->dropUnique(['organization_id', 'code']);
            });
        } catch (\Throwable $e) {
            //
        }

        Schema::table('languages', function (Blueprint $table) {
            $table->dropColumn('organization_id');
        });
    }
};
