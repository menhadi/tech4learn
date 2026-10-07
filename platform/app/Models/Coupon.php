<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'code',
        'type',
        'value',
        'min_amount',
        'max_uses',
        'used_count',
        'expires_at',
        'status',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
