<?php

namespace App\Http\Controllers;

use App\Models\PaymentGateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Razorpay\Api\Api; 

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Cart;
use App\Models\Configuration;
use App\Models\Student;
use App\Models\Package;
use App\Models\Coupon; 
use App\Models\EmailTemplate;
use App\Services\EmailService;
use App\Support\SaasAccess;
use App\Services\StudentPostAuthService;
use Carbon\Carbon; 

class CheckoutController extends Controller
{
    public function __construct(private StudentPostAuthService $postAuthService)
    {
    }

    private function currentTenantId(): ?int
    {
        if (! class_exists(\App\Support\Tenant::class)) {
            return null;
        }

        return \App\Support\Tenant::hostId(request()->getHost()) ?: \App\Support\Tenant::id();
    }

    private function tenantPackageQuery()
    {
        $query = Package::query();
        $tenantId = $this->currentTenantId();

        if ($tenantId) {
            $query->where('organization_id', $tenantId);
        }

        if (! $this->paidPackagesAvailable()) {
            $query->where('package_type', 'free');
        }

        return $query;
    }

    private function paidPackagesAvailable(): bool
    {
        return SaasAccess::isPlatformOrganization() && SaasAccess::featureEnabled('paid_packages');
    }

    public function index()
    {
        $student = Auth::guard('student')->user();
        
        if ($student) {
            $cartItems = Cart::where('student_id', $student->id)->get();
        } else {
            $sessionCart = session()->get('cart', []);
            $cartItems = collect($sessionCart)->map(function ($item) {
                $cartItem = new Cart();
                $cartItem->fill($item);
                return $cartItem;
            });
        }
        
        if ($cartItems->isEmpty()) {
            return redirect()->route('courses.index')->with('info', 'Your cart is empty.');
        }

        $packageIds = $cartItems->pluck('package_id')->filter()->unique()->values();
        $packages = $this->tenantPackageQuery()->whereIn('id', $packageIds)->get()->keyBy('id');

        if ($packages->count() !== $packageIds->count()) {
            return redirect()->route('courses.index')->with('error', 'One or more packages in your cart are no longer available.');
        }

        $cartItems = $cartItems->map(function ($item) use ($packages) {
            $packageId = $item->package_id ?? $item['package_id'] ?? null;
            $package = $packages->get($packageId);

            if ($package) {
                $price = (!empty($package->discounted_amount) && $package->discounted_amount > 0)
                    ? (float) $package->discounted_amount
                    : (float) ($package->amount ?? 0);
            } else {
                $price = (float) ($item->price ?? $item['price'] ?? 0);
            }

            $quantity = (int) ($item->quantity ?? $item['quantity'] ?? 1);
            $total = $price * $quantity;

            $item->price = $price;
            $item->quantity = $quantity;
            $item->total = $total;

            return $item;
        });

        $subtotal = $cartItems->sum(function ($item) {
            return (float) ($item->total ?? 0);
        });
        $discount = session('coupon.discount', 0);
        $finalAmount = max(0, $subtotal - $discount);

        $configuration = Configuration::when($this->currentTenantId(), function ($query, $tenantId) {
                $query->where('organization_id', $tenantId);
            })
            ->first();
        $gateway = $this->paidPackagesAvailable() ? PaymentGateway::where('status', 1)->first() : null;

        return view('website.checkout', compact('cartItems', 'student', 'configuration', 'gateway', 'subtotal', 'discount', 'finalAmount'));
    }

    public function store(Request $request)
    {
        $rules = [
            'payment_method' => 'required|in:razorpay,offline', 
        ];

        if (!Auth::guard('student')->check()) {
            $rules['name'] = 'required|string|max:255';
            $rules['email'] = 'required|email|max:255'; 
            $rules['phone'] = 'required|string|max:20';
            $rules['password'] = 'required|string|min:6';
        }

        $request->validate($rules);

        // 1. Identify or Create Student
        if (Auth::guard('student')->check()) {
            $student = Auth::guard('student')->user();
        } else {
            // Guest Logic
            $existingStudent = Student::where('email', $request->email)
                ->when($this->currentTenantId(), function ($query, $tenantId) {
                    $query->where('organization_id', $tenantId);
                })
                ->first();
            if ($existingStudent) {
                return redirect()->back()->with('error', 'Email already registered. Please login to continue.')->withInput();
            }

            $student = Student::create([
                'organization_id' => $this->currentTenantId(),
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password), 
                'status' => 1,
            ]);

            Auth::guard('student')->login($student);

            // Merge Session Cart
            $sessionCart = session()->get('cart', []);
            foreach ($sessionCart as $item) {
                Cart::create([
                    'student_id' => $student->id,
                    'package_id' => $item['package_id'],
                    'name' => $item['name'],
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'image' => $item['image'] ?? null,
                ]);
            }
            session()->forget('cart');
        }

        // 2. Re-fetch Cart
        $cartItems = Cart::where('student_id', $student->id)->get();
        if ($cartItems->isEmpty()) {
            return redirect()->route('courses.index')->with('error', 'Cart is empty.');
        }

        $packageIds = $cartItems->pluck('package_id')->filter()->unique()->values();
        $packages = $this->tenantPackageQuery()->whereIn('id', $packageIds)->get()->keyBy('id');

        if ($packages->count() !== $packageIds->count()) {
            return redirect()->route('courses.index')->with('error', 'One or more packages in your cart are no longer available.');
        }

        // 3. Calculate Total
        $subTotal = $cartItems->sum(function($item) use ($packages) {
            $package = $packages->get($item->package_id);
            $price = (!empty($package->discounted_amount) && $package->discounted_amount > 0)
                ? (float) $package->discounted_amount
                : (float) ($package->amount ?? 0);

            return $price * (int) $item->quantity;
        });
        
        $discount = 0;
        $couponCode = null;
        if (session()->has('applied_coupon')) {
            $couponData = session()->get('applied_coupon');
            $discount = $couponData['discount'];
            $couponCode = $couponData['code'];
        }

        $totalAmount = max(0, $subTotal - $discount);

        // 4. Determine Payment Status
        $paymentStatus = 'Pending';
        $orderStatus = 'pending';

        // --- CASE A: Free Order ---
        if ($totalAmount == 0) {
            $paymentStatus = 'Completed';
            $orderStatus = 'completed'; 
        }
        // --- CASE B: Razorpay Verification ---
        elseif ($request->payment_method == 'razorpay' && $request->has('razorpay_payment_id') && $this->paidPackagesAvailable()) {
            $gateway = PaymentGateway::where('status', 1)->first();
            
            if ($gateway) {
                try {
                    $api = new Api($gateway->key_id, $gateway->key_secret);
                    
                    // Signature verify karein
                    $attributes = [
                        'razorpay_order_id' => $request->razorpay_order_id,
                        'razorpay_payment_id' => $request->razorpay_payment_id,
                        'razorpay_signature' => $request->razorpay_signature
                    ];
                    
                    $api->utility->verifyPaymentSignature($attributes);
                    
                    // Payment Verified!
                    $paymentStatus = 'Completed';
                    $orderStatus = 'completed';
                    
                } catch (\Exception $e) {
                    return redirect()->back()->with('error', 'Payment Verification Failed: ' . $e->getMessage());
                }
            }
        }

        // 5. Create Order
        // ✅ FIX 1: 'total_amount' ko 'total' kiya (DB Column Fix)
        $order = Order::create([
            'organization_id' => $this->currentTenantId(),
            'student_id' => $student->id,
            'total' => $totalAmount, // <-- Changed from total_amount to total
            'discount' => $discount, 
            'coupon_code' => $couponCode, 
            'payment_method' => ($totalAmount == 0) ? 'free' : $request->payment_method,
            'payment_status' => $paymentStatus,
            'status' => $orderStatus,
        ]);

        // 6. Order Items
        foreach ($cartItems as $item) {
            $package = $packages->get($item->package_id);
            $price = (!empty($package->discounted_amount) && $package->discounted_amount > 0)
                ? (float) $package->discounted_amount
                : (float) ($package->amount ?? 0);

            OrderItem::create([
                'order_id' => $order->id,
                'package_id' => $item->package_id,
                'name' => $package->name,
                'price' => $price,
                'quantity' => $item->quantity,
                'total' => $price * $item->quantity,
            ]);
        }

        if ($orderStatus === 'completed') {
            $groupIds = $packages->flatMap(fn ($package) => $package->groups()->pluck('groups.id'))
                ->map(fn ($id) => (int) $id)->unique()->values()->all();
            if ($groupIds !== []) {
                $student->groups()->syncWithoutDetaching($groupIds);
            }
        }
        Cart::where('student_id', $student->id)->delete();
        $this->postAuthService->clear();
        session()->forget('applied_coupon');

        // Email Send
        $this->sendOrderEmail($student, $order);

        // ✅ FIX 2: Added 'new_purchase' session flash for Dashboard Popup
        // return redirect()->route('student.dashboard')
        //     ->with('success', 'Order placed successfully! Course Activated.')
        //     ->with('new_purchase', true);

        return redirect()->route('student.myexams')
            ->with('success', 'Order placed successfully! Course Activated.')
            ->with('new_purchase', true);
            
    }

    private function sendOrderEmail($student, $order) {
        try {
            $template = EmailTemplate::where('key', 'order_confirmation')->first(); 
            $config = Configuration::when($this->currentTenantId(), function ($query, $tenantId) {
                    $query->where('organization_id', $tenantId);
                })
                ->first();
            
            if ($template && $config) {
                $html = $template->description;
                $subject = $template->subject;
                
                $itemsHtml = '';
                foreach ($order->items as $item) {
                    $itemsHtml .= '<li style="padding: 5px 0;">' . htmlspecialchars($item->name ?? 'Package') . '</li>';
                }

                $replacements = [
                    '{#studentName#}' => $student->name,
                    '{#orderId#}' => $order->id,
                    '{#orderItems#}' => $itemsHtml,
                '{#totalAmount#}' => number_format($order->total, 2),
                '{#siteEmailContact#}' => $config->email,
                '{#dashboardLink#}' => route('student.dashboard'),
                    // ✅ FIX 3: Email me bhi sahi property use ki
                    '{#totalAmount#}' => number_format($order->total, 2),
                '{#siteEmailContact#}' => $config->email,
                '{#dashboardLink#}' => route('student.dashboard'),
                    '{#siteName#}' => $config->name,
                ];

                foreach ($replacements as $key => $value) {
                    $html = str_replace($key, $value, $html);
                    $subject = str_replace($key, $value, $subject);
                }
                
                (new EmailService())->sendEmail($student->email, $subject, $html);
            }
        } catch (\Exception $e) {
            Log::error('Order email error: ' . $e->getMessage());
        }
    }

    public function applyCoupon(Request $request) { 
        $request->validate(['code' => 'required|string']);
        $couponCode = $request->code;
        $student = Auth::guard('student')->user();
        
        $coupon = Coupon::where('code', $couponCode)
            ->where('status', 1)
            ->when($this->currentTenantId(), function ($q, $tenantId) {
                $q->where('organization_id', $tenantId);
            })
            ->first();
        if (!$coupon) return response()->json(['success' => false, 'message' => 'Invalid coupon code.']);
        if ($coupon->expires_at && Carbon::now()->gt($coupon->expires_at)) return response()->json(['success' => false, 'message' => 'Coupon expired.']);

        $subTotal = 0;
        if($student) {
            $cartItems = Cart::where('student_id', $student->id)->get();
            $subTotal = $cartItems->sum(function($item) { return $item->price * $item->quantity; });
        } else {
            $sessionCart = session()->get('cart', []);
            $subTotal = collect($sessionCart)->sum(function($item) { return $item['price'] * $item['quantity']; });
        }

        if ($coupon->min_amount && $subTotal < $coupon->min_amount) return response()->json(['success' => false, 'message' => 'Min amount: ' . $coupon->min_amount]);

        $discount = 0;
        if ($coupon->type == 'fixed') $discount = $coupon->value;
        elseif ($coupon->type == 'percent') $discount = ($subTotal * $coupon->value) / 100;

        if ($discount > $subTotal) $discount = $subTotal;
        $newTotal = $subTotal - $discount;

        session()->put('applied_coupon', ['code' => $coupon->code, 'discount' => $discount]);

        return response()->json(['success' => true, 'message' => 'Applied!', 'discount' => $discount, 'new_total' => $newTotal]);
    }

    public function removeCoupon() {
        session()->forget('applied_coupon');
        return response()->json(['success' => true, 'message' => 'Coupon removed.']);
    }

    public function createRazorpayOrder(Request $request)
    {
        $student = Auth::guard('student')->user();
        $subTotal = 0;

        if ($student) {
            $cartItems = Cart::where('student_id', $student->id)->get();
            $packageIds = $cartItems->pluck('package_id')->filter()->unique()->values();
            $packages = $this->tenantPackageQuery()->whereIn('id', $packageIds)->get()->keyBy('id');

            if ($packages->count() !== $packageIds->count()) {
                return response()->json(['error' => 'One or more packages in your cart are no longer available.'], 400);
            }

            $subTotal = $cartItems->sum(function($item) use ($packages) {
                $package = $packages->get($item->package_id);
                $price = (!empty($package->discounted_amount) && $package->discounted_amount > 0)
                    ? (float) $package->discounted_amount
                    : (float) ($package->amount ?? 0);

                return $price * (int) $item->quantity;
            });
        } else {
            $sessionCart = session()->get('cart', []);
            $packageIds = collect($sessionCart)->pluck('package_id')->filter()->unique()->values();
            $packages = $this->tenantPackageQuery()->whereIn('id', $packageIds)->get()->keyBy('id');

            if ($packages->count() !== $packageIds->count()) {
                return response()->json(['error' => 'One or more packages in your cart are no longer available.'], 400);
            }

            $subTotal = collect($sessionCart)->sum(function($item) use ($packages) {
                $package = $packages->get($item['package_id']);
                $price = (!empty($package->discounted_amount) && $package->discounted_amount > 0)
                    ? (float) $package->discounted_amount
                    : (float) ($package->amount ?? 0);

                return $price * (int) $item['quantity'];
            });
        }

        if ($subTotal <= 0) return response()->json(['error' => 'Cart is empty or invalid'], 400);

        $discount = 0;
        if (session()->has('applied_coupon')) {
            $discount = session('applied_coupon')['discount'];
        }
        $totalAmount = max(0, $subTotal - $discount);

        if (! $this->paidPackagesAvailable()) {
            return response()->json(['error' => 'Online payment is currently available only on the default platform site.'], 403);
        }

        $gateway = PaymentGateway::where('status', 1)->first();
        if (!$gateway) return response()->json(['error' => 'Razorpay Gateway is NOT Active or Missing'], 400);

        $api = new Api($gateway->key_id, $gateway->key_secret);
        
        $orderData = [
            'receipt'         => 'rcptid_' . Str::random(10),
            'amount'          => $totalAmount * 100, // Paise
            'currency'        => 'INR',
        ];

        try {
            $razorpayOrder = $api->order->create($orderData);
            return response()->json([
                'id' => $razorpayOrder['id'], 
                'amount' => $orderData['amount']
            ]);
        } catch (\Exception $e) {
            Log::error("Razorpay Create Error: " . $e->getMessage());
            return response()->json(['error' => 'Razorpay API Error: ' . $e->getMessage()], 500);
        }
    }

    public function enrollExam(Request $request)
    {

        $request->validate([
            'id' => 'required|exists:packages,id',
            'groupId' => 'nullable',
        ]);

        try {

            $package = $this->tenantPackageQuery()
                ->where('id', $request->id)
                ->where('status', 1)
                ->firstOrFail();

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

            return response()->json([
                'message' => 'This course is currently unavailable.'
            ], 404);

        }

        $requestedExam = $this->resolveRequestedPackageExam($package, $request->input('exam'));
        $targetExam = $this->activePackageExam($package, $requestedExam);

        $isStudentLoggedIn = auth('student')->check();
        $guestId = $isStudentLoggedIn ? null : $this->getOrCreateGuestId();

        Log::info('Exam enrollment start requested', [
            'package_id' => $package->id,
            'requested_exam' => $request->input('exam'),
            'target_exam_id' => $targetExam ? $targetExam->id : null,
            'target_exam_slug' => $targetExam ? $targetExam->slug : null,
            'is_student_logged_in' => $isStudentLoggedIn,
            'student_id' => auth('student')->id(),
            'guest_id' => $guestId,
            'tenant_id' => $this->currentTenantId(),
            'host' => $request->getHost(),
        ]);

        // Prevent duplicate participation
        $alreadyExists = Order::query()
            ->when($this->currentTenantId(), function ($query, $tenantId) {
                $query->where(function ($query) use ($tenantId) {
                    $query->where('organization_id', $tenantId)
                        ->orWhereNull('organization_id');
                });
            })
            ->where('guest_id', $guestId)
            ->whereHas('items', function ($query) use ($package) {
                $query->where('package_id', $package->id);
            })
            ->exists();

        if (! $isStudentLoggedIn && $alreadyExists) {

            // $purchasedPackages = \App\Models\Package::where('id', $package->id)
            //     ->with(['exams' => function ($query) {
            //         $query->where('status', 'Active')
            //               ->withSum('questions', 'marks')
            //               ->latest();
            //     }])
            //     ->get();

            // $allExamIds = $purchasedPackages->flatMap(function ($package) {
            //     return $package->exams->pluck('id');
            // });

            // $getExamId = $allExamIds[0] ?? null;

            if (! $targetExam) {
                return response()->json([
                    'message' => 'No active exam is available in this course.',
                ], 409);
            }

            return response()->json([
                'message' => 'You have already participated in this course.',
                'redirectUrl' => route('guest.instructions', ['id' => $this->examRouteKey($targetExam)])
            ]);            
        }

        $price = (!empty($package->discounted_amount) && $package->discounted_amount > 0) ? $package->discounted_amount : ($package->amount ?? 0);
        $isFree = strtolower($package->package_type) === 'free';

        if ($isFree && ! (getConfiguration()->allow_guest_exam_attempts ?? true) && ! auth('student')->check()) {
            $this->postAuthService->remember([
                'action' => 'start_exam',
                'package_id' => $package->id,
                'exam_id' => $targetExam?->id,
                'group_id' => $request->integer('groupId') ?: null,
            ]);

            return response()->json([
                'success' => true,
                'redirectUrl' => route('student.signin'),
            ]);
        }

        if ($isStudentLoggedIn) {
            $student = auth('student')->user();

            $alreadyPurchased = Order::query()
                ->when($this->currentTenantId(), function ($query, $tenantId) {
                    $query->where(function ($query) use ($tenantId) {
                        $query->where('organization_id', $tenantId)
                            ->orWhereNull('organization_id');
                    });
                })
                ->where('student_id', $student->id)
                ->whereHas('items', function ($query) use ($package) {
                    $query->where('package_id', $package->id);
                })
                ->exists();

            if (! $alreadyPurchased && ! $isFree) {
                return response()->json([
                    'message' => 'This exam is paid. Please purchase this course to take the exam.',
                ], 409);
            }

            if (! $alreadyPurchased) {
                $order = Order::create([
                    'organization_id' => $this->currentTenantId(),
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
            }

            if (! $targetExam) {
                return response()->json([
                    'message' => 'No active exam is available in this course.',
                ], 409);
            }

            return response()->json([
                'message' => 'Course activated successfully.',
                'redirectUrl' => route('student.instructions', ['id' => $this->examRouteKey($targetExam)]),
            ]);
        }

        if (!$isFree) {
            return response()->json([
                'message' => 'This exam is paid. Please purchase this course to take the exam.',
            ], 409);
        }

        $subTotal = $price * 1;
        $discount = 0;
        $totalAmount = max(0, $subTotal - $discount);

        // 4. Determine Payment Status
        $paymentStatus = 'Completed';
        $orderStatus = 'completed';

        // 5. Create Order
        $order = Order::create([
            'organization_id' => $this->currentTenantId(),
            'guest_id' => $guestId,
            'total' => $totalAmount, // <-- Changed from total_amount to total
            'discount' => $discount, 
            'coupon_code' => null, 
            'payment_method' => 'free',
            'payment_status' => $paymentStatus,
            'status' => $orderStatus,
        ]);

        // 6. Order Items
        OrderItem::create([
            'order_id' => $order->id,
            'package_id' => $package->id,
            'name' => $package->name,
            'price' => $price,
            'quantity' => 1,
            'total' => $price * 1,
        ]);

        // Email Send
        //$this->sendOrderEmail($student, $order);

        // ✅ FIX 2: Added 'new_purchase' session flash for Dashboard Popup
        // return redirect()->route('student.myexams')
        //     ->with('success', 'Order placed successfully! Course Activated.')
        //     ->with('new_purchase', true);

        // $purchasedPackages = \App\Models\Package::where('id', $package->id)
        //     ->with(['exams' => function ($query) {
        //         $query->where('status', 'Active')
        //               ->withSum('questions', 'marks')
        //               ->latest();
        //     }])
        //     ->get();

        // $allExamIds = $purchasedPackages->flatMap(function ($package) {
        //     return $package->exams->pluck('id');
        // });

        // $getExamId = $allExamIds[0] ?? null;


        if (! $targetExam) {
            return response()->json([
                'message' => 'No active exam is available in this course.',
            ], 409);
        }

        return response()->json([
            'message' => 'Order placed successfully! Course Activated.',
            'redirectUrl' => route('guest.instructions', ['id' => $this->examRouteKey($targetExam)])
        ]);
            
    }


    private function resolveRequestedPackageExam(Package $package, $examKey)
    {
        if (empty($examKey)) {
            return null;
        }

        return $package->exams()
            ->where(function ($query) {
                $this->activeExamStatusQuery($query);
            })
            ->where(function ($query) use ($examKey) {
                if (is_numeric($examKey)) {
                    $query->where('exams.id', $examKey);
                }

                $query->orWhere('exams.slug', $examKey);
            })
            ->first();
    }

    private function activePackageExam(Package $package, $requestedExam = null)
    {
        if ($requestedExam && $this->isActiveExamStatus($requestedExam->status ?? null)) {
            return $requestedExam;
        }

        return $package->exams()
            ->where(function ($query) {
                $this->activeExamStatusQuery($query);
            })
            ->withSum('questions', 'marks')
            ->latest('exams.id')
            ->first();
    }

    private function activeExamStatusQuery($query): void
    {
        $query->whereIn('exams.status', ['Active', 'active', 'ACTIVE', '1', 1, true, 'Published', 'published', 'Enabled', 'enabled']);
    }

    private function isActiveExamStatus($status): bool
    {
        return in_array($status, ['Active', 'active', 'ACTIVE', '1', 1, true, 'Published', 'published', 'Enabled', 'enabled'], true);
    }

    private function examRouteKey($exam)
    {
        return $exam ? ($exam->slug ?: $exam->id) : null;
    }

    private function getOrCreateGuestId()
    {
        // First check session
        if (session()->has('guest_id')) {
            return session('guest_id');
        }

        // Then check cookie
        $guestId = request()->cookie('guest_id');

        if (!$guestId) {
            $guestId = 'GST-' . strtoupper(Str::random(10));
        }

        // Store in session
        session(['guest_id' => $guestId]);

        return $guestId;
    }
}
