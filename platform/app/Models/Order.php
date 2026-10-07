<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'guest_id',
        'student_id',
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'country',
        'zip',
        'payment_method',
        'payment_status',
        'notes',
        'total',
        'discount',
        'coupon_code',
        'status',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function transaction()
    {
        return $this->hasOne(Transaction::class);
    }

    public function salesReports()
    {
        return $this->hasMany(SalesReport::class);
    }
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

}
