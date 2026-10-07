<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AboutUs;
use Illuminate\Validation\ValidationException;

class AboutUsController extends Controller
{
    public function index(Request $request)
    {
        // 'About Us' hamesha 1 hi entry hoti hai, isliye 'first()' sahi hai
        $data = AboutUs::where('id','1')->first();

        // Agar database mein entry nahi hai (fresh install), toh ek empty object bana do
        if (!$data) {
            $data = new AboutUs();
            $data->id = 1; // Taaki form ka update route kaam kare
        }

        return view('aboutus.index', compact('data'));
    }



    public function update(Request $request, $id)
    {
        try {
            // 1. Validation ko array input ke liye update kiya
            $request->validate([
                'image_url'   => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
                'title.en'       => 'required|string|max:255', // English title required
                'title.*'       => 'nullable|string|max:255',
                'description.en' => 'required|string', // English description required
                'description.*' => 'nullable|string',

                'meta_title' => 'nullable|string|max:191',
                'meta_description' => 'nullable|string',
                'meta_keywords' => 'nullable|string',
                'canonical_url' => 'nullable|string|max:255',
                'og_title' => 'nullable|string|max:191',
                'og_description' => 'nullable|string',
                'og_image' => 'nullable|string|max:255',
                'robots_meta' => 'nullable|string|max:50',
                'seo_schema' => 'nullable|string',
            ]);

            // findOrFail ki jagah findOrNew use karenge taaki agar data na ho toh naya bana de
            $aboutus = AboutUs::findOrNew($id);

            // Spatie package $request->title aur $request->description arrays ko handle kar lega
            $data = [
                'title'       => $request->input('title'),
                'description' => $request->input('description'),
                'meta_title' => $request->meta_title,
                'meta_description' => $request->meta_description,
                'meta_keywords' => $request->meta_keywords,
                'canonical_url' => $request->canonical_url,
                'og_title' => $request->og_title,
                'og_description' => $request->og_description,
                'og_image' => $request->og_image,
                'robots_meta' => $request->robots_meta ?: 'index,follow',
                'seo_schema' => $request->seo_schema,
            ];

            if ($request->hasFile('image_url')) {
                $image = $request->file('image_url');
                $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                $imagePath = 'uploads/aboutus/' . $imageName;

                $image->move(public_path('uploads/aboutus'), $imageName);
                $data['image_url'] = $imagePath;

                if (!empty($aboutus->image_url) && file_exists(public_path($aboutus->image_url))) {
                    @unlink(public_path($aboutus->image_url));
                }
            }

            $aboutus->fill($data); // fill() ka use data set karne ke liye
            $aboutus->id = 1; // Ensure ID 1 hi rahe
            $aboutus->save(); // save() ka use naya create karne ya update karne ke liye

            return redirect()->route('aboutus.index')->with('success', 'About Us updated successfully.');

        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->validator)->withInput();
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update About Us. ' . $e->getMessage());
        }
    }
}