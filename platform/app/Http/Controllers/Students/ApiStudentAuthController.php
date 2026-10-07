<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Student;
use App\Services\PhoneNumberService;
use App\Services\StudentWelcomeEmailService;
use App\Support\SaasAccess;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ApiStudentAuthController extends Controller
{
    public function __construct(private StudentWelcomeEmailService $welcomeEmailService, private PhoneNumberService $phoneNumbers) {}

    /**
     * Student Signup API
     */
    public function signup(Request $request)
    {
        SaasAccess::abortIfFeatureDisabled('student_self_registration');
        $organizationId = (int) Tenant::hostId($request->getHost());
        if ($request->filled('phone')) {
            try {
                $request->merge(['phone' => $this->phoneNumbers->normalize($request->phone, getConfiguration()?->default_country_code ?: '+91')]);
            } catch (\InvalidArgumentException $exception) {
                return response()->json(['errors' => ['phone' => [$exception->getMessage()]]], 422);
            }
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'email' => [
                'nullable', 'required_without:phone', 'string', 'email', 'max:255',
                Rule::unique('students', 'email')->where('organization_id', $organizationId),
            ],
            'password' => 'required|string|min:8|confirmed',
            'phone' => [
                'nullable', 'required_without:email', 'string',
                Rule::unique('students', 'phone')->where('organization_id', $organizationId),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $student = Student::create([
            'organization_id' => $organizationId,
            'name' => $request->name,
            'address' => $request->input('address'),
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'status' => 'Active',
        ]);

        $defaultGroupId = Group::where('organization_id', $organizationId)
            ->orderBy('id')
            ->value('id');
        if ($defaultGroupId) {
            $student->groups()->syncWithoutDetaching([$defaultGroupId]);
        }

        $this->welcomeEmailService->sendOnce($student);

        // Token banao aur student data ke saath return karo
        $token = $student->createToken('mobile-app-token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful!',
            'token' => $token,
            'student' => $student,
        ], 201);
    }

    /**
     * Student Signin API
     */
    public function signin(Request $request)
    {
        $organizationId = (int) Tenant::hostId($request->getHost());
        if ($request->filled('phone')) {
            try {
                $request->merge(['phone' => $this->phoneNumbers->normalize($request->phone, getConfiguration()?->default_country_code ?: '+91')]);
            } catch (\InvalidArgumentException $exception) {
                return response()->json(['errors' => ['phone' => [$exception->getMessage()]]], 422);
            }
        }
        $validator = Validator::make($request->all(), [
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $login = $request->input('login');
        $password = $request->input('password');

        $normalizedPhone = null;
        if (! filter_var($login, FILTER_VALIDATE_EMAIL) && preg_match('/^[+\d\s().-]{8,20}$/', $login)) {
            try {
                $normalizedPhone = $this->phoneNumbers->normalize($login, getConfiguration()?->default_country_code ?: '+91');
            } catch (\InvalidArgumentException) {
                $normalizedPhone = null;
            }
        }

        $student = Student::where('organization_id', $organizationId)
            ->where(function ($query) use ($login, $normalizedPhone) {
                $query->where('email', $login)->orWhere('phone', $login);
                if ($normalizedPhone) {
                    $query->orWhere('phone', $normalizedPhone);
                }
            })->first();

        if (! $student || ! Hash::check($password, $student->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Login successful, last login update karo
        $student->last_login = now();
        $student->save();

        // Purane tokens delete karke naya token banao
        $student->tokens()->delete();
        $token = $student->createToken('mobile-app-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful!',
            'token' => $token,
            'student' => $student,
        ]);
    }

    /**
     * Student Signout API
     */
    public function signout(Request $request)
    {
        // Current logged-in student (jo token se authenticate hua hai) ke sabhi tokens delete kar do
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }
}
