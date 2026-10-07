<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaasPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'price',
        'billing_cycle',
        'limits',
        'features',
        'is_default',
        'status',
    ];

    protected $casts = [
        'limits' => 'array',
        'features' => 'array',
        'is_default' => 'boolean',
        'status' => 'boolean',
        'price' => 'decimal:2',
    ];

    public function organizations()
    {
        return $this->hasMany(Organization::class);
    }
}
