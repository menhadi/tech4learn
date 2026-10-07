<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Order;
use App\Models\Package;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentPostAuthService
{
    private const SESSION_KEY = 'student_post_auth_intent';

    public function __construct(private StudentFreePackageEnrollmentService $freePackageEnrollmentService)
    {
    }

    public function remember(array $values): void
    {
        $allowed = array_intersect_key($values, array_flip([
            'action', 'package_id', 'exam_id', 'exam_result_id', 'group_id',
            'quick_quiz_session_id', 'question_id',
        ]));

        $intent = array_filter(
            array_merge((array) session(self::SESSION_KEY, []), $allowed),
            fn ($value) => $value !== null && $value !== ''
        );

        if ($intent) {
            session([self::SESSION_KEY => $intent]);
        }
    }

    public function capture(Request $request): void
    {
        $action = (string) $request->input('action', '');
        if (in_array($action, ['activate_free_package', 'solution_pdf', 'start_exam', 'checkout', 'view_result', 'quick_quiz'], true)) {
            $this->remember([
                'action' => $action,
                'package_id' => $request->integer('package_id') ?: null,
                'exam_id' => $request->integer('exam_id') ?: null,
                'group_id' => $request->integer('group_id') ?: null,
                'quick_quiz_session_id' => $request->input('quick_quiz_session_id'),
                'question_id' => $request->integer('question_id') ?: null,
            ]);
        }

        $resultId = $request->integer('exam_result_id') ?: $request->integer('examResultId');
        if ($resultId) {
            $this->remember([
                'action' => 'view_result',
                'exam_result_id' => $resultId,
            ]);
        }

        if ($request->input('redirect') === 'checkout') {
            $this->remember(['action' => 'checkout']);
        }
    }

    public function intent(): array
    {
        return (array) session(self::SESSION_KEY, []);
    }

    public function hasIntent(): bool
    {
        return $this->intent() !== [];
    }

    public function clear(): void
    {
        session()->forget([self::SESSION_KEY, 'auth_redirect_to']);
    }

    public function inferredGroupId(?int $organizationId = null): ?int
    {
        $intent = $this->intent();
        $package = $this->resolvePackage($intent, $organizationId);

        if (! $package) {
            return isset($intent['group_id']) ? (int) $intent['group_id'] : null;
        }

        $requestedGroupId = isset($intent['group_id']) ? (int) $intent['group_id'] : null;
        if ($requestedGroupId && $package->groups()->whereKey($requestedGroupId)->exists()) {
            return $requestedGroupId;
        }

        return $package->groups()->value('groups.id');
    }

    public function complete(Student $student, Request $request): ?array
    {
        $this->mergeSessionCart($student);

        $intent = $this->intent();
        if ($intent === []) {
            return null;
        }

        $organizationId = (int) ($student->organization_id ?: 0) ?: null;
        $guestId = session('guest_id') ?: $request->cookie('guest_id');
        $result = null;
        $package = null;

        DB::transaction(function () use ($student, $request, $intent, $organizationId, $guestId, &$result, &$package) {
            if (! empty($intent['exam_result_id']) && $guestId) {
                $result = ExamResult::query()
                    ->whereKey((int) $intent['exam_result_id'])
                    ->where('guest_id', $guestId)
                    ->where(function ($query) use ($student) {
                        $query->whereNull('student_id')->orWhere('student_id', $student->id);
                    })
                    ->when($organizationId, fn ($query, $id) => $query->where('organization_id', $id))
                    ->first();

                if ($result && ! $result->student_id) {
                    $result->student_id = $student->id;
                    $result->save();

                    DB::table('exam_stats')
                        ->where('exam_result_id', $result->id)
                        ->update(['student_id' => $student->id]);
                }
            }

            $package = $this->resolvePackage($intent, $organizationId, $result, $guestId);

            if ($package && $guestId) {
                Order::query()
                    ->where('guest_id', $guestId)
                    ->when($organizationId, fn ($query, $id) => $query->where(function ($tenantQuery) use ($id) {
                        $tenantQuery->where('organization_id', $id)->orWhereNull('organization_id');
                    }))
                    ->whereHas('items', fn ($query) => $query->where('package_id', $package->id))
                    ->update(['student_id' => $student->id, 'guest_id' => null]);
            }

            if ($package && strtolower((string) $package->package_type) === 'free') {
                $groupId = $this->groupForPackage($package, $intent);
                if ($groupId) {
                    $student->groups()->syncWithoutDetaching([$groupId]);
                }

                $this->freePackageEnrollmentService->enrollPackage(
                    $student,
                    $package,
                    $organizationId,
                    $request,
                    'post_auth_requested_package'
                );
            }
        });

        $this->clear();

        $action = $intent['action'] ?? null;
        if ($action === 'view_result' && $result) {
            return ['url' => route('student.results.view', ['id' => $result->id]), 'handled' => true];
        }

        if ($action === 'solution_pdf' && $package && ! empty($intent['exam_id'])) {
            $exam = Exam::query()
                ->when($organizationId, fn ($query, $id) => $query->where('organization_id', $id))
                ->find((int) $intent['exam_id']);
            if ($exam) {
                return [
                    'url' => route('student.exam.solution.download', [
                        'id' => $exam->slug ?: $exam->id,
                        'package' => $package->slug ?: $package->id,
                    ]),
                    'handled' => true,
                ];
            }
        }

        if ($action === 'start_exam' && ! empty($intent['exam_id'])) {
            $exam = Exam::query()
                ->when($organizationId, fn ($query, $id) => $query->where('organization_id', $id))
                ->find((int) $intent['exam_id']);
            if ($exam) {
                return ['url' => route('student.instructions', ['id' => $exam->slug ?: $exam->id]), 'handled' => true];
            }
        }

        if ($action === 'checkout') {
            return ['url' => route('checkout.index'), 'handled' => true];
        }

        if ($action === 'quick_quiz' && ! empty($intent['quick_quiz_session_id'])) {
            return [
                'url' => route('student.quick-quizzes', array_filter([
                    'quick_quiz' => $intent['quick_quiz_session_id'],
                    'explain' => $intent['question_id'] ?? null,
                ])),
                'handled' => true,
            ];
        }

        if ($action === 'activate_free_package' && $package) {
            return ['url' => route('courses.detail', ['id' => $package->slug ?: $package->id]), 'handled' => true];
        }

        return ['url' => route('student.myexams'), 'handled' => (bool) $package];
    }

    private function resolvePackage(
        array $intent,
        ?int $organizationId,
        ?ExamResult $result = null,
        ?string $guestId = null
    ): ?Package {
        if (! empty($intent['package_id'])) {
            return Package::query()
                ->when($organizationId, fn ($query, $id) => $query->where('organization_id', $id))
                ->where('status', 1)
                ->find((int) $intent['package_id']);
        }

        if ($result && $guestId) {
            $packageId = DB::table('orders')
                ->join('order_items', 'order_items.order_id', '=', 'orders.id')
                ->join('exam_packages', 'exam_packages.package_id', '=', 'order_items.package_id')
                ->where('orders.guest_id', $guestId)
                ->where('exam_packages.exam_id', $result->exam_id)
                ->when($organizationId, fn ($query, $id) => $query->where(function ($tenantQuery) use ($id) {
                    $tenantQuery->where('orders.organization_id', $id)->orWhereNull('orders.organization_id');
                }))
                ->orderByDesc('orders.id')
                ->value('order_items.package_id');

            if ($packageId) {
                return Package::query()
                    ->when($organizationId, fn ($query, $id) => $query->where('organization_id', $id))
                    ->find($packageId);
            }
        }

        if (! empty($intent['exam_id'])) {
            return Package::query()
                ->when($organizationId, fn ($query, $id) => $query->where('organization_id', $id))
                ->whereHas('exams', fn ($query) => $query->where('exams.id', (int) $intent['exam_id']))
                ->where('status', 1)
                ->first();
        }

        return null;
    }

    private function groupForPackage(Package $package, array $intent): ?int
    {
        $requested = isset($intent['group_id']) ? (int) $intent['group_id'] : null;
        if ($requested && $package->groups()->whereKey($requested)->exists()) {
            return $requested;
        }

        return $package->groups()->value('groups.id');
    }

    private function mergeSessionCart(Student $student): void
    {
        $sessionCart = (array) session('cart', []);
        if ($sessionCart === []) {
            return;
        }

        foreach ($sessionCart as $packageId => $details) {
            Cart::updateOrCreate(
                ['student_id' => $student->id, 'package_id' => (int) $packageId],
                [
                    'name' => $details['name'] ?? '',
                    'price' => $details['price'] ?? 0,
                    'image' => $details['image'] ?? null,
                    'quantity' => max(1, (int) ($details['quantity'] ?? 1)),
                ]
            );
        }

        session()->forget('cart');
    }
}
