<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('configurations', 'mathpix_enabled')) {
            Schema::table('configurations', fn (Blueprint $table) => $table->boolean('mathpix_enabled')->default(false)->after('anthropic_model'));
        }
        if (! Schema::hasColumn('configurations', 'mathpix_app_id')) {
            Schema::table('configurations', fn (Blueprint $table) => $table->string('mathpix_app_id')->nullable()->after('mathpix_enabled'));
        }
        if (! Schema::hasColumn('configurations', 'mathpix_app_key')) {
            Schema::table('configurations', fn (Blueprint $table) => $table->text('mathpix_app_key')->nullable()->after('mathpix_app_id'));
        }
        if (! Schema::hasColumn('configurations', 'mathpix_min_confidence')) {
            Schema::table('configurations', fn (Blueprint $table) => $table->decimal('mathpix_min_confidence', 5, 2)->default(70)->after('mathpix_app_key'));
        }
    }

    public function down(): void
    {
        $columns = collect(['mathpix_enabled', 'mathpix_app_id', 'mathpix_app_key', 'mathpix_min_confidence'])
            ->filter(fn (string $column) => Schema::hasColumn('configurations', $column))->all();
        if ($columns !== []) Schema::table('configurations', fn (Blueprint $table) => $table->dropColumn($columns));
    }
};