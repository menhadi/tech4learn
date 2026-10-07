<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NavigationSetting extends Model
{
    protected $fillable = ['organization_id', 'header_enabled', 'secondary_enabled', 'footer_enabled', 'footer_brand_label'];

    protected $casts = [
        'header_enabled' => 'boolean',
        'secondary_enabled' => 'boolean',
        'footer_enabled' => 'boolean',
    ];
}
