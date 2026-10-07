<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoIntegration extends Model
{
    protected $fillable = [
        'organization_id',
        'provider',
        'property_url',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'status',
        'data',
        'last_synced_at',
        'last_error',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'data' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeForOrganization($query, ?int $organizationId)
    {
        return $organizationId
            ? $query->where('organization_id', $organizationId)
            : $query->whereNull('organization_id');
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected' && filled($this->refresh_token ?: $this->access_token);
    }
}
