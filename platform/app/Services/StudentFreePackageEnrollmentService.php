<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StudentFreePackageEnrollmentService
{
    public function enrollForGroup(
        Student $student,
        int $groupId,
        ?int $organizationId = null,
        ?Request $request = null
    ): Collection {
        $packages = Package::query()
            ->when($organizationId, fn ($query, $id) => $query->where('organization_id', $id))
            ->where('package_type', 'free')
            ->where('auto_enroll_on_registration', true)
            ->where('status', 1)
            ->whereHas('groups', fn ($query) => $query->where('groups.id', $groupId))
            ->whereHas('exams', function ($query) use ($organizationId) {
                $query->when($organizationId, fn ($examQuery, $id) => $examQuery->where('exams.organization_id', $id))
                    ->whereIn('exams.status', ['Active', 'active', 'ACTIVE', '1', 1, true, 'Published', 'published', 'Enabled', 'enabled']);
            })
            ->get();

        if ($packages->isEmpty()) {
            return collect();
        }

        $existingPackageIds = OrderItem::query()
            ->whereIn('package_id', $packages->pluck('id'))
            ->whereHas('order', function ($query) use ($student, $organizationId) {
                $query->where('student_id', $student->id)
                    ->where('status', 'completed')
                    ->when($organizationId, function ($orderQuery, $id) {
                        $orderQuery->where(function ($tenantQuery) use ($id) {
                            $tenantQuery->where('organization_id', $id)
                                ->orWhereNull('organization_id');
                        });
                    });
            })
            ->pluck('package_id')
            ->map(fn ($id) => (int) $id)
            ->unique();

        $newPackages = $packages->reject(fn ($package) => $existingPackageIds->contains((int) $package->id))->values();

        if ($newPackages->isEmpty()) {
            return collect();
        }

        DB::transaction(function () use ($student, $groupId, $organizationId, $request, $newPackages) {
            $order = Order::create([
                'organization_id' => $organizationId,
                'student_id' => $student->id,
                'total' => 0,
                'discount' => 0,
                'coupon_code' => null,
                'payment_method' => 'free',
                'payment_status' => 'paid',
                'status' => 'completed',
            ]);

            foreach ($newPackages as $package) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'package_id' => $package->id,
                    'name' => $package->name,
                    'price' => 0,
                    'quantity' => 1,
                ]);

                StudentActivityTracker::track(StudentActivityTracker::PACKAGE_ENROLLED, [
                    'organization_id' => $organizationId,
                    'student_id' => $student->id,
                    'package_id' => $package->id,
                    'order_id' => $order->id,
                    'metadata' => [
                        'enrollment_type' => 'auto_free_on_group_selection',
                        'group_id' => $groupId,
                    ],
                ], $request);
            }
        });

        return $newPackages;
    }

    public function enrollPackage(
        Student $student,
        Package $package,
        ?int $organizationId = null,
        ?Request $request = null,
        string $enrollmentType = 'requested_free_package'
    ): bool {
        if (strtolower((string) $package->package_type) !== 'free' || ! $package->status) {
            return false;
        }

        if ($organizationId && (int) $package->organization_id !== $organizationId) {
            return false;
        }

        $alreadyOwned = OrderItem::query()
            ->where('package_id', $package->id)
            ->whereHas('order', function ($query) use ($student, $organizationId) {
                $query->where('student_id', $student->id)
                    ->where('status', 'completed')
                    ->when($organizationId, fn ($orderQuery, $id) => $orderQuery->where(function ($tenantQuery) use ($id) {
                        $tenantQuery->where('organization_id', $id)->orWhereNull('organization_id');
                    }));
            })
            ->exists();

        if ($alreadyOwned) {
            return true;
        }

        DB::transaction(function () use ($student, $package, $organizationId, $request, $enrollmentType) {
            $order = Order::create([
                'organization_id' => $organizationId,
                'student_id' => $student->id,
                'total' => 0,
                'discount' => 0,
                'coupon_code' => null,
                'payment_method' => 'free',
                'payment_status' => 'paid',
                'status' => 'completed',
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'package_id' => $package->id,
                'name' => $package->name,
                'price' => 0,
                'quantity' => 1,
            ]);

            StudentActivityTracker::track(StudentActivityTracker::PACKAGE_ENROLLED, [
                'organization_id' => $organizationId,
                'student_id' => $student->id,
                'package_id' => $package->id,
                'order_id' => $order->id,
                'metadata' => ['enrollment_type' => $enrollmentType],
            ], $request);
        });

        return true;
    }
}
