<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleSheetConnection extends Model
{
    protected $fillable = [
        'organization_id',
        'created_by',
        'resource',
        'spreadsheet_id',
        'spreadsheet_url',
        'tab_name',
        'share_email',
        'selected_fields',
        'filters',
        'last_exported_at',
        'last_synced_at',
    ];

    protected $casts = [
        'selected_fields' => 'array',
        'filters' => 'array',
        'last_exported_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];
}
