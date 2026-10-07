<?php

namespace App\Http\Controllers;

use App\Models\Features;
use App\Models\Titles;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\App; // <-- 1. Ye import add kiya hai
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;

class FeaturesController extends Controller
{
    private function currentTenantId(): ?int
    {
        return class_exists(\App\Support\Tenant::class)
            ? (\App\Support\Tenant::hostId(request()->getHost()) ?: \App\Support\Tenant::id())
            : null;
    }

    private function applyTenantScope($query)
    {
        return $query->when($this->currentTenantId(), function ($q, $tenantId) {
            $q->where('organization_id', $tenantId);
        });
    }

    private function tenantFind($modelClass, $id)
    {
        return $this->applyTenantScope($modelClass::query())->findOrFail($id);
    }

    private function sectionTitle(string $sectionKey, int $legacyId)
    {
        $query = Titles::query();

        if (Schema::hasColumn('titles', 'section_key')) {
            $query->where('section_key', $sectionKey);
        } else {
            $query->where('id', $legacyId);
        }

        $tenantQuery = clone $query;

        return $this->applyTenantScope($tenantQuery)->first() ?: $query->first();
    }

    public function index(Request $request)
    {
        $query = $this->applyTenantScope(Features::query());
        $titles = $this->sectionTitle('features', 1);
        
        if ($request->has('search')) {
            // 2. Search logic ko JSON column ke liye update kiya
            $locale = App::getLocale();
            $query->where('title->'.$locale, 'like', '%' . $request->input('search') . '%')
                  ->orWhere('title->en', 'like', '%' . $request->input('search') . '%'); // Fallback
        }

        $data = $query->paginate(10);
        return view('features.index', compact('data','titles'));
    }

    public function store(Request $request)
    {
        try {
            // 3. Validation ko array input ke liye update kiya
            $validated = $request->validate([
                'image_url'   => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'icon'       => 'nullable|string|max:100',
                'title.en' => 'required|string|max:255', // English title required
                'title.*' => 'nullable|string|max:255', // Baaki optional
                'description.en' => 'required|string|max:255', // English desc required
                'description.*' => 'nullable|string|max:255', // Baaki optional
            ]);

            if ($request->hasFile('image_url')) 
            {
                File::ensureDirectoryExists(public_path('uploads/features'));
                $image = $request->file('image_url');
                $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('uploads/features'), $imageName);
                $imagePath = 'uploads/features/' . $imageName;
            }

            // Spatie package $request->title array ko automatically handle kar lega
            Features::create([
                'organization_id' => $this->currentTenantId(),
                'image_url'   => $imagePath ?? '',
                'icon'        => $validated['icon'] ?? null,
                'title'       => $validated['title'],
                'description' => $validated['description']
            ]);
            return redirect()->route('homepage-content.index', ['section' => 'features'])->with('success', 'Features created successfully.');
        } catch (ValidationException $e) {
            return redirect()->route('homepage-content.index', ['section' => 'features'])->withErrors($e->validator)->withInput();
        } catch (\Exception $e) {
            report($e);
            return redirect()->route('homepage-content.index', ['section' => 'features'])->with('error', 'Failed to create benefit. Please check the image and required English text.');
        }
    }

    public function update(Request $request, $id)
    {
        try 
        {
            // 4. Validation ko array input ke liye update kiya
            $validated = $request->validate([
                'image_url'   => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'icon'       => 'nullable|string|max:100',
                'title.en'       => 'required|string|max:255',
                'title.*'       => 'nullable|string|max:255',
                'description.en' => 'required|string|max:255',
                'description.*' => 'nullable|string|max:255',
            ]);


            $features = $this->tenantFind(Features::class, $id);
            
            // Spatie package $request->title array ko automatically handle kar lega
            $data = [
                'title'       => $validated['title'],
                'icon'        => $validated['icon'] ?? null,
                'description' => $validated['description'],
            ];

            if ($request->hasFile('image_url')) {
                File::ensureDirectoryExists(public_path('uploads/features'));
                $image = $request->file('image_url');
                $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('uploads/features'), $imageName);
                $data['image_url'] = 'uploads/features/' . $imageName;

                // Optional: delete old image
                if ($features->image_url && file_exists(public_path($features->image_url))) {
                    unlink(public_path($features->image_url));
                }
            }
            $features->update($data);
            
            return redirect()->route('homepage-content.index', ['section' => 'features'])->with('success', 'Features updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('homepage-content.index', ['section' => 'features'])->withErrors($e->validator)->withInput();
        } catch (\Exception $e) {
            report($e);
            return redirect()->route('homepage-content.index', ['section' => 'features'])->with('error', 'Failed to update benefit. Please check the image and required English text.');
        }
    }



    public function destroy($id)
    {
        try {
            $data = $this->tenantFind(Features::class, $id);

            if (!$data) {
                return redirect()->route('homepage-content.index', ['section' => 'features'])->with('error', 'Features not found.');
            }

            if ($data->image_url && file_exists(public_path($data->image_url))) {
                unlink(public_path($data->image_url));
            }

           $data->delete();
            return redirect()->route('homepage-content.index', ['section' => 'features'])->with('success', 'Features deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->route('homepage-content.index', ['section' => 'features'])->with('error', 'Failed to delete Features.');
        }
    }
}
