<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stopic extends Model
{
    use HasFactory;

    protected $fillable = ['subject_id', 'group_id', 'topic_id', 'name', 'display_order'];

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function topic()
    {
        return $this->belongsTo(Topic::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function questions()
    {
        return $this->hasMany(Question::class, 'stopic_id');
    }
}
