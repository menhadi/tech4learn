<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePageRightsTable extends Migration
{
    public function up()
    {
        Schema::create('page_rights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->onDelete('cascade');
            $table->foreignId('ugroup_id')->constrained()->onDelete('cascade');
            $table->boolean('view_right')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('page_rights');
    }
}