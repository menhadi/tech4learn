<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\Package;
use App\Models\Configuration;
use App\Models\EmailTemplate;
use App\Services\EmailService;
use Illuminate\Support\Facades\Log; // Error check karne ke liye
use Illuminate\Support\Facades\Schema;

class PaymentWebhookController extends Controller
{
    /**
     * sePay.vn se aane waale webhook ko handle karein
     */
    public function handleSepayWebhook(Request $request)
    {
        Log::info('sePay Webhook Received', [
            'order_id' => $request->input('order_id'),
            'code' => $request->input('code'),
            'amount' => $request->input('amount'),
            'status' => $request->input('status'),
        ]);

        // sePay ki documentation ke hisaab se, woh 'api_key' bhejte hain
        $incomingApiKey = $request->input('api_key');

        // 2. Apne database se saved API key nikaalein
        $gateway = PaymentGateway::where('status', 1)->first(); // Active gateway (ID 1)
        
        if (!$gateway || !$gateway->webhook_secret) {
            Log::error('sePay Webhook: Gateway settings ya Webhook Secret missing.');
            return response()->json(['error' => 'Configuration error'], 400);
        }

        $savedApiKey = $gateway->webhook_secret;

        // 3. API Key ko Verify karein (Security Check)
        if ($incomingApiKey !== $savedApiKey) {
            Log::error('sePay Webhook: Invalid API Key.');
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // 4. Order dhoondein (sePay 'code' field mein hamara unique code bhejta hai)
        $paymentCode = $request->input('code'); // Jaise: PAYUser43Order101
        if (!$paymentCode) {
            Log::error('sePay Webhook: "code" field missing.');
            return response()->json(['error' => 'Payment code missing'], 400);
        }

        // Order ID ko code se nikaalein (Hum maan rahe hain code "PAYUserXXOrderYY" format mein hoga)
        // Ye logic hum agle step mein banayenge. Abhi ke liye, hum maan lete hain ki 'code' hi order ID hai.
        // TODO: Is logic ko behtar banana hai jab hum CheckoutController banayenge.
        // Abhi ke liye hum order_id field expect karte hain (sePay docs ke mutabik)
        
        $orderId = $request->input('order_id'); // sePay order_id bhi bhejta hai

        $order = Order::with(['items', 'student'])->find($orderId);
        if (!$order) {
            Log::error('sePay Webhook: Order not found with ID: ' . $orderId);
            return response()->json(['error' => 'Order not found'], 404);
        }

        // 5. Check karein ki order pehle se completed toh nahi hai
        if ($order->status == 'completed') {
            Log::info('sePay Webhook: Order already completed. ID: ' . $orderId);
            return response()->json(['message' => 'Order already processed']);
        }

        // 6. Payment successful - Order update karein
        $order->status = 'completed';
        $order->payment_method = 'sePay (Bank Transfer)'; // Payment method update karein
        $order->save();

        // 7. Student ko package assign karein
            $student = $order->student;
            if ($student) {
                foreach ($order->items as $item) {
                $package = Package::query()
                    ->when(Schema::hasColumn('packages', 'organization_id'), function ($query) use ($order) {
                        $query->where('organization_id', $order->organization_id);
                    })
                    ->find($item->package_id);
                if ($package && $package->groups) {
                    $student->groups()->syncWithoutDetaching($package->groups->pluck('id')->toArray());
                }
            }
        }

        // 8. Student ko email bhejein
        $this->sendOrderConfirmationEmail($order);

        Log::info('sePay Webhook: Successfully processed order ID: ' . $orderId);
        
        // sePay ko batayein ki sab safal raha
        return response()->json(['message' => 'Webhook received successfully']);
    }

    /**
     * Order confirmation email bhej
     */
    private function sendOrderConfirmationEmail(Order $order)
    {
        try {
            $student = $order->student;
            $template = EmailTemplate::where('key', 'order_confirmation')->first();
            $config = Configuration::query()
                ->when(Schema::hasColumn('configurations', 'organization_id'), function ($query) use ($order) {
                    $query->where('organization_id', $order->organization_id);
                })
                ->first();

            if (!$template || !$config || !$student) {
                Log::error('Email send failed: Template, Config, or Student missing for Order ID: ' . $order->id);
                return;
            }

            $html = $template->description;
            $subject = $template->subject;
            
            $itemsHtml = '';
            foreach ($order->items as $item) {
                $itemsHtml .= '<li style="padding: 5px 0; font-size: 15px; color: #555555;">' . htmlspecialchars($item->name) . '</li>';
            }

            $totalAmountFormatted = ($order->total > 0)
                ? ($config->currency ?? '$') . number_format($order->total, 2)
                : 'Free';

            $replacements = [
                '{#studentName#}' => $student->name,
                '{#orderId#}' => $order->id,
                '{#dashboardLink#}' => route('student.dashboard'),
                '{#siteName#}' => $config->name,
                '{#siteEmailContact#}' => $config->email,
                '{#orderItems#}' => $itemsHtml,
                '{#totalAmount#}' => $totalAmountFormatted,
            ];

            foreach ($replacements as $key => $value) {
                $html = str_replace($key, $value, $html);
                $subject = str_replace($key, $value, $subject);
            }
            
            (new EmailService())->sendEmail($student->email, $subject, $html);
            Log::info('Order confirmation email sent for order ID: ' . $order->id);

        } catch (\Exception $e) {
            Log::error('Order confirmation email failed for order ID ' . $order->id . ': ' . $e->getMessage());
        }
    }
}
