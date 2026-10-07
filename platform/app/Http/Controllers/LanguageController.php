<?php

namespace App\Http\Controllers;

use App\Models\Language;
use App\Support\SaasAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LanguageController extends Controller
{
    public function index(Request $request)
    {
        $query = Language::query()
            ->where('organization_id', $this->languageOwnerOrganizationId())
            ->when(! $this->isPlatformAdmin() && $this->hasLanguageEnablement(), function ($query) {
                $query->where('is_enabled', true);
            });

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%');
        }

        $languages = $query->paginate(10);
        $isPlatformLanguageAdmin = $this->isPlatformAdmin();
        $platformLanguages = $isPlatformLanguageAdmin ? collect() : $this->availablePlatformLanguages();

        return view('languages.index', compact('languages', 'isPlatformLanguageAdmin', 'platformLanguages'));
    }

    public function store(Request $request)
    {
        try {
            if (! $this->isPlatformAdmin()) {
                $request->validate([
                    'master_language_id' => ['required', Rule::exists('languages', 'id')->where(function ($query) {
                        $query->where('organization_id', $this->platformOrganizationId());
                    })],
                ]);

                $masterLanguage = Language::where('organization_id', $this->platformOrganizationId())
                    ->findOrFail($request->master_language_id);

                $language = Language::query()
                    ->where('organization_id', $this->currentOrganizationId())
                    ->where(function ($query) use ($masterLanguage) {
                        $query->where('source_language_id', $masterLanguage->id)
                            ->orWhere('code', $masterLanguage->code);
                    })
                    ->first();

                if ($language) {
                    $language->update([
                        'source_language_id' => $masterLanguage->id,
                        'name' => $masterLanguage->name,
                        'code' => $masterLanguage->code,
                        'value1' => $masterLanguage->value1,
                        'value2' => $masterLanguage->value2,
                        'is_enabled' => true,
                    ]);
                } else {
                    Language::create([
                        'organization_id' => $this->currentOrganizationId(),
                        'source_language_id' => $masterLanguage->id,
                        'name' => $masterLanguage->name,
                        'code' => $masterLanguage->code,
                        'value1' => $masterLanguage->value1,
                        'value2' => $masterLanguage->value2,
                        'is_enabled' => true,
                    ]);
                }

                return redirect()->route('languages.index')->with('success', 'Language enabled successfully.');
            }

            $request->validate($this->languageValidationRules());

            Language::create([
                'organization_id' => $this->languageOwnerOrganizationId(),
                'name' => $request->name,
                'code' => $request->code,
                'value1' => $request->value1,
                'value2' => $request->value2,
                'is_enabled' => true,
            ]);
            return redirect()->route('languages.index')->with('success', 'Language created successfully.');
        } catch (ValidationException $e) {
            return redirect()->route('languages.index')->withErrors($e->validator)->withInput();
        } catch (\Exception $e) {
            return redirect()->route('languages.index')->with('error', 'Failed to create language.');
        }
    }

    public function update(Request $request, Language $language)
    {
        $this->ensureTenantOwns($language);

        try {
            if (! $this->isPlatformAdmin()) {
                $request->validate([
                    'value1' => 'nullable|string',
                    'value2' => 'nullable|string',
                ]);

                $language->update($request->only('value1', 'value2'));

                return redirect()->route('languages.index')->with('success', 'Language labels updated successfully.');
            }

            $request->validate($this->languageValidationRules($language));

            $language->update($request->only('name', 'code', 'value1', 'value2'));
            return redirect()->route('languages.index')->with('success', 'Language updated successfully.');
        } catch (ValidationException $e) {
            return redirect()->route('languages.index')->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            return redirect()->route('languages.index')->with('error', 'Failed to update language.');
        }
    }

    public function destroy($id)
    {
        try {
            $language = Language::query()
                ->where('organization_id', $this->languageOwnerOrganizationId())
                ->when(! $this->isPlatformAdmin() && $this->hasLanguageEnablement(), function ($query) {
                    $query->where('is_enabled', true);
                })
                ->findOrFail($id);

            if ($this->isPlatformAdmin()) {
                $language->delete();
                return redirect()->route('languages.index')->with('success', 'Language deleted successfully.');
            }

            $language->update(['is_enabled' => false]);
            return redirect()->route('languages.index')->with('success', 'Language disabled successfully.');
        } catch (\Exception $e) {
            return redirect()->route('languages.index')->with('error', 'Failed to remove language.');
        }
    }

    private function currentOrganizationId(): ?int
    {
        return SaasAccess::organization()?->id;
    }

    private function ensureTenantOwns(Language $language): void
    {
        if (
            $this->languageOwnerOrganizationId()
            && Schema::hasColumn('languages', 'organization_id')
            && (int) $language->organization_id !== (int) $this->languageOwnerOrganizationId()
        ) {
            abort(404);
        }
    }

    private function isPlatformAdmin(): bool
    {
        return SaasAccess::isPlatformAdmin();
    }

    private function hasLanguageEnablement(): bool
    {
        return Schema::hasColumn('languages', 'is_enabled');
    }

    private function platformOrganizationId(): ?int
    {
        return Schema::hasTable('organizations')
            ? DB::table('organizations')->where('slug', 'examelite')->value('id')
            : null;
    }

    private function languageOwnerOrganizationId(): ?int
    {
        return $this->isPlatformAdmin()
            ? $this->platformOrganizationId()
            : $this->currentOrganizationId();
    }

    private function availablePlatformLanguages()
    {
        $platformOrganizationId = $this->platformOrganizationId();
        $currentOrganizationId = $this->currentOrganizationId();

        if (! $platformOrganizationId || ! $currentOrganizationId) {
            return collect();
        }

        if (! $this->hasLanguageEnablement()) {
            return Language::where('organization_id', $platformOrganizationId)
                ->orderBy('name')
                ->get();
        }

        return Language::where('organization_id', $platformOrganizationId)
            ->whereNotExists(function ($query) use ($currentOrganizationId) {
                $query->select(DB::raw(1))
                    ->from('languages as organization_languages')
                    ->whereColumn('organization_languages.code', 'languages.code')
                    ->where('organization_languages.organization_id', $currentOrganizationId)
                    ->where('organization_languages.is_enabled', true);
            })
            ->orderBy('name')
            ->get();
    }

    private function languageValidationRules(?Language $language = null): array
    {
        $nameRule = Rule::unique('languages', 'name')
            ->where(fn ($query) => $query->where('organization_id', $this->languageOwnerOrganizationId()));
        $codeRule = Rule::unique('languages', 'code')
            ->where(fn ($query) => $query->where('organization_id', $this->languageOwnerOrganizationId()));

        if ($language) {
            $nameRule->ignore($language->id);
            $codeRule->ignore($language->id);
        }

        return [
            'name' => ['required', $nameRule],
            'code' => ['required', 'string', 'max:20', $codeRule],
            'value1' => 'nullable|string',
            'value2' => 'nullable|string',
        ];
    }
}
