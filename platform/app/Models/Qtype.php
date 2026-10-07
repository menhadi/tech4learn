<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Qtype extends Model
{
    use HasFactory;
    protected $fillable = [
        'question_type',
        'type',
    ];

    public static function displayOrdered($columns = ['*'])
    {
        return static::query()
            ->get($columns)
            ->sortBy(function ($qtype) {
                $label = strtolower(trim((string) $qtype->question_type));
                $code = strtoupper(trim((string) $qtype->type));

                return match (true) {
                    $code === 'M', str_contains($label, 'multiple') => 10,
                    $code === 'B', str_contains($label, 'fill') => 20,
                    $code === 'NAT', str_contains($label, 'numerical') => 25,
                    $code === 'S', str_contains($label, 'subjective') => 30,
                    $code === 'T', str_contains($label, 'true') => 40,
                    default => 90,
                };
            })
            ->values();
    }
}
