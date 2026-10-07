<?php

namespace App\Imports;

use Closure;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;

class ExamWorkbookRowsImport implements OnEachRow, WithHeadingRow, WithChunkReading, SkipsEmptyRows
{
    public function __construct(private readonly Closure $handler) {}

    public function onRow(Row $row): void
    {
        ($this->handler)($row->getIndex(), $row->toArray());
    }

    public function chunkSize(): int
    {
        return 200;
    }
}
