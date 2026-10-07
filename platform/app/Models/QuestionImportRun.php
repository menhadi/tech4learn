<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionImportRun extends Model
{
    protected $fillable = [
        'upload_id', 'organization_id', 'created_by', 'original_name', 'stored_path',
        'status', 'total_bytes', 'total_chunks', 'received_chunks', 'total_rows', 'processed_rows',
        'imported_rows', 'updated_rows', 'duplicate_rows', 'created_records',
        'failed_rows', 'options', 'failure_message', 'error_report_path',
        'processing_started_at', 'completed_at',
    ];

    protected $casts = [
        'options' => 'array',
        'processing_started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $hidden = ['stored_path', 'error_report_path'];
}
