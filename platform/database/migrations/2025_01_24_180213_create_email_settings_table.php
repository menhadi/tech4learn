<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmailSettingsTable extends Migration
{
    public function up()
    {
        Schema::create('email_settings', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['local', 'smtp']);
            $table->string('host')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->integer('port')->nullable();
            $table->boolean('tls')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('email_settings');
    }
}