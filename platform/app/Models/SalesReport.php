<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'order_id', 'package_id', 'student_id', 'price', 'quantity', 'total', 'sold_at'
    ];

    protected $casts = [
        'sold_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

}
