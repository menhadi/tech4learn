<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'type',
        'host',
        'username',
        'password',
        'port',
        'tls',
        'founder_from_name',
        'founder_from_address',
    ];
}
