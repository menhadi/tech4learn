<?php

namespace App\Contracts;

interface SourceQuestionAdapter
{
    public function key(): string;
    public function supports(string $url): bool;
    public function extract(string $url, array $metadata = []): array;
}
