<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique('students_email_unique');
            $table->dropUnique('students_phone_unique');

            $table->unique(['organization_id', 'email'], 'students_org_email_unique');
            $table->unique(['organization_id', 'phone'], 'students_org_phone_unique');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique('students_org_email_unique');
            $table->dropUnique('students_org_phone_unique');

            $table->unique('email', 'students_email_unique');
            $table->unique('phone', 'students_phone_unique');
        });
    }
};
