<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasFactory;

    public function pageRights()
    {
        return $this->hasMany(PageRights::class);
    }

    public function children()
    {
        return $this->hasMany(Page::class, 'parent_id');
    }
}
