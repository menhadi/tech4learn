<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('to_email');
            $table->string('subject');
            $table->string('status'); // 'Success' or 'Failed'
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('email_logs');
    }
};