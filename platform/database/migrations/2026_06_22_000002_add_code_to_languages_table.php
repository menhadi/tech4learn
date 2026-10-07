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
        if (!Schema::hasColumn('languages', 'code')) {
            Schema::table('languages', function (Blueprint $table) {
                $table->string('code', 20)->nullable()->unique()->after('name');
            });
        }

        $map = [
            'english' => 'en',
            'hindi' => 'hi',
            'bengali' => 'bn',
            'telugu' => 'te',
            'marathi' => 'mr',
            'tamil' => 'ta',
            'urdu' => 'ur',
            'gujarati' => 'gu',
            'kannada' => 'kn',
            'odia' => 'or',
            'malayalam' => 'ml',
            'punjabi' => 'pa',
            'assamese' => 'as',
            'sanskrit' => 'sa',
        ];

        DB::table('languages')->orderBy('id')->select('id', 'name', 'code')->chunk(100, function ($languages) use ($map) {
            foreach ($languages as $language) {
                if (!empty($language->code)) {
                    continue;
                }

                $base = $map[Str::lower($language->name)] ?? Str::slug($language->name);
                $code = $base ?: 'lang-' . $language->id;
                $i = 2;

                while (DB::table('languages')->where('code', $code)->where('id', '!=', $language->id)->exists()) {
                    $code = $base . '-' . $i++;
                }

                DB::table('languages')->where('id', $language->id)->update(['code' => $code]);
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('languages', 'code')) {
            Schema::table('languages', function (Blueprint $table) {
                $table->dropColumn('code');
            });
        }
    }
};
