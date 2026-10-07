<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The native model and phone-auth migration expect this legacy field.
        // Do not mark existing students verified as a consequence of installation.
        if (!Schema::hasColumn('students', 'email_verified_at')) {
            Schema::table('students', function (Blueprint $table) {
                $table->timestamp('email_verified_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('students', fn (Blueprint $table) => $table->dropColumn('email_verified_at'));
    }
};
