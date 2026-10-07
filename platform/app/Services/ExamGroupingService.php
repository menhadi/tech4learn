<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamSubjectDuration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExamGroupingService
{
    public const NONE = 'none';
    public const SUBJECT = 'subject';
    public const SECTION = 'section';

    public function groupingMode(Exam $exam): string
    {
        $mode = (string) ($exam->grouping_mode ?: ($exam->timer_mode === self::SECTION ? self::SECTION : self::SUBJECT));
        return in_array($mode, [self::NONE, self::SUBJECT, self::SECTION], true) ? $mode : self::SUBJECT;
    }

    public function mode(Exam $exam): string
    {
        $mode = (string) ($exam->timer_mode ?: ($exam->is_subject_timer ? $this->groupingMode($exam) : self::NONE));
        return in_array($mode, [self::NONE, self::SUBJECT, self::SECTION], true) ? $mode : self::NONE;
    }

    public function decorate(Exam $exam, Collection $questions): Collection
    {
        $groupingMode = $this->groupingMode($exam);
        $sectionAssignments = collect();
        $sections = collect();

        if ($groupingMode === self::SECTION && $questions->isNotEmpty()) {
            $sectionAssignments = DB::table('exam_questions')->where('exam_id', $exam->id)
                ->whereIn('question_id', $questions->pluck('id')->filter()->all())
                ->pluck('exam_section_id', 'question_id');
            $sections = $exam->sections()->get()->keyBy('id');
        }

        $questions->each(function ($question) use ($groupingMode, $sectionAssignments, $sections) {
            $sectionId = null;
            if ($groupingMode === self::SECTION) {
                $sectionId = $sectionAssignments->get($question->id);
                $section = $sectionId ? $sections->get((int) $sectionId) : null;
                $key = $section ? 'section-'.$section->id : 'section-general';
                $name = $section?->name ?: 'General';
            } elseif ($groupingMode === self::SUBJECT) {
                $key = $question->subject_id ? (string) $question->subject_id : 'unknown';
                $name = $question->subject?->subject_name ?: 'General';
            } else {
                $key = 'all';
                $name = 'All Questions';
            }
            $question->setAttribute('exam_group_key', $key);
            $question->setAttribute('exam_group_name', $name);
            $question->setAttribute('exam_section_id', $sectionId ? (int) $sectionId : null);
        });

        return $questions;
    }

    public function durationMap(Exam $exam, Collection $questions, int $availableSeconds): array
    {
        $timerMode = $this->mode($exam);
        if ($timerMode === self::NONE || $questions->isEmpty()) return [];

        $this->decorate($exam, $questions);
        $groups = $questions->unique('exam_group_key')->values();
        if ($groups->isEmpty()) return [];

        $configured = [];
        if ($timerMode === self::SUBJECT) {
            $minutes = ExamSubjectDuration::where('exam_id', $exam->id)->pluck('duration', 'subject_id');
            foreach ($groups as $question) {
                if ($question->subject_id && (int) $minutes->get($question->subject_id) > 0) $configured[$question->exam_group_key] = (int) $minutes->get($question->subject_id) * 60;
            }
        } else {
            $minutes = $exam->sections()->pluck('duration', 'id');
            foreach ($groups as $question) {
                if ($question->exam_section_id && (int) $minutes->get($question->exam_section_id) > 0) $configured[$question->exam_group_key] = (int) $minutes->get($question->exam_section_id) * 60;
            }
        }

        if ($availableSeconds <= 0 && empty($configured)) return [];
        $unconfigured = $groups->reject(fn ($question) => array_key_exists($question->exam_group_key, $configured));
        $remaining = max(0, $availableSeconds - array_sum($configured));
        $automatic = $unconfigured->count() > 0 ? (int) floor($remaining / $unconfigured->count()) : 0;
        $durations = [];
        foreach ($groups as $question) $durations[$question->exam_group_key] = $configured[$question->exam_group_key] ?? $automatic;
        return $durations;
    }
}
