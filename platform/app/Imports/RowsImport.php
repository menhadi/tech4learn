<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

class RowsImport implements ToArray
{
    private array $rows = [];

    public function array(array $array)
    {
        $this->rows = $array;
    }

    public function rows(): array
    {
        return $this->rows;
    }
}
