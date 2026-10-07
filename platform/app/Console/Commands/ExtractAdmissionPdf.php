<?php

namespace App\Console\Commands;

use App\Services\AdmissionPdfExtractionService;
use Illuminate\Console\Command;
use Throwable;

class ExtractAdmissionPdf extends Command
{
    protected $signature = 'admission-prediction:extract-pdf
        {exam : Configured exam code}
        {kind : Official resource kind}
        {path : Local PDF path}
        {--sample=3 : Number of sample rows to print}
        {--diagnostics : Print validation counts by source page}';

    protected $description = 'Dry-run an admission PDF extractor without publishing rows';

    public function handle(AdmissionPdfExtractionService $extractor): int
    {
        try {
            $records = $extractor->extract(
                (string) $this->argument('exam'),
                (string) $this->argument('kind'),
                (string) $this->argument('path')
            );

            $this->info('Extracted records: '.count($records));
            if ($this->option('diagnostics')) {
                $byPage = [];
                foreach ($records as $record) {
                    $page = (int) data_get($record, 'provenance.page', 0);
                    $byPage[$page] = ($byPage[$page] ?? 0) + 1;
                }
                ksort($byPage);
                $this->line('Records by page: '.json_encode($byPage));
                if ((string) $this->argument('kind') === 'seat_matrix') {
                    $this->line('Total seats: '.array_sum(array_column($records, 'seat_count')));
                    $this->line('Rows missing institution code: '.count(array_filter(
                        $records,
                        fn ($record) => empty($record['institution_code'])
                    )));
                }
            }
            $sample = array_slice($records, 0, max(0, (int) $this->option('sample')));
            foreach ($sample as $index => $record) {
                $this->line(json_encode(
                    ['row' => $index + 1] + $record,
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                ));
            }

            return $records === [] ? self::FAILURE : self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
