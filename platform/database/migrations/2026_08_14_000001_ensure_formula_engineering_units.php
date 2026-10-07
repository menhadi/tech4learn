<?php

use App\Services\FormulaUnitConversionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('formula_units')
            || ! Schema::hasColumn('formula_units', 'quantity_name')
            || ! Schema::hasColumn('formula_units', 'is_si')) {
            return;
        }

        app(FormulaUnitConversionService::class)->ensureDefaults(null);
    }

    public function down(): void
    {
        // The earlier expansion migration owns removal of its unit definitions.
    }
};
