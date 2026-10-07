<?php

namespace App\Http\Controllers;

use App\Models\Package;
use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Guest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cookie;
use App\Support\SaasAccess;
use App\Services\StudentPostAuthService;

class CartController extends Controller
{
    public function __construct(private StudentPostAuthService $postAuthService)
    {
    }

    private function currentTenantId(): ?int
    {
        return class_exists(\App\Support\Tenant::class)
            ? (\App\Support\Tenant::hostId(request()->getHost()) ?: \App\Support\Tenant::id())
            : null;
    }

    private function tenantPackageQuery()
    {
        return Package::query()
            ->when($this->currentTenantId(), function ($query, $tenantId) {
                $query->where('organization_id', $tenantId);
            })
            ->when(! $this->paidPackagesAvailable(), function ($query) {
                $query->where('package_type', 'free');
            });
    }

    private function paidPackagesAvailable(): bool
    {
        return SaasAccess::isPlatformOrganization() && SaasAccess::featureEnabled('paid_packages');
    }

    public function index()
    {
        $cartItems = collect();
        
        if (Auth::guard('student')->check()) {
            $cartItems = Cart::where('student_id', Auth::guard('student')->id())->get();
        } else {
            $sessionCart = session()->get('cart', []);
            foreach ($sessionCart as $item) {
                $cartItem = new Cart();
                $cartItem->fill($item);
                $cartItems->push($cartItem);
            }
        }

        $total = $cartItems->sum(function ($item) {
            return $item->price * $item->quantity;
        });

        return response()->json([
            'items' => $cartItems,
            'total' => $total,
            'count' => $cartItems->count()
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['id' => 'required|exists:packages,id']);

        // ✅✅✅ YAHAN FIX KIYA GAYA HAI ✅✅✅
        // Ab package add karne se pehle status check hoga
        try {
            $package = $this->tenantPackageQuery()
                            ->where('id', $request->id)
                            ->where('status', 1) // Sirf Published package ko hi find karo
                            ->firstOrFail();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Agar package unpublished hai (ya nahi mila), toh error do
            return response()->json([
                'message' => 'This course is currently unavailable.'
            ], 404); // 404 Not Found
        }
        // ✅✅✅ END FIX ✅✅✅

        $price = (!empty($package->discounted_amount) && $package->discounted_amount > 0) ? $package->discounted_amount : ($package->amount ?? 0);
        $isFree = strtolower($package->package_type) === 'free';


        if (! Auth::guard('student')->check()) {
            $this->postAuthService->remember([
                'action' => $isFree ? 'activate_free_package' : 'checkout',
                'package_id' => $package->id,
                'group_id' => $request->integer('groupId') ?: null,
            ]);

            if ($isFree) {
                return response()->json([
                    'message' => 'Sign in to activate this free package.',
                    'count' => count((array) session('cart', [])),
                    'redirectUrl' => route('student.signin'),
                ]);
            }
        }
        $args = ['redirect' => 'checkout'];
        $groupId = $request->groupId;
        if(!empty($groupId)) {
           $args['group'] = $groupId;
        }
        $redirectUrl = route('student.signin', $args);

        if (Auth::guard('student')->check()) {

            $studentId = Auth::guard('student')->id();

            if ($isFree) {
                $alreadyPurchased = Order::where('student_id', $studentId)
                    ->when($this->currentTenantId(), function ($query, $tenantId) {
                        $query->where(function ($query) use ($tenantId) {
                            $query->where('organization_id', $tenantId)
                                ->orWhereNull('organization_id');
                        });
                    })
                    ->whereHas('items', function ($query) use ($package) {
                        $query->where('package_id', $package->id);
                    })
                    ->exists();

                if (! $alreadyPurchased) {
                    $order = Order::create([
                        'organization_id' => $this->currentTenantId(),
                        'student_id' => $studentId,
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

                Cart::where('student_id', $studentId)->where('package_id', $package->id)->delete();

                return response()->json([
                    'message' => 'Course activated successfully.',
                    'count' => Cart::where('student_id', $studentId)->count(),
                    'redirectUrl' => route('student.myexams'),
                ]);
            }

            $redirectUrl = '';
            $cartItem = Cart::where('student_id', $studentId)->where('package_id', $package->id)->first();

            if ($cartItem) {
                // Item pehle se cart mein hai
                return response()->json([
                    'message' => 'This item is already in your cart.',
                    'count' => Cart::where('student_id', $studentId)->count(),
                    'redirectUrl' => $redirectUrl
                ], 409); // 409 Conflict (Already Exists)
            } else {
                Cart::create([
                    'student_id' => $studentId,
                    'package_id' => $package->id,
                    'name' => $package->name,
                    'price' => $price,
                    'image' => $package->photo ? asset($package->photo) : asset('build/images/small/img-1.jpg'),
                    'quantity' => 1
                ]);
            }
            $count = Cart::where('student_id', $studentId)->count();
        } else {
            $cart = session()->get('cart', []);

            if(isset($cart[$package->id])) {
                // Item pehle se cart mein hai
                return response()->json([
                    'message' => 'This item is already in your cart.',
                    'count' => count($cart),
                    'redirectUrl' => $redirectUrl
                ], 409); // 409 Conflict
            } else {
                $cart[$package->id] = [
                    "package_id" => $package->id,
                    "name" => $package->name,
                    "quantity" => 1,
                    "price" => $price,
                    "image" => $package->photo ? asset($package->photo) : asset('build/images/small/img-1.jpg')
                ];
            }
            session()->put('cart', $cart);
            $count = count(session()->get('cart', []));
        }

        return response()->json(['message' => 'Cart updated successfully.', 'count' => $count, 'redirectUrl' => $redirectUrl]);
    }

    public function destroy($id)
    {
        if (Auth::guard('student')->check()) {
            $studentId = Auth::guard('student')->id();
            Cart::where('student_id', $studentId)->where('package_id', $id)->delete();
            $count = Cart::where('student_id', $studentId)->count();
        } else {
            $cart = session()->get('cart', []);
            if (isset($cart[$id])) {
                unset($cart[$id]);
                session()->put('cart', $cart);
            }
            $count = count(session()->get('cart', []));
        }

        return response()->json(['message' => 'Item removed successfully.', 'count' => $count]);
    }
    
}
