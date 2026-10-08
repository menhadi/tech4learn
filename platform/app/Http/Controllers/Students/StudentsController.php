<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\Student;
// ✅ Storage Facade ko import karein
use Illuminate\Support\Facades\Storage;

class StudentsController extends Controller
{

    public function showProfile()
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();
        return view('students.profile', compact('student'));
    }

    // ======== ✅ EDIT PROFILE FUNCTION UPDATED ========
    public function editProfile(Request $request)
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        if ($request->isMethod('post')) {
            return app(\App\Services\EnrolledStudentEditGuard::class)->withIdentityLock($request,$student,function () use ($request,$student) {
            $validator = Validator::make($request->all(), [
                'enroll'         => 'sometimes|required|string|max:255', // Sometimes if you dont allow editing
                'guardian_phone' => 'nullable|string|max:20', // Max length adjust karein
                'address'        => 'sometimes|required|string|max:500', // Max length adjust karein
                // Photo ke liye validation rules add kiye gaye
                'photo'          => 'nullable|image|mimes:jpeg,png,gif|max:2048', // Max 2MB
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }

            // Data array banayein update ke liye
            $updateData = $request->only(['enroll', 'guardian_phone', 'address']);

            // === Photo Upload Logic ===
            if ($request->hasFile('photo')) {
                // 1. Purani photo delete karein (agar hai)
                if ($student->photo && Storage::disk('public')->exists($student->photo)) {
                    Storage::disk('public')->delete($student->photo);
                }

                // 2. Nayi photo upload karein aur path save karein
                // Path hoga: storage/app/public/students/profile_photos/filename.jpg
                $path = $request->file('photo')->store('students/profile_photos', 'public');
                $updateData['photo'] = $path; // Path ko data mein add karein
            }
            // === End Photo Upload Logic ===


            // Student data update karein
            $student->update($updateData);

            return redirect()->route('student.profile')->with('success', 'Profile updated successfully.');
            });
        }

        // GET request ke liye view return karein
        return view('students.edit_profile', compact('student'));
    }
    // ======== END UPDATE ========


    public function changePassword(Request $request)
    {
        if ($request->isMethod('post')) {
            $validator = Validator::make($request->all(), [
                'old_password' => 'required|string|min:8',
                'password' => 'required|string|min:8|confirmed',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }

            /** @var Student $student */
            $student = Auth::guard('student')->user();

            if (!Hash::check($request->old_password, $student->password)) {
                return redirect()->back()->withErrors(['old_password' => 'Old password is incorrect'])->withInput();
            }

            $student->password = Hash::make($request->password);
            $student->save();
            return redirect()->route('student.profile')->with('success', 'Password changed successfully.');
        }

        return view('students.change_password');
    }
}