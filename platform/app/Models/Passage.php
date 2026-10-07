<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Passage extends Model
{
    use HasFactory;
    protected $fillable = [
        'organization_id',
        'name',
    ];

    public function langs()
    {
        return $this->hasMany(PassageLang::class);
    }
}
