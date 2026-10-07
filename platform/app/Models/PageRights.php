<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PageRights extends Model
{
    use HasFactory;

    protected $fillable = [
        'page_id',
        'ugroup_id',
        'view_right',
        'add_right',
        'edit_right',
        'delete_right',
    ];

    public function page()
    {
        return $this->belongsTo(Page::class);
    }

    public function ugroup()
    {
        return $this->belongsTo(Ugroup::class);
    }
}
