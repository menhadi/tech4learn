<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use App\Support\SaasAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EmailTemplateController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->templateQuery();

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%');
        }

        $emailTemplates = $query->paginate(10);
        return view('email_templates.index', compact('emailTemplates'));
    }

    public function create()
    {
        return view('email_templates.action');
    }

    public function edit(EmailTemplate $emailTemplate)
    {
        $this->authorizeTenantTemplate($emailTemplate);

        return view('email_templates.action', compact('emailTemplate'));
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => [
                    'required',
                    'string',
                    Rule::unique('email_templates', 'name')->where(fn ($query) => $query->where('organization_id', $this->currentOrganizationId())),
                ],
                'subject' => 'nullable|string|max:255',
                'description' => 'required|string',
                'status' => 'required|in:Active,Inactive',
                'type' => 'nullable|string',
            ]);

            $validated['organization_id'] = $this->currentOrganizationId();
            EmailTemplate::create($validated);

            return redirect()->route('email-templates.index')->with('success', 'Email Template created successfully.');
        } catch (ValidationException $e) {
            return redirect()->route('email-templates.create')->withErrors($e->validator)->withInput();
        } catch (\Exception $e) {
            return redirect()->route('email-templates.create')->with('error', 'Failed to create email template.');
        }
    }

    public function update(Request $request, EmailTemplate $emailTemplate)
    {
        $this->authorizeTenantTemplate($emailTemplate);

        try {
            $validated = $request->validate([
                'name' => [
                    'required',
                    'string',
                    Rule::unique('email_templates', 'name')
                        ->ignore($emailTemplate->id)
                        ->where(fn ($query) => $query->where('organization_id', $this->currentOrganizationId())),
                ],
                'subject' => 'nullable|string|max:255',
                'description' => 'required|string',
                'status' => 'required|in:Active,Inactive',
                'type' => 'nullable|string',
            ]);

            unset($validated['organization_id']);
            $emailTemplate->update($validated);

            return redirect()->route('email-templates.index')->with('success', 'Email Template updated successfully.');
        } catch (ValidationException $e) {
            return redirect()->route('email-templates.edit', $emailTemplate->id)->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            return redirect()->route('email-templates.edit', $emailTemplate->id)->with('error', 'Failed to update email template.');
        }
    }

    public function destroy($id)
    {
        try {
            $emailTemplate = $this->templateQuery()->findOrFail($id);
            $emailTemplate->delete();
            return redirect()->route('email-templates.index')->with('success', 'Email Template deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->route('email-templates.index')->with('error', 'Failed to delete email template.');
        }
    }

    private function currentOrganizationId(): ?int
    {
        return SaasAccess::organization()?->id;
    }

    private function templateQuery()
    {
        return EmailTemplate::query()
            ->when($this->currentOrganizationId() && Schema::hasColumn('email_templates', 'organization_id'), function ($query) {
                $query->where('organization_id', $this->currentOrganizationId());
            });
    }

    private function authorizeTenantTemplate(EmailTemplate $emailTemplate): void
    {
        if (
            $this->currentOrganizationId()
            && Schema::hasColumn('email_templates', 'organization_id')
            && (int) $emailTemplate->organization_id !== (int) $this->currentOrganizationId()
        ) {
            abort(404);
        }
    }
}
