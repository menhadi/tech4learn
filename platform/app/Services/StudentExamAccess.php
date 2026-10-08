<?php

namespace App\Services;

use App\Models\{Exam, Order, Student};

class StudentExamAccess
{
    public function assertAllowed(Exam $exam, Student $student): void
    {
        abort_unless($student->status === 'Active' && (int) $student->organization_id === (int) $exam->organization_id, 404);
        if ($exam->is_student_practice) {
            abort_unless((int) $exam->created_by_student_id === (int) $student->id, 404);
        }
        // Unpackaged papers retain the native organisation access path.
        if (! $exam->packages()->exists()) return;

        $packageIds = $exam->packages()->where('packages.organization_id', $exam->organization_id)
            ->where('packages.status', true)->pluck('packages.id');
        $allowed = Order::where('organization_id', $exam->organization_id)
            ->where('student_id', $student->id)->where('status', 'completed')
            ->whereHas('items', fn ($query) => $query->whereIn('package_id', $packageIds))->exists();
        abort_unless($allowed, 403, 'Activate this course before starting its exam.');
    }
}
