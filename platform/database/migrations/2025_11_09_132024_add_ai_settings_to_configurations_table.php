<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // configurations table ko pakdo aur usme changes karo
        Schema::table('configurations', function (Blueprint $table) {
            
            // Yeh 3 naye columns add karo
            
            // Column 1: Yeh save karega ki user 'google' use kar raha hai ya 'openai'
            $table->string('ai_provider')->nullable()->default('google');
            
            // Column 2: Google ki key save karne ke liye
            $table->text('google_gemini_api_key')->nullable()->after('ai_provider');
            
            // Column 3: OpenAI ki key save karne ke liye
            $table->text('openai_api_key')->nullable()->after('google_gemini_api_key');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Agar kabhi migration rollback (undo) karna pade
        Schema::table('configurations', function (Blueprint $table) {
            // Yeh 3 columns delete kar dena
            $table->dropColumn(['ai_provider', 'google_gemini_api_key', 'openai_api_key']);
        });
    }
};