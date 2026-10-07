<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Qtype;

class QtypeSeeder extends Seeder
{
    public function run()
    {
        $qtypes = [
            ['question_type' => 'Multiple Choices', 'type' => 'M'],
            ['question_type' => 'True / False', 'type' => 'T'],
            ['question_type' => 'Fill in the blanks', 'type' => 'F'],
            ['question_type' => 'Numerical Answer Type (NAT)', 'type' => 'NAT'],
            ['question_type' => 'Subjective', 'type' => 'S'],
            ['question_type' => 'Match-Interaction', 'type' => 'I'],
            ['question_type' => 'Audio Interaction', 'type' => 'D'],
            ['question_type' => 'Slider Interaction', 'type' => 'SI'],
            ['question_type' => 'Inline Choice', 'type' => 'IC'],
            ['question_type' => 'Choice Interaction', 'type' => 'CI'],
        ];

        foreach ($qtypes as $qtype) {
            Qtype::updateOrCreate(['type' => $qtype['type']], $qtype);
        }
    }
}