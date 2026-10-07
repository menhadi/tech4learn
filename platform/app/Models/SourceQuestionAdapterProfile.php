<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SourceQuestionAdapterProfile extends Model
{
    protected $fillable = [
        'organization_id', 'name', 'key', 'url_pattern', 'selectors', 'enabled', 'version',
    ];

    protected $casts = [
        'selectors' => 'array',
        'enabled' => 'boolean',
        'version' => 'integer',
    ];
}
