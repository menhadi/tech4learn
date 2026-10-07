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

        Schema::table('languages', function (Blueprint $table) {
            if (! Schema::hasColumn('languages', 'source_language_id')) {
                $table->unsignedBigInteger('source_language_id')->nullable()->after('organization_id')->index();
            }

            if (! Schema::hasColumn('languages', 'is_enabled')) {
                $table->boolean('is_enabled')->default(true)->after('value2')->index();
            }
        });

        if (! Schema::hasTable('organizations') || ! Schema::hasColumn('languages', 'organization_id')) {
            return;
        }

        $platformOrganizationId = DB::table('organizations')->where('slug', 'examelite')->value('id');

        if (! $platformOrganizationId) {
            return;
        }

        $platformLanguages = DB::table('languages')
            ->where('organization_id', $platformOrganizationId)
            ->get()
            ->keyBy('code');

        foreach ($platformLanguages as $language) {
            DB::table('languages')
                ->where('id', $language->id)
                ->update([
                    'source_language_id' => null,
                    'is_enabled' => true,
                ]);
        }

        $usedLanguageIds = collect();

        foreach ([
            ['questions', 'language_id'],
            ['question_langs', 'language_id'],
            ['passage_langs', 'language_id'],
        ] as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $usedLanguageIds = $usedLanguageIds->merge(
                DB::table($table)->whereNotNull($column)->distinct()->pluck($column)
            );
        }

        $usedLanguageIds = $usedLanguageIds->unique()->values();

        DB::table('languages')
            ->where('organization_id', '!=', $platformOrganizationId)
            ->orderBy('id')
            ->select('id', 'code')
            ->chunk(200, function ($languages) use ($platformLanguages, $usedLanguageIds) {
                foreach ($languages as $language) {
                    $platformLanguage = $platformLanguages->get($language->code);
                    $isEnabled = strtolower((string) $language->code) === 'en'
                        || $usedLanguageIds->contains($language->id);

                    DB::table('languages')
                        ->where('id', $language->id)
                        ->update([
                            'source_language_id' => $platformLanguage?->id,
                            'is_enabled' => $isEnabled,
                        ]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('languages')) {
            return;
        }

        Schema::table('languages', function (Blueprint $table) {
            if (Schema::hasColumn('languages', 'source_language_id')) {
                $table->dropIndex(['source_language_id']);
                $table->dropColumn('source_language_id');
            }

            if (Schema::hasColumn('languages', 'is_enabled')) {
                $table->dropIndex(['is_enabled']);
                $table->dropColumn('is_enabled');
            }
        });
    }
};
