<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UrlRedirect extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'source_path',
        'target_path',
        'status_code',
        'is_active',
        'hit_count',
        'last_hit_at',
        'note',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'status_code' => 'integer',
        'hit_count' => 'integer',
        'last_hit_at' => 'datetime',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
