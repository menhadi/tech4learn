<?php

namespace App\Http\Controllers;

use App\Models\Passage;
use App\Models\Language;
use App\Models\PassageLang;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PassageController extends Controller
{
    private function tenantId(): ?int
    {
        return class_exists(\App\Support\Tenant::class) ? \App\Support\Tenant::id() : null;
    }

    private function tenantPassageQuery()
    {
        return Passage::query()->when($this->tenantId(), function ($q, $tenantId) {
            $q->where('organization_id', $tenantId);
        });
    }

    private function ensureTenantOwns(Passage $passage): void
    {
        if ($this->tenantId() && (int) ($passage->organization_id ?? 0) !== (int) $this->tenantId()) {
            abort(404);
        }
    }

    public function index(Request $request)
    {
        $query = $this->tenantPassageQuery();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%');
        }

        $perPage = (int) $request->input('per_page', 50);
        $perPage = in_array($perPage, [50, 100, 500], true) ? $perPage : 50;

        $passages = $query->paginate($perPage)->withQueryString();
        return view('passages.index', compact('passages', 'perPage'));
    }

    public function create()
    {
        $languages = Language::enabledForOrganization($this->tenantId())->orderBy('name')->get();
        return view('passages.action', compact('languages'));
    }

    public function edit(Passage $passage)
    {
        $this->ensureTenantOwns($passage);
        $languages = Language::enabledForOrganization($this->tenantId())->orderBy('name')->get();
        return view('passages.action', compact('passage', 'languages'));
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|unique:passages,name',
                'passages.*' => 'required|string',
            ]);
            $this->validateEnabledPassageLanguages($request);

            $passage = Passage::create([
                'organization_id' => $this->tenantId(),
                'name' => $request->input('name'),
            ]);

            foreach ($request->input('passages') as $languageId => $translation) {
                PassageLang::create([
                    'passage_id' => $passage->id,
                    'language_id' => $languageId,
                    'passage' => $translation,
                ]);
            }

            return redirect()->route('passages.index')->with('success', 'Passage created successfully.');
        } catch (ValidationException $e) {
            return redirect()->route('passages.create')->withErrors($e->validator)->withInput();
        } catch (\Exception $e) {
            return redirect()->route('passages.create')->with('error', 'Failed to create passage.');
        }
    }

    public function update(Request $request, Passage $passage)
    {
        $this->ensureTenantOwns($passage);
        try {
            $request->validate([
                'name' => 'required|string|unique:passages,name,' . $passage->id,
                'passages.*' => 'required|string',
            ]);
            $this->validateEnabledPassageLanguages($request);

            $passage->update([
                'name' => $request->input('name'),
            ]);

            foreach ($request->input('passages') as $languageId => $translation) {
                PassageLang::updateOrCreate(
                    ['passage_id' => $passage->id, 'language_id' => $languageId],
                    ['passage' => $translation]
                );
            }

            return redirect()->route('passages.index')->with('success', 'Passage updated successfully.');
        } catch (ValidationException $e) {
            return redirect()->route('passages.edit', $passage->id)->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            return redirect()->route('passages.edit', $passage->id)->with('error', 'Failed to update passage.');
        }
    }

    public function destroy($id)
    {
        try {
            $passage = $this->tenantPassageQuery()->findOrFail($id);
            $passage->delete();
            return redirect()->route('passages.index')->with('success', 'Passage deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->route('passages.index')->with('error', 'Failed to delete passage.');
        }
    }

    private function validateEnabledPassageLanguages(Request $request): void
    {
        $languageIds = array_map('intval', array_keys($request->input('passages', [])));
        $enabledCount = Language::enabledForOrganization($this->tenantId())
            ->whereIn('id', $languageIds)
            ->count();

        if ($enabledCount !== count(array_unique($languageIds))) {
            abort(422, 'Invalid language selected.');
        }
    }
}
