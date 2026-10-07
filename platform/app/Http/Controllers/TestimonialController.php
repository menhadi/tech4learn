<?php

namespace App\Http\Controllers;

use App\Models\Testimonial;
use App\Models\Titles;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\App; // <-- 1. Ye import add kiya hai
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;

class TestimonialController extends Controller
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
        $query = $this->applyTenantScope(Testimonial::query());
        $titles = $this->sectionTitle('testimonials', 2);
        if ($request->has('search')) {
            // 2. Search logic ko JSON 'name' column ke liye update kiya
            $locale = App::getLocale();
            $query->where('name->'.$locale, 'like', '%' . $request->input('search') . '%')
                  ->orWhere('name->en', 'like', '%' . $request->input('search') . '%'); // Fallback
        }

        $data = $query->paginate(10);
        return view('testimonial.index', compact('data','titles'));
    }

    public function store(Request $request)
    {
        try {
            // 3. Validation ko array input ke liye update kiya
            $validated = $request->validate([
                'image_url'   => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'name.en' => 'required|string|max:255', // English name required
                'name.*' => 'nullable|string|max:255',
                'feedback.en' => 'required|string|max:255', // English feedback required
                'feedback.*' => 'nullable|string|max:255',
            ]);

            if ($request->hasFile('image_url')) 
            {
                File::ensureDirectoryExists(public_path('uploads/testimonial'));
                $image = $request->file('image_url');
                $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('uploads/testimonial'), $imageName);
                $imagePath = 'uploads/testimonial/' . $imageName;
            }

            // Spatie package $request->name aur $request->feedback arrays ko handle kar lega
            Testimonial::create([
                'organization_id' => $this->currentTenantId(),
                'image_url'   => $imagePath ?? '',
                'name'       => $validated['name'],
                'feedback' => $validated['feedback']
            ]);
            return redirect()->route('homepage-content.index', ['section' => 'testimonials'])->with('success', 'Testimonial created successfully.');
        } catch (ValidationException $e) {
            return redirect()->route('homepage-content.index', ['section' => 'testimonials'])->withErrors($e->validator)->withInput();
        } catch (\Exception $e) {
            report($e);
            return redirect()->route('homepage-content.index', ['section' => 'testimonials'])->with('error', 'Failed to create student review. Please check the image and required English text.');
        }
    }

    public function update(Request $request, $id)
    {
        try 
        {
            // 4. Validation ko array input ke liye update kiya
            $validated = $request->validate([
                'image_url'   => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'name.en'       => 'required|string|max:255',
                'name.*'       => 'nullable|string|max:255',
                'feedback.en' => 'required|string|max:255',
                'feedback.*' => 'nullable|string|max:255',
            ]);

            $testimonial = $this->tenantFind(Testimonial::class, $id);

            $data = [
                'name'       => $validated['name'],
                'feedback' => $validated['feedback'],
            ];

            if ($request->hasFile('image_url')) {
                File::ensureDirectoryExists(public_path('uploads/testimonial'));
                $image = $request->file('image_url');
                $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('uploads/testimonial'), $imageName);
                $data['image_url'] = 'uploads/testimonial/' . $imageName;

                // Optional: delete old image
                if ($testimonial->image_url && file_exists(public_path($testimonial->image_url))) {
                    unlink(public_path($testimonial->image_url));
                }
            }
            
            $testimonial->update($data);
            
            return redirect()->route('homepage-content.index', ['section' => 'testimonials'])->with('success', 'Testimonial updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('homepage-content.index', ['section' => 'testimonials'])->withErrors($e->validator)->withInput();
        } catch (\Exception $e) {
            report($e);
            return redirect()->route('homepage-content.index', ['section' => 'testimonials'])->with('error', 'Failed to update student review. Please check the image and required English text.');
        }
    }



    public function destroy($id)
    {
        try {
            $data = $this->tenantFind(Testimonial::class, $id);

            if (!$data) {
                return redirect()->route('homepage-content.index', ['section' => 'testimonials'])->with('error', 'Testimonial not found.');
            }

            if ($data->image_url && file_exists(public_path($data->image_url))) {
                unlink(public_path($data->image_url));
            }

           $data->delete();
            return redirect()->route('homepage-content.index', ['section' => 'testimonials'])->with('success', 'Testimonial deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->route('homepage-content.index', ['section' => 'testimonials'])->with('error', 'Failed to delete Testimonial.');
        }
    }
}
