<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePagesTable extends Migration
{
    public function up()
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('page_name');
            $table->string('action_name')->default('index');
            $table->string('icon')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->integer('ordering')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('pages');
    }
}