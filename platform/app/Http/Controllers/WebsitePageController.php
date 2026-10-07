<?php

namespace App\Http\Controllers;

use App\Models\WebsitePage;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\App; // <-- 1. Ye import add kiya hai

class WebsitePageController extends Controller
{
    private function tenantId(): ?int
    {
        return class_exists(\App\Support\Tenant::class)
            ? (\App\Support\Tenant::hostId(request()->getHost()) ?: \App\Support\Tenant::id())
            : null;
    }

    private function tenantQuery()
    {
        return WebsitePage::query()->when($this->tenantId(), fn ($query, $tenantId) => $query->where('organization_id', $tenantId));
    }
    public function index(Request $request)
    {
        $query = $this->tenantQuery();

        if ($request->has('search')) {
            // 2. Search logic ko JSON column ke liye update kiya
            $locale = App::getLocale();
            $query->where('title->'.$locale, 'like', '%' . $request->input('search') . '%')
                  ->orWhere('title->en', 'like', '%' . $request->input('search') . '%'); // Fallback
        }

        $data = $query->paginate(10);
        return view('website_pages.index', compact('data'));
    }

    public function store(Request $request)
    {
        try {
            // 3. Validation ko array input ke liye update kiya
            $request->validate([
                'short_title.en' => 'required|string|max:50',
                'short_title.*' => 'nullable|string|max:50',
                'title.en' => 'required|string|max:255',
                'title.*' => 'nullable|string|max:255',
                'description.en' => 'required|string',
                'description.*' => 'nullable|string',
                'show_in_menu' => 'nullable', // Ye waise hi rahega
                'show_in_footer' => 'nullable',
            ]);

            // Spatie package arrays ko handle kar lega
            WebsitePage::create([
                'organization_id' => $this->tenantId(),
                'short_title' => $request->short_title, // Array
                'title' => $request->title, // Array
                'description' => $request->description, // Array
                'show_in_menu' => $request->has('show_in_menu') ? 1 : 0,
                'show_in_footer' => $request->has('show_in_footer') ? 1 : 0,
                'meta_title' => $request->meta_title,
                'meta_description' => $request->meta_description,
                'meta_keywords' => $request->meta_keywords,
                'canonical_url' => $request->canonical_url,
                'og_title' => $request->og_title,
                'og_description' => $request->og_description,
                'og_image' => $request->og_image,
                'robots_meta' => $request->robots_meta ?: 'index,follow',
                'seo_schema' => $request->seo_schema,
            ]);
            
            return redirect()->route('websitepages.index')->with('success', 'Page created successfully.');
        } catch (ValidationException $e) {
            return redirect()->route('websitepages.index')->withErrors($e->validator)->withInput();
        } catch (\Exception $e) {
            return redirect()->route('websitepages.index')->with('error', 'Failed to create page.');
        }
    }

    public function update(Request $request, $id)
    {
        try {
            // 4. Validation ko array input ke liye update kiya
            $request->validate([
                'short_title.en' => 'required|string|max:50',
                'short_title.*' => 'nullable|string|max:50',
                'title.en' => 'required|string|max:255',
                'title.*' => 'nullable|string|max:255',
                'description.en' => 'required|string',
                'description.*' => 'nullable|string',
                'show_in_menu' => 'nullable', // Ye waise hi rahega
                'show_in_footer' => 'nullable',
            ]);
            
             $websitepages = $this->tenantQuery()->findOrFail($id);
            
            // Spatie package arrays ko handle kar lega
            $websitepages->update([
                'short_title' => $request->short_title, // Array
                'title' => $request->title, // Array
                'description' => $request->description, // Array
                'show_in_menu' => $request->has('show_in_menu') ? 1 : 0,
                'show_in_footer' => $request->has('show_in_footer') ? 1 : 0,
            ]);
            
            return redirect()->route('websitepages.index')->with('success', 'Page updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->validator)->withInput();
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update page.');
        }
    }

    public function destroy($id)
    {
        try {
            $websitepages = $this->tenantQuery()->findOrFail($id);
            $websitepages->delete();
            return redirect()->route('websitepages.index')->with('success', 'Page deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->route('websitepages.index')->with('error', 'Failed to delete page.');
        }
    }
}
