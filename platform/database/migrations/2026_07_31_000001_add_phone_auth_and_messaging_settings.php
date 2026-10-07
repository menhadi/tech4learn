<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('phone')->nullable()->change();
            $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
            $table->string('otp_channel', 20)->nullable()->after('otp_expires_at');
        });

        Schema::table('configurations', function (Blueprint $table) {
            $table->string('student_otp_channel', 20)->default('email');
            $table->string('sms_provider', 20)->nullable();
            $table->string('whatsapp_provider', 20)->nullable();
            $table->string('default_country_code', 8)->default('+91');
            $table->text('messaging_credentials')->nullable();
            $table->json('messaging_settings')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->dropColumn(['student_otp_channel', 'sms_provider', 'whatsapp_provider', 'default_country_code', 'messaging_credentials', 'messaging_settings']);
        });
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['phone_verified_at', 'otp_channel']);
        });
    }
};
