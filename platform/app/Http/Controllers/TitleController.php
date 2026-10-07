<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Titles;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Schema;

class TitleController extends Controller
{
    private function currentTenantId(): ?int
    {
        return class_exists(\App\Support\Tenant::class)
            ? (\App\Support\Tenant::hostId(request()->getHost()) ?: \App\Support\Tenant::id())
            : null;
    }

    private function titleSectionKey(int $id): string
    {
        return [
            1 => 'features',
            2 => 'testimonials',
            3 => 'packages',
            4 => 'banner',
            5 => 'top_performers',
            6 => 'counters',
        ][$id] ?? 'section_' . $id;
    }

    public function update_title(Request $request)
    {
        try {
            // 1. Validation ko update kiya
            // Hum 'en' (English) ko required rakhenge
            $validated = $request->validate([
                'title.en'     => 'required|string|max:255',
                'sub_title.en' => 'required|string|max:255',
                'title.*'      => 'nullable|string|max:255', // Baaki languages optional hain
                'sub_title.*'  => 'nullable|string|max:255',
            ]);

            $sectionId = (int) $request->id;
            $tenantId = $this->currentTenantId();
            $sectionKey = $this->titleSectionKey($sectionId);

            if ($tenantId && Schema::hasColumn('titles', 'organization_id') && Schema::hasColumn('titles', 'section_key')) {
                $data = Titles::firstOrNew([
                    'organization_id' => $tenantId,
                    'section_key' => $sectionKey,
                ]);
            } else {
                $data = Titles::findOrFail($sectionId);
            }
            
            // 2. Update logic ko badla
            // Hum 'validated' array pass nahi karenge
            // Hum seedha 'title' aur 'sub_title' arrays ko pass karenge
            // Package ye JSON mein convert kar dega
            $data->title = $validated['title'];
            $data->sub_title = $validated['sub_title'];
            $data->save();

            return redirect()->route('homepage-content.index', ['section' => $sectionKey])->with('success', 'Title updated successfully.');
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('homepage-content.index', ['section' => $this->titleSectionKey((int) $request->id)])->withErrors($e->validator)->withInput();
        } catch (\Exception $e) {
            // Error message ko specific kiya
            report($e);
            return redirect()->route('homepage-content.index', ['section' => $this->titleSectionKey((int) $request->id)])->with('error', 'Failed to update section heading.');
        }
    }
}
