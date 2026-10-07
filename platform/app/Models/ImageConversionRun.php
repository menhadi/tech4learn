<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImageConversionRun extends Model
{
    protected $fillable = [
        'organization_id', 'requested_by', 'status', 'total_images',
        'processed_images', 'success_count', 'failed_count', 'completed_at',
    ];

    protected $casts = ['completed_at' => 'datetime'];

    public function requester() { return $this->belongsTo(User::class, 'requested_by'); }
    public function items() { return $this->hasMany(ImageConversionItem::class, 'run_id'); }
}
