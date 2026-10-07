<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Models\Student;

class ApiStudentProfileController extends Controller
{
    /**
     * Authenticated student ki profile fetch karo
     */
    public function getProfile(Request $request)
    {
        // $request->user() token se authenticated student de dega
        // 'photo_url' accessor jo humne model mein banaya, wo automatically add ho jayega
        return response()->json(['success' => true, 'student' => $request->user()]);
    }

    /**
     * Student ki profile update karo
     * (Logic 'StudentsController@editProfile' se copy kiya hai)
     */
    public function updateProfile(Request $request)
    {
        $student = $request->user();

        $validator = Validator::make($request->all(), [
            'enroll'         => 'sometimes|required|string|max:255',
            'guardian_phone' => 'nullable|string|max:20',
            'address'        => 'sometimes|required|string|max:500',
            'photo'          => 'nullable|image|mimes:jpeg,png,gif|max:2048', // Max 2MB
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Data array banayein update ke liye
        $updateData = $request->only(['enroll', 'guardian_phone', 'address']);

        // === Photo Upload Logic (100% Same) ===
        if ($request->hasFile('photo')) {
            // 1. Purani photo delete karein
            if ($student->photo && Storage::disk('public')->exists($student->photo)) {
                Storage::disk('public')->delete($student->photo);
            }
            // 2. Nayi photo upload karein
            $path = $request->file('photo')->store('students/profile_photos', 'public');
            $updateData['photo'] = $path;
        }
        // === End Photo Upload Logic ===

        $student->update($updateData);

        // Naya updated student data (photo_url ke saath) wapas bhejo
        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'student' => $student->fresh() // 'fresh()' database se updated data laata hai
        ]);
    }

    /**
     * Student ka password change karo
     * (Logic 'StudentsController@changePassword' se copy kiya hai)
     */
    public function updatePassword(Request $request)
    {
        $student = $request->user();

        $validator = Validator::make($request->all(), [
            'old_password' => 'required|string|min:8',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        if (!Hash::check($request->old_password, $student->password)) {
            // Error: Purana password galat hai
            return response()->json(['success' => false, 'message' => 'Old password is incorrect.'], 401);
        }

        $student->password = Hash::make($request->password);
        $student->save();

        // Optional: Suraksha ke liye, password badalne par student ko baaki sabhi devices se logout kar do
        // $student->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        return response()->json(['success' => true, 'message' => 'Password changed successfully.']);
    }
}