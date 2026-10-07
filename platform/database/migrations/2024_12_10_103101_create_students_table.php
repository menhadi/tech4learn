<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('address');
            $table->string('phone')->unique();
            $table->string('guardian_phone')->nullable();
            $table->string('enroll')->nullable();
            $table->string('photo')->nullable();
            $table->enum('status', ['Active', 'Pending', 'Suspend']);
            $table->string('reg_code')->nullable();
            $table->string('reg_status')->default('Live');
            $table->integer('expiry_days')->nullable();
            $table->date('renewal_date')->nullable();
            $table->string('presetcode')->unique()->nullable();
            $table->string('otp')->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->dateTime('last_login')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
