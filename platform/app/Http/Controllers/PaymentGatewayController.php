<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PaymentGateway;

class PaymentGatewayController extends Controller
{
    /**
     * Admin panel mein payment settings page dikhayein
     */
    public function index()
    {
        // Hum pehli entry hi fetch karenge (ID 1)
        $gateway = PaymentGateway::first();
        return view('payment_gateway.index', compact('gateway'));
    }

    /**
     * Settings ko database mein save karein
     */
    public function store(Request $request)
    {
        // Validation rules
        $validated = $request->validate([
            'key_id' => 'required|string|max:255',       // Razorpay Key ID
            'key_secret' => 'required|string|max:255',    // Razorpay Key Secret
            'webhook_secret' => 'nullable|string|max:255', // Razorpay Webhook Secret (Optional)
            'status' => 'required|boolean',
        ]);

        // ID 1 wali row ko update karein ya nayi banayein
        $gateway = PaymentGateway::updateOrCreate(
            ['id' => 1], 
            $validated
        );

        $message = 'Razorpay credentials updated successfully.';

        return redirect()->route('payment-gateway.index')->with('success', $message);
    }
}