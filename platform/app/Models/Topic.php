<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Topic extends Model
{
    use HasFactory;

    protected $fillable = ['subject_id', 'group_id', 'name', 'display_order'];

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function stopics()
    {
        return $this->hasMany(Stopic::class);
    }

    public function questions()
    {
        return $this->hasMany(Question::class);
    }
}
