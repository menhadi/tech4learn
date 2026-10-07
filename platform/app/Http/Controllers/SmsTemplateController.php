<?php

namespace App\Http\Controllers;

use App\Models\SmsTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SmsTemplateController extends Controller
{
    private function tenantId(): ?int
    {
        return class_exists(\App\Support\Tenant::class) ? \App\Support\Tenant::id() : null;
    }

    private function hasOrganizationColumn(): bool
    {
        return Schema::hasColumn('sms_templates', 'organization_id');
    }

    private function templateQuery()
    {
        return SmsTemplate::query()
            ->when($this->tenantId() && $this->hasOrganizationColumn(), function ($query) {
                $query->where('organization_id', $this->tenantId());
            });
    }

    private function ensureTenantOwns(SmsTemplate $smsTemplate): void
    {
        if ($this->tenantId() && $this->hasOrganizationColumn() && (int) ($smsTemplate->organization_id ?? 0) !== (int) $this->tenantId()) {
            abort(404);
        }
    }

    private function ensureReadyMadeTemplates(): void
    {
        foreach (SmsTemplate::readyMadeTemplates() as $type => $preset) {
            $query = $this->templateQuery()->where('type', $type);
            if ($query->exists()) {
                continue;
            }

            $name = $preset['name'];
            if (SmsTemplate::where('name', $name)->exists()) {
                $name .= ' [Organization '.($this->tenantId() ?: 'Platform').']';
            }

            $data = [
                'name' => $name,
                'description' => $preset['description'],
                'status' => 'Active',
                'type' => $type,
                'dlt_template_id' => null,
            ];
            if ($this->hasOrganizationColumn()) {
                $data['organization_id'] = $this->tenantId();
            }

            SmsTemplate::create($data);
        }
    }

    private function validationRules(?SmsTemplate $smsTemplate = null): array
    {
        $nameRule = Rule::unique('sms_templates', 'name');

        if ($smsTemplate) {
            $nameRule->ignore($smsTemplate->id);
        }

        if ($this->tenantId() && $this->hasOrganizationColumn()) {
            $nameRule->where(function ($query) {
                $query->where('organization_id', $this->tenantId());
            });
        }

        return [
            'name' => ['required', 'string', $nameRule],
            'description' => 'required|string',
            'status' => 'required|in:Active,Inactive',
            'type' => 'nullable|string',
            'dlt_template_id' => 'nullable|string',
        ];
    }

    private function templateData(Request $request): array
    {
        $data = $request->only(['name', 'description', 'status', 'type', 'dlt_template_id']);

        if ($this->hasOrganizationColumn()) {
            $data['organization_id'] = $this->tenantId();
        }

        return $data;
    }

    public function index(Request $request)
    {
        $this->ensureReadyMadeTemplates();
        $query = $this->templateQuery();

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%');
        }

        $smsTemplates = $query->paginate(10);
        return view('sms_templates.index', compact('smsTemplates'));
    }

    public function create()
    {
        $this->ensureReadyMadeTemplates();

        return view('sms_templates.action');
    }

    public function edit(SmsTemplate $smsTemplate)
    {
        $this->ensureTenantOwns($smsTemplate);

        return view('sms_templates.action', compact('smsTemplate'));
    }

    public function store(Request $request)
    {
        try {
            $request->validate($this->validationRules());

            SmsTemplate::create($this->templateData($request));

            return redirect()->route('sms-templates.index')->with('success', 'SMS Template created successfully.');
        } catch (ValidationException $e) {

            return redirect()->route('sms-templates.create')->withErrors($e->validator)->withInput();
        } catch (\Exception $e) {
            return redirect()->route('sms-templates.create')->with('error', 'Failed to create SMS template.');
        }
    }

    public function update(Request $request, SmsTemplate $smsTemplate)
    {
        try {
            $this->ensureTenantOwns($smsTemplate);
            $request->validate($this->validationRules($smsTemplate));

            $smsTemplate->update($this->templateData($request));

            return redirect()->route('sms-templates.index')->with('success', 'SMS Template updated successfully.');
        } catch (ValidationException $e) {
            return redirect()->route('sms-templates.edit', $smsTemplate->id)->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            return redirect()->route('sms-templates.edit', $smsTemplate->id)->with('error', 'Failed to update SMS template.');
        }
    }

    public function destroy($id)
    {
        try {
            $smsTemplate = $this->templateQuery()->findOrFail($id);
            if ($smsTemplate->isReadyMade()) {
                return redirect()->route('sms-templates.index')
                    ->with('error', 'Ready-made OTP templates cannot be deleted. Set the template to Inactive if you do not want to use it.');
            }
            $smsTemplate->delete();
            return redirect()->route('sms-templates.index')->with('success', 'SMS Template deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->route('sms-templates.index')->with('error', 'Failed to delete SMS template.');
        }
    }
}
