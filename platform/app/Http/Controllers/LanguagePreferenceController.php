<?php

namespace App\Http\Controllers;

use App\Services\UiLanguageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LanguagePreferenceController extends Controller
{
    public function update(Request $request, string $locale, UiLanguageService $languages): RedirectResponse
    {
        $locale = strtolower($locale);
        abort_unless($languages->supports($locale), 404);

        $request->session()->put([
            'locale' => $locale,
            'direction' => $languages->direction($locale),
        ]);

        if ($user = Auth::user()) {
            $user->forceFill(['language' => $locale])->save();
        }
        if ($student = Auth::guard('student')->user()) {
            $student->forceFill(['language' => $locale])->save();
        }

        return redirect()->back()->withCookie(cookie('locale', $locale, 60 * 24 * 365));
    }
}
