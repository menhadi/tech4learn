<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PypPackageSetting extends Model
{
    protected $fillable = [
        'organization_id', 'package_id', 'enabled', 'analysis_mode',
        'indexable', 'meta_title', 'meta_description',
    ];

    protected $casts = ['enabled' => 'boolean', 'indexable' => 'boolean'];

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
