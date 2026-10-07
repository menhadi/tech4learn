<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // Coupon code (jaise "SALE100")
            $table->enum('type', ['fixed', 'percent']); // Discount type
            $table->decimal('value', 10, 2); // 100 (agar fixed) ya 10 (agar 10%)
            $table->decimal('min_amount', 10, 2)->nullable(); // Minimum order amount
            $table->integer('max_uses')->nullable(); // Total kitni baar use ho sakta hai
            $table->integer('used_count')->default(0); // Kitni baar use ho chuka hai
            $table->timestamp('expires_at')->nullable(); // Kab expire hoga
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};