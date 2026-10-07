<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PassageLang extends Model
{
    use HasFactory;

    protected $fillable = [
        'passage_id',
        'language_id',
        'passage',
    ];

    public function passage()
    {
        return $this->belongsTo(Passage::class);
    }

    public function language()
    {
        return $this->belongsTo(Language::class);
    }
}