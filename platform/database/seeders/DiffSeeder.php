<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Diff;

class DiffSeeder extends Seeder
{
    public function run()
    {
        $diffs = [
            ['diff_level' => 'Easy', 'type' => 'E'],
            ['diff_level' => 'Medium', 'type' => 'M'],
            ['diff_level' => 'Hard', 'type' => 'H'],
        ];

        foreach ($diffs as $diff) {
            Diff::create($diff);
        }
    }
}