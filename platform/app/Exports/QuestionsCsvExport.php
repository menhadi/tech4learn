<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuestionsCsvExport
{
    public function __construct(
        private Builder $query,
        private ?int $groupId = null,
    ) {
    }

    public function download(string $filename): StreamedResponse
    {
        $query = clone $this->query;
        $mapper = new QuestionsExport($query, $this->groupId);

        return response()->streamDownload(function () use ($query, $mapper) {
            if (function_exists('ignore_user_abort')) ignore_user_abort(true);

            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $mapper->headings(), ',', '"', '');

            $query->reorder()->chunkById(250, function ($questions) use ($output, $mapper) {
                foreach ($questions as $question) {
                    fputcsv($output, $mapper->map($question), ',', '"', '');
                }

                fflush($output);
                if (! app()->environment('testing')) {
                    if (ob_get_level() > 0) @ob_flush();
                    flush();
                }
            }, 'questions.id', 'id');

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}