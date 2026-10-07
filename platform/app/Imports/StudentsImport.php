<?php

namespace App\Imports;

use App\Models\Student;
use App\Support\SaasAccess;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Hash;

class StudentsImport implements ToModel, WithHeadingRow
{
    protected $groupId;
    protected $organizationId;

    public function __construct($groupId, $organizationId = null)
    {
        $this->groupId = $groupId;
        $this->organizationId = $organizationId ?: (class_exists(\App\Support\Tenant::class) ? \App\Support\Tenant::id() : null);
    }

    public function model(array $row)
    {
        $registrationNumber = $row['registration_number'] ?? $row['reg_code'] ?? $row['registration'] ?? null;
        $rollNumber = $row['roll_number'] ?? $row['enroll'] ?? $row['roll'] ?? null;
        $email = !empty($row['email']) ? $row['email'] : null;
        $phone = !empty($row['phone']) ? $row['phone'] : null;
        $rollNumber = !empty($rollNumber) ? $rollNumber : null;

        // Name aur registration number compulsory hain. Initial password registration number hoga.
        if (empty($row['name']) || empty($registrationNumber)) {
            return null;
        }

        $student = Student::query()
            ->when($this->organizationId, function ($query, $organizationId) {
                $query->where('organization_id', $organizationId);
            })
            ->where(function ($query) use ($registrationNumber, $rollNumber, $email, $phone) {
                $query->where('reg_code', $registrationNumber);

                if (!empty($rollNumber)) {
                    $query->orWhere('enroll', $rollNumber);
                }

                if (!empty($email)) {
                    $query->orWhere('email', $email);
                }

                if (!empty($phone)) {
                    $query->orWhere('phone', $phone);
                }
            })
            ->first();

        // Agar nahi mila to naya create karo
        if (!$student) {
            SaasAccess::abortIfLimitReached('students');

            $student = Student::create([
                'organization_id' => $this->organizationId,
                'name'            => $row['name'],
                'email'           => $email,
                'phone'           => $phone,
                'address'         => $row['address'] ?? 'N/A',
                'reg_code'        => $registrationNumber,
                'enroll'          => $rollNumber,
                'password'        => Hash::make($registrationNumber),
                'status'          => 'Active',
            ]);
        }

        // Student ko UI se selected group me attach karo (purane groups remove nahi honge)
        $student->groups()->syncWithoutDetaching([$this->groupId]);

        return $student;
    }
}
