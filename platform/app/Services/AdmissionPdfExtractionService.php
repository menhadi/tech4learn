<?php

namespace App\Services;

use App\Contracts\AdmissionPdfParser;
use InvalidArgumentException;

class AdmissionPdfExtractionService
{
    public function __construct(
        private AdmissionPdfTextExtractor $textExtractor,
        private JeeMainStatisticsParser $jeeStatistics,
        private MccSeatMatrixParser $mccSeatMatrix,
        private MccSeatMatrixPdfExtractor $mccSeatMatrixPdf,
        private MccAllotmentResultPdfExtractor $mccAllotmentResultPdf,
    ) {}

    public function extract(string $examCode, string $resourceKind, string $path): array
    {
        if ($examCode === 'neet-ug' && $resourceKind === 'seat_matrix') {
            return $this->mccSeatMatrixPdf->extract($path);
        }

        if ($examCode === 'neet-ug' && $resourceKind === 'allotment_result') {
            return $this->mccAllotmentResultPdf->extract($path);
        }

        return $this->parser($examCode, $resourceKind)->parse(
            $this->textExtractor->extract($path)
        );
    }

    public function parser(string $examCode, string $resourceKind): AdmissionPdfParser
    {
        return match ($examCode.':'.$resourceKind) {
            'jee-main-paper-1:exam_statistics' => $this->jeeStatistics,
            'neet-ug:seat_matrix' => $this->mccSeatMatrix,
            default => throw new InvalidArgumentException(
                "No PDF parser is registered for [{$examCode}:{$resourceKind}]."
            ),
        };
    }
}
