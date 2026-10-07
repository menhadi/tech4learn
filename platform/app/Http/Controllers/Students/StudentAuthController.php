<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Configuration;
use App\Models\EmailTemplate;
use App\Models\ExamResult;
use App\Models\Group;
use App\Models\Student;
use App\Services\EmailService;
use App\Services\PhoneNumberService;
use App\Services\StudentAccountSupportEmailService;
use App\Services\StudentActivityTracker;
use App\Services\StudentFreePackageEnrollmentService;
use App\Services\StudentOtpService;
use App\Services\StudentPostAuthService;
use App\Services\StudentWelcomeEmailService;
use App\Support\SaasAccess;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StudentAuthController extends Controller
{
    protected $emailService;

    protected $welcomeEmailService;

    protected $accountSupportEmailService;

    protected $freePackageEnrollmentService;

    protected $postAuthService;

    protected $otpService;

    protected $phoneNumbers;

    public function __construct(
        EmailService $emailService,
        StudentWelcomeEmailService $welcomeEmailService,
        StudentAccountSupportEmailService $accountSupportEmailService,
        StudentFreePackageEnrollmentService $freePackageEnrollmentService,
        StudentPostAuthService $postAuthService,
        StudentOtpService $otpService,
        PhoneNumberService $phoneNumbers
    ) {
        $this->emailService = $emailService;
        $this->welcomeEmailService = $welcomeEmailService;
        $this->accountSupportEmailService = $accountSupportEmailService;
        $this->freePackageEnrollmentService = $freePackageEnrollmentService;
        $this->postAuthService = $postAuthService;
        $this->otpService = $otpService;
        $this->phoneNumbers = $phoneNumbers;
    }

    private function currentTenantId(): ?int
    {
        return class_exists(Tenant::class)
            ? Tenant::hostId(request()->getHost())
            : null;
    }

    private function tenantGroupQuery()
    {
        $query = Group::query();
        $tenantId = $this->currentTenantId();

        if ($tenantId) {
            $query->where('organization_id', $tenantId);
        }

        return $query;
    }

    private function tenantStudentQuery()
    {
        $query = Student::query();
        $tenantId = $this->currentTenantId();

        if ($tenantId) {
            $query->where('organization_id', $tenantId);
        }

        return $query;
    }

    private function studentLoginCandidates(string $login)
    {
        $normalizedPhone = null;
        if (preg_match('/^[+\d\s().-]{8,20}$/', $login)) {
            try {
                $normalizedPhone = $this->phoneNumbers->normalize($login, getConfiguration()?->default_country_code ?: '+91');
            } catch (\InvalidArgumentException) {
                $normalizedPhone = null;
            }
        }

        $query = Student::query()
            ->where(function ($query) use ($login, $normalizedPhone) {
                $query->where('email', $login)
                    ->orWhere('phone', $login)
                    ->when($normalizedPhone, fn ($query) => $query->orWhere('phone', $normalizedPhone))
                    ->orWhere('reg_code', $login)
                    ->orWhere('enroll', $login);
            });

        if (SaasAccess::isPlatformOrganization()) {
            return $query
                ->whereHas('organization', function ($query) {
                    $query->where('status', 'active');
                })
                ->orderByRaw('organization_id = ? desc', [$this->currentTenantId()])
                ->latest()
                ->get();
        }

        return $query
            ->when($this->currentTenantId(), function ($query, $tenantId) {
                $query->where('organization_id', $tenantId);
            })
            ->limit(1)
            ->get();
    }

    private function ensureTenantOwnsGroup(int $groupId): void
    {
        $tenantId = $this->currentTenantId();

        if ($tenantId && ! Group::where('organization_id', $tenantId)->where('id', $groupId)->exists()) {
            abort(404);
        }
    }

    // ==========================================
    // 1. SIGNUP LOGIC
    // ==========================================
    public function showSignupForm(Request $request)
    {
        $this->postAuthService->capture($request);
        $groups = $this->tenantGroupQuery()->orderBy('group_name')->get();
        $configuration = getConfiguration();
        $contextualGroupId = $this->postAuthService->inferredGroupId($this->currentTenantId());

        return view('students.auth.student_signup', compact('groups', 'configuration', 'contextualGroupId'));
    }

    public function signup(Request $request)
    {
        $tenantId = $this->currentTenantId();
        $this->postAuthService->capture($request);

        $contact = trim((string) $request->input('contact'));
        $request->merge(['contact' => $contact, 'email' => null, 'phone' => null]);

        if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
            $request->merge(['email' => strtolower($contact)]);
        } elseif ($contact !== '') {
            try {
                $request->merge([
                    'phone' => $this->phoneNumbers->normalize(
                        $contact,
                        getConfiguration()?->default_country_code ?: '+91'
                    ),
                ]);
            } catch (\InvalidArgumentException $exception) {
                return back()->withErrors(['contact' => 'Enter a valid email address or mobile number. '.$exception->getMessage()])->withInput();
            }
        }

        $contextualGroupId = $this->postAuthService->inferredGroupId($tenantId);
        if ($contextualGroupId) {
            $request->merge(['group_id' => $contextualGroupId]);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'contact' => 'required|string|max:255',
            'email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                Rule::unique('students', 'email')->where(fn ($query) => $query->where('organization_id', $tenantId)),
            ],
            'password' => 'required|string|min:8',
            'phone' => [
                'nullable',
                'string',
                Rule::unique('students', 'phone')->where(fn ($query) => $query->where('organization_id', $tenantId)),
            ],
            'group_id' => 'required|exists:groups,id',
        ]);

        if ($validator->fails()) {
            if ($pendingStudent = $this->pendingStudentFromSignupAttempt($request)) {
                session(['verify_student_id' => $pendingStudent->id]);

                try {
                    $channel = $this->otpService->send($pendingStudent);
                    session(['verify_channel' => $channel]);
                    $message = 'This account is awaiting verification. A new code was sent by '.ucfirst($channel).'.';
                } catch (\RuntimeException $exception) {
                    $message = $exception->getMessage().' Only the newest code will work.';
                } catch (\Throwable $exception) {
                    Log::error('Pending signup OTP delivery failed: '.$exception->getMessage());
                    $message = 'This account is awaiting verification. Use the latest code already received or try again shortly.';
                }

                return redirect()->route('student.verify')->with('error', $message);
            }

            return redirect()->back()->withErrors($validator)->withInput();
        }

        $configuration = getConfiguration();
        if ($request->phone && ! ($configuration?->sms_provider || $configuration?->whatsapp_provider)) {
            return back()->withErrors([
                'contact' => 'Mobile verification is not available yet. Please register with an email address or ask the administrator to configure SMS or WhatsApp.',
            ])->withInput();
        }

        $this->ensureTenantOwnsGroup((int) $request->group_id);

        $student = Student::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'organization_id' => $tenantId,
            'status' => 'Pending',
            'otp' => null,
        ]);

        if ($request->has('group_id')) {
            $student->groups()->attach($request->group_id);
        }

        StudentActivityTracker::track(StudentActivityTracker::STUDENT_REGISTERED, [
            'organization_id' => $tenantId,
            'student_id' => $student->id,
            'metadata' => [
                'group_id' => (int) $request->group_id,
                'status' => 'Pending',
                'registration_identifier' => $request->email ? 'email' : 'phone',
            ],
        ], $request);

        try {
            $channel = $this->otpService->send($student);
        } catch (\Throwable $exception) {
            Log::error('Student OTP delivery failed: '.$exception->getMessage());

            return redirect()->route('student.signin')->with('error', 'Account created, but the verification code could not be sent. Please contact the administrator.');
        }

        session(['verify_student_id' => $student->id, 'verify_channel' => $channel]);

        return redirect()->route('student.verify')->with('success', 'Verification code sent by '.ucfirst($channel).'.');
    }
    // ==========================================
    // 2. VERIFICATION LOGIC
    // ==========================================
    public function verifySignupForm()
    {
        $student = $this->tenantStudentQuery()->find(session('verify_student_id'));
        if (! $student) {
            return redirect()->route('student.signin');
        }

        $configuration = getConfiguration();
        $channels = $this->otpService->availableChannels($student);
        $destination = $this->otpService->destination($student);
        $resendAfter = $this->otpService->resendAvailableIn($student);
        $whatsAppAttempts = $this->otpService->whatsAppAttempts($student);

        return view('students.auth.student_verify_signup', compact(
            'configuration',
            'student',
            'channels',
            'destination',
            'resendAfter',
            'whatsAppAttempts'
        ));
    }

    public function verifySignup(Request $request)
    {
        $request->validate(['otp' => 'required|numeric']);

        $tenantId = $this->currentTenantId();
        $student = $this->tenantStudentQuery()->with('groups')->find(session('verify_student_id'));

        if (! $student) {
            return redirect()->route('student.signup')->with('error', 'Student record not found.');
        }

        if ($this->otpService->verify($student, (string) $request->otp)) {
            $student->status = 'Active';
            $student->save();

            StudentActivityTracker::track(StudentActivityTracker::STUDENT_VERIFIED, [
                'organization_id' => $tenantId,
                'student_id' => $student->id,
                'metadata' => [
                    'verified_from' => 'otp',
                ],
            ], $request);

            if ($request->has('examResultId') && $this->getGuestId()) {

                $examResultId = $request->examResultId;
                $guestId = $this->getGuestId();

                $examResult = ExamResult::where(['id' => $examResultId, 'guest_id' => $guestId])->whereNull('student_id')->first();

                if ($examResult) {
                    $examResult->student_id = $student->id;
                    $examResult->update();
                }

            }

            $groupId = $student->groups[0]->id ?? null;

            // General registration receives configured starter packages only.
            if ($groupId && ! $this->postAuthService->hasIntent()) {
                $this->freePackageEnrollmentService->enrollForGroup(
                    $student,
                    (int) $groupId,
                    $tenantId,
                    $request
                );
            }

            Auth::guard('student')->login($student);
            StudentActivityTracker::track(StudentActivityTracker::STUDENT_LOGGED_IN, [
                'organization_id' => $tenantId,
                'student_id' => $student->id,
                'metadata' => [
                    'login_after_verification' => true,
                ],
            ], $request);
            $this->welcomeEmailService->sendOnce($student);
            session()->forget(['verify_student_id', 'verify_channel']);
            session()->flash('new_purchase', true);

            $continuation = $this->postAuthService->complete($student, $request);
            if ($continuation) {
                return redirect($continuation['url'])
                    ->with('success', 'Account verified. You can continue where you left off.');
            }

            return redirect()->route('student.myexams')->with('success', 'Account verified. Welcome!');
        }

        return redirect()->back()->with('error', 'Invalid OTP.');
    }

    public function resendOtp(Request $request)
    {
        $student = $this->tenantStudentQuery()->find(session('verify_student_id'));
        if (! $student) {
            return redirect()->route('student.signin')->with('error', 'Session expired.');
        }

        $request->validate(['channel' => 'nullable|in:email,sms,whatsapp']);
        try {
            $channel = $this->otpService->send($student, $request->input('channel'));
            session(['verify_channel' => $channel]);

            return back()->with('success', 'New verification code sent by '.ucfirst($channel).'.');
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (\Throwable $exception) {
            Log::error('Student OTP resend failed: '.$exception->getMessage());

            return back()->with('error', 'The code could not be sent. Try another available method or contact the administrator.');
        }
    }

    // ==========================================
    // 3. LOGIN LOGIC
    // ==========================================
    public function showSigninForm(Request $request)
    {
        $this->postAuthService->capture($request);
        if ($request->has('redirect')) {
            session(['auth_redirect_to' => $request->redirect]);
        }

        $groups = $this->tenantGroupQuery()->orderBy('group_name')->get();
        $configuration = getConfiguration();
        $defGroupId = $request->group;
        $examResultId = $request->exam_result_id;

        return view('students.auth.student_signin', compact('configuration', 'groups', 'defGroupId', 'examResultId'));
    }

    public function signin(Request $request)
    {
        $this->postAuthService->capture($request);

        $login = $request->input('login');
        $password = $request->input('password');

        $studentCheck = $this->studentLoginCandidates($login)
            ->first(function ($student) use ($password) {
                return Hash::check($password, $student->password);
            });

        if ($studentCheck && $studentCheck->status === 'Pending') {
            session(['verify_student_id' => $studentCheck->id]);

            try {
                $channel = $this->otpService->send($studentCheck);
                session(['verify_channel' => $channel]);
                $message = 'Account not verified. We sent a new code by '.ucfirst($channel).'.';
            } catch (\RuntimeException $exception) {
                $message = $exception->getMessage().' Use the newest code already received.';
            } catch (\Throwable $exception) {
                Log::error('Pending student OTP failed: '.$exception->getMessage());
                $message = 'Your account needs verification. Use the latest code already received or try again shortly.';
            }

            return redirect()->route('student.verify')->with('error', $message);
        }

        if ($studentCheck && Hash::check($password, $studentCheck->password)) {
            Auth::guard('student')->login($studentCheck, (bool) $request->boolean('remember'));
            $student = $studentCheck;
            $student->last_login = now();
            $student->save();

            StudentActivityTracker::track(StudentActivityTracker::STUDENT_LOGGED_IN, [
                'organization_id' => $student->organization_id,
                'student_id' => $student->id,
                'metadata' => [
                    'login_identifier_type' => filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'other',
                ],
            ], $request);

            // connect user id with guest exam
            if ($request->has('examResultId') && $this->getGuestId()) {

                $examResultId = $request->examResultId;
                $guestId = $this->getGuestId();

                // $examResult = ExamResult::where('id', $examResultId)->where('guest_id', $guestId)->first();
                $examResult = ExamResult::where(['id' => $examResultId, 'guest_id' => $guestId])->whereNull('student_id')->first();

                if ($examResult) {
                    $examResult->student_id = $student->id;
                    $examResult->update();
                }

            }

            $sessionCart = session()->get('cart', []);
            if (! empty($sessionCart)) {
                foreach ($sessionCart as $package_id => $details) {
                    $cartItem = Cart::where('student_id', $student->id)->where('package_id', $package_id)->first();
                    if ($cartItem) {
                        $cartItem->quantity += $details['quantity'];
                        $cartItem->save();
                    } else {
                        Cart::create([
                            'student_id' => $student->id,
                            'package_id' => $package_id,
                            'name' => $details['name'],
                            'price' => $details['price'],
                            'image' => $details['image'] ?? null,
                            'quantity' => $details['quantity'],
                        ]);
                    }
                }
                session()->forget('cart');
            }

            $continuation = $this->postAuthService->complete($student, $request);
            if ($continuation) {
                return redirect($continuation['url']);
            }

            $redirectTo = session()->pull('auth_redirect_to');
            if ($redirectTo == 'checkout') {
                return redirect()->route('checkout.index');
            }
            if ($request->has('redirect') && $request->redirect == 'checkout') {
                return redirect()->route('checkout.index');
            }

            return redirect()->intended(route('student.myexams'));
        }

        return redirect()->back()->withErrors(['login' => 'Invalid credentials'])->withInput();
    }

    public function signout()
    {
        auth()->guard('student')->logout();

        return redirect()->route('student.signin');
    }

    // ==========================================
    // 4. HELPER: Send OTP Email
    // ==========================================
    private function sendOtpEmail($student, $otp)
    {
        try {
            $organizationId = $student->organization_id ?: $this->currentTenantId();
            $configuration = $organizationId
                ? Configuration::where('organization_id', $organizationId)->first()
                : (function_exists('getConfiguration') ? getConfiguration() : Configuration::first());
            $template = EmailTemplate::where('type', 'otp')->where('status', 'Active')
                ->when(Schema::hasColumn('email_templates', 'organization_id'), function ($query) use ($organizationId) {
                    $query->where(fn ($q) => $q->where('organization_id', $organizationId)->orWhereNull('organization_id'))->orderByRaw('organization_id is null');
                })->latest()->first();
            $tokens = [
                '{#studentName#}' => e($student->name ?: 'Student'),
                '{#otp#}' => e((string) $otp),
                '{#siteName#}' => e($configuration?->name ?: config('app.name', 'Examways')),
                '{#primaryColor#}' => e($configuration?->theme_primary_color ?: '#0f766e'),
                '{#secondaryColor#}' => e($configuration?->theme_secondary_color ?: '#f59e0b'),
            ];
            $subject = str_replace(array_keys($tokens), array_values($tokens), $template?->subject ?: '{#otp#} is your {#siteName#} verification code');
            $html = str_replace(array_keys($tokens), array_values($tokens), $template?->description ?: '<p>Hello {#studentName#}, your verification code is <strong>{#otp#}</strong>.</p>');
            $this->emailService->sendEmail($student->email, $subject, $html, null, null, $organizationId);
        } catch (\Throwable $e) {
            Log::error('OTP Email Failed: '.$e->getMessage());
        }
    }

    private function getGuestId()
    {
        return session('guest_id') ?? request()->cookie('guest_id');
    }

    private function pendingStudentFromSignupAttempt(Request $request): ?Student
    {
        if (! $request->filled('email') && ! $request->filled('phone')) {
            return null;
        }

        return $this->tenantStudentQuery()
            ->where('status', 'Pending')
            ->where(function ($query) use ($request) {
                if ($request->filled('email')) {
                    $query->orWhere('email', $request->email);
                }

                if ($request->filled('phone')) {
                    $query->orWhere('phone', $request->phone);
                }
            })
            ->latest()
            ->first();
    }
}
