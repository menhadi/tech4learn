<?php

namespace App\Exports;

use App\Models\Exam;
use App\Services\ExamWorkbookService;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExamWorkbookExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithEvents
{
    public function __construct(
        private readonly ExamWorkbookService $service,
        private readonly int $organizationId,
        private readonly array $filters = [],
    ) {}

    public function query(): Builder
    {
        return $this->service->exportQuery($this->organizationId, $this->filters);
    }

    public function headings(): array
    {
        return $this->service->headings();
    }

    public function map($exam): array
    {
        /** @var Exam $exam */
        return $this->service->exportRow($exam);
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0F766E']],
        ]];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $sheet->freezePane('A2');
            $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
            $sheet->getRowDimension(1)->setRowHeight(28);
            $lastColumn = Coordinate::columnIndexFromString($sheet->getHighestColumn());
            for ($index = 1; $index <= $lastColumn; $index++) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setWidth($index <= 37 ? 18 : 22);
            }
        }];
    }
}
