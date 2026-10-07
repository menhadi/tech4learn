<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\ExamStat;
use App\Models\Language;
use App\Models\Package;
use App\Models\Order;

class NewExamController extends Controller
{
    private function tenantId(): ?int
    {
        return class_exists(\App\Support\Tenant::class) ? \App\Support\Tenant::hostId(request()->getHost()) : null;
    }

    private function examBySlug(string $slug): Exam
    {
        return Exam::where('slug', $slug)
            ->when($this->tenantId(), function ($query, $tenantId) {
                $query->where('organization_id', $tenantId);
            })
            ->firstOrFail();
    }

    public function guideline($slug)
    {
        $exam = $this->examBySlug($slug);
        $languages = Language::enabledForOrganization($exam->organization_id)->orderBy('name')->get();
        
        return view('students.guest_exams.exam_guideline', compact('exam', 'languages'));
    }
    
    public function instructions($slug)
    {
        $exam = $this->examBySlug($slug);
        $languages = Language::enabledForOrganization($exam->organization_id)->orderBy('name')->get();
        
        return view('students.guest_exams.exam_instructions', compact('exam', 'languages'));
    }
    
    public function start($slug)
    {
        $exam = $this->examBySlug($slug);
        $languages = Language::enabledForOrganization($exam->organization_id)->orderBy('name')->get();
        
        if (!session('guest_id')) {
            session(['guest_id' => 'guest_' . uniqid()]);
        }
        
        return view('students.guest_exams.exam_start', compact('exam', 'languages'));
    }
}
