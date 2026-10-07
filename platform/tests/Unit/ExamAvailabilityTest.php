<?php

namespace Tests\Unit;

use App\Models\Exam;
use App\Services\ExamWorkbookService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ExamAvailabilityTest extends TestCase
{
    public function test_exam_without_schedule_is_always_live(): void
    {
        $exam = $this->exam(['start_date' => null, 'end_date' => null]);

        $this->assertSame('Live', $exam->availabilityStatus(Carbon::parse('2026-08-16 12:00:00')));
        $this->assertTrue($exam->isAvailableAt(Carbon::parse('2036-08-16 12:00:00')));
    }

    public function test_each_schedule_boundary_is_independently_optional(): void
    {
        $now = Carbon::parse('2026-08-16 12:00:00');

        $future = $this->exam(['start_date' => $now->copy()->addHour()->format('Y-m-d H:i:s'), 'end_date' => null]);
        $expired = $this->exam(['start_date' => null, 'end_date' => $now->copy()->subHour()->format('Y-m-d H:i:s')]);
        $openEnded = $this->exam(['start_date' => $now->copy()->subHour()->format('Y-m-d H:i:s'), 'end_date' => null]);

        $this->assertSame('Upcoming', $future->availabilityStatus($now));
        $this->assertSame('Expired', $expired->availabilityStatus($now));
        $this->assertSame('Live', $openEnded->availabilityStatus($now));
    }

    public function test_offline_flag_is_available_once_in_workbook_imports(): void
    {
        $headings = (new ExamWorkbookService)->headings();

        $this->assertSame(1, count(array_filter($headings, fn ($heading) => $heading === 'offline_enabled')));
    }
    private function exam(array $attributes): Exam
    {
        $exam = new Exam;
        $exam->setDateFormat('Y-m-d H:i:s');
        $exam->setRawAttributes($attributes);

        return $exam;
    }
}
