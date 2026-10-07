<?php

namespace App\Jobs;

use App\Models\ImageConversionItem;
use App\Services\QuestionImageConversionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ConvertQuestionImageToPng implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(public int $itemId) {}

    public function handle(QuestionImageConversionService $service): void
    {
        $item = ImageConversionItem::with('run')->find($this->itemId);
        if ($item && in_array($item->status, ['queued', 'processing'], true)) $service->convert($item);
    }
}
