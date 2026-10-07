<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Group;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\StudentHiddenPackage;
use App\Support\SaasAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentCourseController extends Controller
{
    private function tenantId(): ?int
    {
        return class_exists(\App\Support\Tenant::class)
            ? (\App\Support\Tenant::hostId(request()->getHost()) ?: \App\Support\Tenant::id())
            : null;
    }

    private function packageQuery()
    {
        return Package::query()
            ->with([
                'groups:id,group_name',
                'tags:id,name',
                'category:id,title',
                'subcategory:id,title',
                'exams' => fn ($query) => $query->active()->select('exams.id'),
            ])
            ->withCount(['exams' => fn ($query) => $query->active()])
            ->when($this->tenantId(), function ($query, $tenantId) {
                $query->where('organization_id', $tenantId);
            })
            ->when(! $this->paidPackagesAvailable(), function ($query) {
                $query->where('package_type', 'free');
            })
            ->where('status', 1)
            ->whereHas('exams', fn ($query) => $query->active());
    }

    private function paidPackagesAvailable(): bool
    {
        return SaasAccess::isPlatformOrganization() && SaasAccess::featureEnabled('paid_packages');
    }

    public function index(Request $request)
    {
        $student = Auth::guard('student')->user();
        $search = trim((string) $request->input('search', ''));
        $groupId = $request->integer('group') ?: null;
        $price = $request->input('price', 'all');
        $sort = $request->input('sort', 'default');

        $purchasedPackageIds = Order::query()
            ->when($this->tenantId(), function ($query, $tenantId) {
                $query->where(function ($query) use ($tenantId) {
                    $query->where('organization_id', $tenantId)
                        ->orWhereNull('organization_id');
                });
            })
            ->where('student_id', $student->id)
            ->where('status', 'completed')
            ->whereHas('items')
            ->with('items:id,order_id,package_id')
            ->get()
            ->flatMap(fn ($order) => $order->items->pluck('package_id'))
            ->filter()
            ->unique()
            ->values();

        $packages = $this->packageQuery()
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%');
            })
            ->when($groupId, function ($query) use ($groupId) {
                $query->whereHas('groups', fn ($groupQuery) => $groupQuery->where('groups.id', $groupId));
            })
            ->when(in_array($price, ['free', 'paid'], true), function ($query) use ($price) {
                $query->where('package_type', $price);
            })
            ->when($sort === 'name_asc', fn ($query) => $query->orderBy('name'))
            ->when($sort === 'name_desc', fn ($query) => $query->orderByDesc('name'))
            ->when($sort === 'price_low_high', fn ($query) => $query->orderByRaw('COALESCE(discounted_amount, amount, 0) asc'))
            ->when($sort === 'price_high_low', fn ($query) => $query->orderByRaw('COALESCE(discounted_amount, amount, 0) desc'))
            ->when($sort === 'default', fn ($query) => $query->latest())
            ->paginate(12)
            ->withQueryString();

        $allGroups = Group::query()
            ->when($this->tenantId(), function ($query, $tenantId) {
                $query->where('organization_id', $tenantId);
            })
            ->whereHas('packages', function ($query) {
                $query->where('status', 1)
                    ->whereHas('exams', fn ($examQuery) => $examQuery->active());
            })
            ->orderBy('group_name')
            ->get();

        return view('students.courses.index', compact('packages', 'purchasedPackageIds', 'search', 'allGroups', 'groupId', 'price', 'sort'));
    }

    public function enroll(Package $package)
    {
        $student = Auth::guard('student')->user();
        $package = $this->packageQuery()->whereKey($package->id)->firstOrFail();

        $alreadyPurchased = Order::query()
            ->when($this->tenantId(), function ($query, $tenantId) {
                $query->where(function ($query) use ($tenantId) {
                    $query->where('organization_id', $tenantId)
                        ->orWhereNull('organization_id');
                });
            })
            ->where('student_id', $student->id)
            ->where('status', 'completed')
            ->whereHas('items', fn ($query) => $query->where('package_id', $package->id))
            ->exists();

        if ($alreadyPurchased) {
            $restored = StudentHiddenPackage::where('student_id', $student->id)
                ->where('package_id', $package->id)
                ->delete();

            return redirect()->route('student.myexams')->with(
                $restored ? 'success' : 'info',
                $restored ? 'Course restored to My Exams.' : 'This course is already active in My Exams.'
            );
        }

        if (strtolower((string) $package->package_type) === 'free') {
            $order = Order::create([
                'organization_id' => $this->tenantId(),
                'student_id' => $student->id,
                'total' => 0,
                'discount' => 0,
                'coupon_code' => null,
                'payment_method' => 'free',
                'payment_status' => 'Completed',
                'status' => 'completed',
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'package_id' => $package->id,
                'name' => $package->name,
                'price' => 0,
                'quantity' => 1,
                'total' => 0,
            ]);

            $student->groups()->syncWithoutDetaching($package->groups->pluck('id')->all());
            Cart::where('student_id', $student->id)->where('package_id', $package->id)->delete();

            return redirect()->route('student.myexams')
                ->with('success', 'Course activated successfully. You can take the exam now.')
                ->with('new_purchase', true);
        }

        $price = (! empty($package->discounted_amount) && $package->discounted_amount > 0)
            ? (float) $package->discounted_amount
            : (float) ($package->amount ?? 0);

        Cart::where('student_id', $student->id)->delete();
        Cart::create([
            'student_id' => $student->id,
            'package_id' => $package->id,
            'name' => $package->name,
            'price' => $price,
            'image' => $package->photo ? asset($package->photo) : asset('build/images/small/img-1.jpg'),
            'quantity' => 1,
        ]);

        return redirect()->route('checkout.index');
    }
}
