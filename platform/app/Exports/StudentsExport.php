<?php

namespace App\Exports;

use App\Models\Student;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Support\Facades\Schema;

class StudentsExport implements FromCollection, WithHeadings, WithMapping
{
    private function tenantId(): ?int
    {
        return class_exists(\App\Support\Tenant::class) ? \App\Support\Tenant::id() : null;
    }

    protected $request;

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function collection()
    {
        $query = Student::query();
        $tenantId = $this->tenantId();

        if ($tenantId) {
            $query->where('organization_id', $tenantId);
        }

        if (Schema::hasColumn('students', 'is_demo')) {
            $query->where(function ($studentQuery) {
                $studentQuery->whereNull('is_demo')->orWhere('is_demo', false);
            });
        }
        
        $groupIds = getUserGroupIds(); 
        if (!empty($groupIds)) {
            $query->whereHas('groups', function ($q) use ($groupIds) {
                $q->whereIn('group_id', $groupIds);
            });
        }

        return $query->latest()->get();
    }

    public function headings(): array
    {
        return ['ID', 'Name', 'Registration Number', 'Roll Number', 'Email', 'Phone', 'Address', 'Status', 'Registered Date'];
    }

    public function map($student): array
    {
        return [
            $student->id,
            $student->name,
            $student->reg_code,
            $student->enroll,
            $student->email,
            $student->phone,
            $student->address,
            $student->status,
            $student->created_at ? $student->created_at->format('Y-m-d') : '',
        ];
    }
}
