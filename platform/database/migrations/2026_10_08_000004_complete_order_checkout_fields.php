<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Native checkout stores customer identity on the account or guest session.
        // Existing optional billing snapshots remain intact.
        Schema::table('orders',function (Blueprint $table) {
            $table->unsignedBigInteger('student_id')->nullable()->change();
            foreach (['first_name','last_name','email','phone','city','state','zip'] as $field) {
                $table->string($field)->nullable()->change();
            }
            $table->text('address')->nullable()->change();
        });
        foreach (['guest_id','payment_status','discount'] as $field) {
            if (!Schema::hasColumn('orders',$field)) {
                Schema::table('orders',function (Blueprint $table) use ($field) {
                    if ($field==='guest_id') $table->string($field)->nullable()->index();
                    elseif ($field==='payment_status') $table->string($field)->nullable();
                    else $table->decimal($field,10,2)->default(0);
                });
            }
        }
    }

    public function down(): void
    {
        // Preserve orders and billing/guest identity evidence on rollback.
    }
};
