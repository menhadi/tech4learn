<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Contact;
use Illuminate\Support\Facades\Mail; // ✅ Mail facade ko import karein

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'required|email',
            'phone'   => 'required|string|max:20',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        // Database mein data save karein
        Contact::create($validated);
        
        // ✅ CODE CHANGE: Admin ko email notification bhejne ka logic add kiya gaya hai
        try {
            // Configuration se admin ka email nikalein
            $adminEmail = getConfiguration()->email;

            if ($adminEmail) {
                // Email bhejein
                Mail::raw(
                    "You have received a new contact inquiry.\n\n" .
                    "Name: " . $validated['name'] . "\n" .
                    "Email: " . $validated['email'] . "\n" .
                    "Phone: " . $validated['phone'] . "\n" .
                    "Subject: " . $validated['subject'] . "\n" .
                    "Message:\n" . $validated['message'],
                    function ($message) use ($adminEmail, $validated) {
                        $message->to($adminEmail)
                                ->subject('New Contact Form Submission: ' . $validated['subject']);
                    }
                );
            }
        } catch (\Exception $e) {
            // Agar email bhejne mein koi error aaye, toh use ignore karein taaki user ko error na dikhe
            // Aap chahein toh yahan error log kar sakte hain: \Log::error($e->getMessage());
        }

        return back()->with('success', 'Thank you for contacting us! We will get back to you shortly.');
    }
}