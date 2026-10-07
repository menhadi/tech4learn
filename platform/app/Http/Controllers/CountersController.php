<?php

namespace App\Http\Controllers;

use App\Models\Counter;
use App\Models\Titles;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\App; // <-- 1. Ye import add kiya hai
use Illuminate\Support\Facades\File;

class CountersController extends Controller
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

    public function index(Request $request)
    {
        $query = $this->applyTenantScope(Counter::query());
        if ($request->has('search')) {
            // 2. Search logic ko JSON column ke liye update kiya
            $locale = App::getLocale();
            $query->where('title->'.$locale, 'like', '%' . $request->input('search') . '%')
                  ->orWhere('title->en', 'like', '%' . $request->input('search') . '%'); // Fallback
        }

        $data = $query->paginate(10);
        return view('counter.index', compact('data'));
    }

    public function store(Request $request)
    {
        try {
            // 3. Validation ko array input ke liye update kiya
            $validated = $request->validate([
                'image_url'   => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'icon'       => 'nullable|string|max:100',
                'number'      => 'required|numeric', // number translatable nahi hai
                'title.en'    => 'required|string|max:255', // English title required
                'title.*'     => 'nullable|string|max:255', // Baaki optional
            ]);

            if ($request->hasFile('image_url')) 
            {
                File::ensureDirectoryExists(public_path('uploads/counters'));
                $image = $request->file('image_url');
                $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('uploads/counters'), $imageName);
                $imagePath = 'uploads/counters/' . $imageName;
            }

            // Spatie package $request->title array ko automatically handle kar lega
            Counter::create([
                'organization_id' => $this->currentTenantId(),
                'image_url'   => $imagePath ?? '',
                'number'      => $validated['number'],
                'icon'        => $validated['icon'] ?? null,
                'title'       => $validated['title']
            ]);
            return redirect()->route('homepage-content.index', ['section' => 'counters'])->with('success', 'Counter created successfully.');
        } catch (ValidationException $e) {
            return redirect()->route('homepage-content.index', ['section' => 'counters'])->withErrors($e->validator)->withInput();
        } catch (\Exception $e) {
            report($e);
            return redirect()->route('homepage-content.index', ['section' => 'counters'])->with('error', 'Failed to create statistic. Please check the image and required English text.');
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
                'number'      => 'required|numeric',
                'title.en'    => 'required|string|max:255',
                'title.*'     => 'nullable|string|max:255',
            ]);

            $counter = $this->tenantFind(Counter::class, $id);

            $data = [
                'title'       => $validated['title'],
                'icon'        => $validated['icon'] ?? null,
                'number'      => $validated['number'],
            ];

            if ($request->hasFile('image_url')) {
                File::ensureDirectoryExists(public_path('uploads/counters'));
                $image = $request->file('image_url');
                $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('uploads/counters'), $imageName);
                $data['image_url'] = 'uploads/counters/' . $imageName;

                // Optional: delete old image
                if ($counter->image_url && file_exists(public_path($counter->image_url))) {
                    unlink(public_path($counter->image_url));
                }
            }
            
            $counter->update($data);
            
            return redirect()->route('homepage-content.index', ['section' => 'counters'])->with('success', 'Counter updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('homepage-content.index', ['section' => 'counters'])->withErrors($e->validator)->withInput();
        } catch (\Exception $e) {
            report($e);
            return redirect()->route('homepage-content.index', ['section' => 'counters'])->with('error', 'Failed to update statistic. Please check the image and required English text.');
        }
    }



    public function destroy($id)
    {
        try {
            $data = $this->tenantFind(Counter::class, $id);

            if (!$data) {
                return redirect()->route('homepage-content.index', ['section' => 'counters'])->with('error', 'Counter not found.');
            }

            if ($data->image_url && file_exists(public_path($data->image_url))) {
                unlink(public_path($data->image_url));
            }

           $data->delete();
            return redirect()->route('homepage-content.index', ['section' => 'counters'])->with('success', 'Counter deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->route('homepage-content.index', ['section' => 'counters'])->with('error', 'Failed to delete Counter.');
        }
    }
}
