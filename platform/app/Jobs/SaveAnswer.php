<?php

namespace App\Jobs;

use App\Models\ExamStat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

class SaveAnswer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function handle()
    {
        $examStat = ExamStat::where('exam_result_id', $this->data['exam_result_id'])
            ->where('question_id', $this->data['question_id'])
            ->first();

        if ($examStat) {
            $questionType = $this->data['question_type'];
            $answerData = null;

            if ($questionType === 'true_false') {
                $answerData = ['true_false' => $this->data['option_selected']];
            } elseif ($questionType === 'multiple_choice_checkbox' || $questionType === 'multiple_choice_radio') {
                $indices = collect((array) $this->data['option_selected'])->map(fn ($value) => (int) $value)->filter(fn ($value) => $value >= 1 && $value <= 6)->unique()->sort()->values()->all();
                $answerData = ['selected_option_indices' => $indices];
            } else {
                $answerData = ['answer' => $this->data['option_selected']];
            }

            $examStat->update(array_merge($answerData, [
                'attempt_time' => Carbon::now(),
                'opened' => $this->data['opened'],
                'answered' => $this->data['answered'],
                'review' => $this->data['review'],
                'time_taken' => $this->data['time_taken'],
            ]));
        }
    }

    public function failed()
    {

    }
}