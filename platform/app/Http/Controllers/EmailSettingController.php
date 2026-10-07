<?php

namespace App\Http\Controllers;

use App\Models\EmailSetting;
use App\Models\EmailLog; // ✅ Naya Model
use App\Models\EmailTemplate;
use App\Models\Student;
use App\Mail\CustomEmail;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Support\SaasAccess;

class EmailSettingController extends Controller
{
    // ======================================================
    // 1. SETTINGS & LOGS (Naya Feature)
    // ======================================================
    public function index(Request $request)
    {
        $emailSetting = $this->emailSettingQuery()->first();
        
        // Logs fetch karo (Latest 50)
        $emailLogs = Schema::hasTable('email_logs')
            ? $this->emailLogQuery()->orderBy('created_at', 'desc')->paginate(20)
            : new LengthAwarePaginator([], 0, 20);

        return view('email_settings.index', compact('emailSetting', 'emailLogs'));
    }

    public function store(Request $request)
    {
        return $this->saveSettings($request);
    }

    public function update(Request $request, EmailSetting $emailSetting)
    {
        return $this->saveSettings($request);
    }

    // Common Save Logic
    private function saveSettings($request)
    {
        try {
            $data = $request->validate([
                'type'     => 'required|in:local,smtp',
                'host'     => 'nullable|string',
                'username' => 'nullable|string',
                'password' => 'nullable|string',
                'port'     => 'nullable|integer',
                'tls'      => 'required|boolean',
                'founder_from_name' => 'nullable|string|max:100',
                'founder_from_address' => 'nullable|email|max:190',
            ]);

            $emailSetting = $this->emailSettingQuery()->first() ?: new EmailSetting();
            $emailSetting->organization_id = $this->currentOrganizationId();
            $emailSetting->fill($data)->save();

            return redirect()->route('email-settings.index')->with('success', 'Email Settings saved successfully.');
        } catch (ValidationException $e) {
            return redirect()->route('email-settings.index')->withErrors($e->validator)->withInput();
        } catch (\Exception $e) {
            return redirect()->route('email-settings.index')->with('error', 'Failed to save settings.');
        }
    }

    // ✅ Naya Feature: Test Connection Button Logic
    public function sendTestEmail(Request $request)
    {
        $request->validate(['test_email' => 'required|email']);

        try {
            $this->applyMailConfigFromDb(); // Config apply karo

            // Test Email Send Karo
            Mail::raw('This is a test email from your Exam System to verify connection.', function ($message) use ($request) {
                $message->to($request->test_email)
                        ->subject('Test Connection - Success');
            });

            // Log Success
            EmailLog::create([
                'organization_id' => $this->currentOrganizationId(),
                'to_email' => $request->test_email,
                'subject' => 'Test Connection',
                'status' => 'Success'
            ]);

            return back()->with('success', 'Connection Verified! Email sent successfully.');

        } catch (\Exception $e) {
            // Log Failure
            EmailLog::create([
                'organization_id' => $this->currentOrganizationId(),
                'to_email' => $request->test_email,
                'subject' => 'Test Connection',
                'status' => 'Failed',
                'error_message' => $e->getMessage()
            ]);

            return back()->with('error', 'Connection Failed: ' . $e->getMessage());
        }
    }

    // ======================================================
    // 2. MANUAL EMAIL SENDING (Purana Feature - Restored)
    // ======================================================

    public function sendEmailForm()
    {
        $emailTemplates = $this->emailTemplateQuery()->get();
        return view('send_email.index', compact('emailTemplates'));
    }

    public function searchStudents(Request $request)
    {
        $query = $request->get('query', '');
        $students = Student::where('email', 'LIKE', "%{$query}%")
            ->when(SaasAccess::organization()?->id && Schema::hasColumn('students', 'organization_id'), function ($studentQuery) {
                $studentQuery->where('organization_id', SaasAccess::organization()->id);
            })
            ->limit(20)
            ->get(['id', 'email']);

        return response()->json($students);
    }

    public function sendEmail(Request $request)
    {
        try {
            $data = $request->validate([
                'type'           => 'required|in:student,any',
                'student_emails' => 'required_if:type,student|array',
                'any_emails'     => 'required_if:type,any|string',
                'subject'        => 'required|string|max:255',
                'email_template' => 'required|string',
            ]);

            // Runtime config apply karo
            $this->applyMailConfigFromDb();

            $emails = $data['type'] === 'student'
                ? $data['student_emails']
                : array_filter(array_map('trim', explode(',', $data['any_emails'])));

            foreach ($emails as $email) {
                // ✅ UPDATED: Try-Catch inside loop (Swift Feature)
                // Agar ek email fail ho, to baaki rukne nahi chahiye
                try {
                    Mail::to($email)->send(new CustomEmail($data['subject'], $data['email_template']));
                    
                    // DB Log
                    EmailLog::create([
                        'organization_id' => $this->currentOrganizationId(),
                        'to_email' => $email,
                        'subject' => $data['subject'],
                        'status' => 'Success'
                    ]);

                } catch (\Exception $e) {
                    // Fail hua to Log karo aur continue karo
                    EmailLog::create([
                        'organization_id' => $this->currentOrganizationId(),
                        'to_email' => $email,
                        'subject' => $data['subject'],
                        'status' => 'Failed',
                        'error_message' => $e->getMessage()
                    ]);
                    
                    Log::error('Failed to send email to ' . $email . ' Error: ' . $e->getMessage());
                    continue; // Next email pe jao
                }
            }

            return redirect()->route('send-email-form')->with('success', 'Process completed. Check Email Logs for details.');
            
        } catch (ValidationException $e) {
            return redirect()->route('send-email-form')->withErrors($e->validator)->withInput();
        } catch (\Exception $e) {
            return redirect()->route('send-email-form')->with('error', 'Critical Error: ' . $e->getMessage());
        }
    }

    // ======================================================
    // 3. HELPER FUNCTION (Runtime Config)
    // ======================================================
    private function applyMailConfigFromDb(): void
    {
        $s = $this->emailSettingQuery()->first();

        // Fallback agar settings nahi hain
        if (!$s || $s->type !== 'smtp') {
            Config::set('mail.default', 'sendmail');
            return;
        }

        // Auto detect encryption
        $encryption = ((int)$s->port === 465) ? 'ssl' : (((int)$s->port === 587) ? 'tls' : ($s->tls ? 'tls' : 'ssl'));

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', $s->host);
        Config::set('mail.mailers.smtp.port', (int)$s->port);
        Config::set('mail.mailers.smtp.encryption', $encryption);
        Config::set('mail.mailers.smtp.username', $s->username);
        Config::set('mail.mailers.smtp.password', $s->password);
        Config::set('mail.from.address', $s->username);
        Config::set('mail.from.name', config('app.name'));
    }

    private function currentOrganizationId(): ?int
    {
        return SaasAccess::organization()?->id;
    }

    private function emailSettingQuery()
    {
        return EmailSetting::query()
            ->when($this->currentOrganizationId() && Schema::hasColumn('email_settings', 'organization_id'), function ($query) {
                $query->where('organization_id', $this->currentOrganizationId());
            });
    }

    private function emailLogQuery()
    {
        return EmailLog::query()
            ->when($this->currentOrganizationId() && Schema::hasColumn('email_logs', 'organization_id'), function ($query) {
                $query->where('organization_id', $this->currentOrganizationId());
            });
    }

    private function emailTemplateQuery()
    {
        return EmailTemplate::query()
            ->when($this->currentOrganizationId() && Schema::hasColumn('email_templates', 'organization_id'), function ($query) {
                $query->where(function ($templateQuery) {
                    $templateQuery
                        ->where('organization_id', $this->currentOrganizationId())
                        ->orWhereNull('organization_id');
                });
            });
    }
}
