<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'student_id', 'transaction_id', 'payment_gateway', 'amount', 'status'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
