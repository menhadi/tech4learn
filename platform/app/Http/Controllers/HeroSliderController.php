<?php

namespace App\Http\Controllers;

use App\Models\HeroSlider;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class HeroSliderController extends Controller
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
        $query = $this->applyTenantScope(HeroSlider::query());

        if ($request->has('search')) {
            $query->where('title', 'like', '%' . $request->input('search') . '%');
        }

        $data = $query->paginate(10);
        return view('home_slider.index', compact('data'));
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'image_url'   => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
                'title' => 'required',
                'description' => 'required',
            ]);

            if ($request->hasFile('image_url')) 
            {
                $image = $request->file('image_url');
                $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                $uploadPath = public_path('uploads/hero-slider');
                if (! file_exists($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                }
                $image->move($uploadPath, $imageName);
                $imagePath = 'uploads/hero-slider/' . $imageName;
            }

            HeroSlider::create([
                'organization_id' => $this->currentTenantId(),
                'image_url'   => $imagePath,
                'title'       => $request->title,
                'description' => $request->description,
            ]);
            return redirect()->route('heroslider.index')->with('success', 'Hero Slider created successfully.');
        } catch (ValidationException $e) {
            return redirect()->route('heroslider.index')->withErrors($e->validator)->withInput();
        } catch (\Exception $e) {
            \Log::error('Hero Slider Create Error: ' . $e->getMessage());
            return redirect()->route('heroslider.index')->with('error', 'Failed to create hero slider: ' . $e->getMessage());
        }
    }

    public function update(Request $request, HeroSlider $heroslider)
    {
        try 
        {
            $request->validate([
                'image_url'   => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
                'title'       => 'required',
                'description' => 'required',
            ]);

            $data = [
                'title'       => $request->title,
                'description' => $request->description,
            ];

            if ($request->hasFile('image_url')) {
                $image = $request->file('image_url');
                $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                $uploadPath = public_path('uploads/hero-slider');
                if (! file_exists($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                }
                $image->move($uploadPath, $imageName);
                $data['image_url'] = 'uploads/hero-slider/' . $imageName;

                // Optional: delete old image
                if ($heroslider->image_url && file_exists(public_path($heroslider->image_url))) {
                    unlink(public_path($heroslider->image_url));
                }
            }

            $heroslider->update($data);
            
            return redirect()->route('heroslider.index')->with('success', 'Hero Slider updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->validator)->withInput();
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update Hero Slider.');
        }
    }



    public function destroy($id)
    {
        try {
            $data = $this->tenantFind(HeroSlider::class, $id);

            if (!$data) {
                return redirect()->route('heroslider.index')->with('error', 'Hero Slider not found.');
            }

            if ($data->image_url && file_exists(public_path($data->image_url))) {
                unlink(public_path($data->image_url));
            }

           $data->delete();
            return redirect()->route('heroslider.index')->with('success', 'Hero Slider deleted successfully.');
        } catch (\Exception $e) {
            \Log::error('Hero Slider Delete Error: ' . $e->getMessage());
            return redirect()->route('heroslider.index')->with('error', 'Failed to delete hero slider: ' . $e->getMessage());
        }
    }
}
