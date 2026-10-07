<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Yahan 'website_pages' table mein column add karne ka code hai
        Schema::table('website_pages', function (Blueprint $table) {
            // 'show_in_menu' column add kiya ja raha hai, type boolean (database mein tinyint(1) banega),
            // default value 1 (true) hai, aur yeh 'title' column ke baad aayega.
            $table->boolean('show_in_menu')->default(1)->after('title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Yahan 'website_pages' table se column hatane ka code hai (agar rollback karna ho)
        Schema::table('website_pages', function (Blueprint $table) {
            $table->dropColumn('show_in_menu');
        });
    }
};