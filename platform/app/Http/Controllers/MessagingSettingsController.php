<?php

namespace App\Http\Controllers;

use App\Models\Configuration;
use App\Models\SmsTemplate;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class MessagingSettingsController extends Controller
{
    public function edit()
    {
        return view('configurations.messaging', [
            'configuration' => $this->configuration(),
            'messageTemplates' => $this->messageTemplateQuery()
                ->where('status', 'Active')
                ->where('description', 'like', '%{#otp#}%')
                ->orderBy('name')
                ->get(['id', 'name', 'type']),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'student_otp_channel' => ['required', Rule::in(['email', 'sms', 'whatsapp'])],
            'sms_provider' => ['nullable', Rule::in(['msg91', 'twilio', 'twofactor'])],
            'whatsapp_provider' => ['nullable', Rule::in(['msg91', 'twilio'])],
            'default_country_code' => ['required', 'regex:/^\+\d{1,4}$/'],
            'credentials' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
            'settings.sms_template_id' => ['nullable', 'string', Rule::exists('sms_templates', 'id')->where(function ($query) {
                $query->where('status', 'Active')->where('description', 'like', '%{#otp#}%');
                if ($this->tenantId() && Schema::hasColumn('sms_templates', 'organization_id')) {
                    $query->where('organization_id', $this->tenantId());
                }
            })],
            'settings.whatsapp_template_id' => ['nullable', 'string', Rule::exists('sms_templates', 'id')->where(function ($query) {
                $query->where('status', 'Active')->where('description', 'like', '%{#otp#}%');
                if ($this->tenantId() && Schema::hasColumn('sms_templates', 'organization_id')) {
                    $query->where('organization_id', $this->tenantId());
                }
            })],
            'credentials.*' => ['nullable', 'string', 'max:500'],
            'settings.*' => ['nullable', 'string', 'max:500'],
        ]);

        $configuration = $this->configuration();
        $credentials = $configuration->messaging_credentials ?: [];
        foreach ($data['credentials'] ?? [] as $key => $value) {
            if (filled($value)) {
                $credentials[$key] = trim($value);
            }
        }

        $configuration->forceFill([
            'student_otp_channel' => $data['student_otp_channel'],
            'sms_provider' => $data['sms_provider'] ?? null,
            'whatsapp_provider' => $data['whatsapp_provider'] ?? null,
            'default_country_code' => $data['default_country_code'],
            'messaging_credentials' => $credentials,
            'messaging_settings' => array_filter($data['settings'] ?? [], fn ($value) => filled($value)),
        ])->save();

        audit_log('configuration.messaging_updated', $configuration, ['sms_provider' => $configuration->sms_provider, 'whatsapp_provider' => $configuration->whatsapp_provider]);

        return back()->with('success', 'Messaging settings saved. New OTP requests will use these choices.');
    }

    private function messageTemplateQuery()
    {
        return SmsTemplate::query()
            ->when($this->tenantId() && Schema::hasColumn('sms_templates', 'organization_id'), function ($query) {
                $query->where('organization_id', $this->tenantId());
            });
    }

    private function tenantId(): ?int
    {
        return Tenant::hostId(request()->getHost()) ?: Tenant::id();
    }

    private function configuration(): Configuration
    {
        return Configuration::firstOrCreate(['organization_id' => $this->tenantId()], ['name' => config('app.name')]);
    }
}
