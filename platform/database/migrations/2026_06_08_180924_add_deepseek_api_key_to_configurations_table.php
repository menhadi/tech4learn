<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->text('deepseek_api_key')->nullable()->after('openai_api_key');
        });
    }

    public function down()
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->dropColumn('deepseek_api_key');
        });
    }
};
