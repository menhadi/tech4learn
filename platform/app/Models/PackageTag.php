<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackageTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function packages()
    {
        return $this->belongsToMany(Package::class, 'package_tag_package', 'package_tag_id', 'package_id')
            ->withTimestamps();
    }
}
