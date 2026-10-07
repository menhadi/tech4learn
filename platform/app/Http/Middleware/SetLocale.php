<?php

namespace App\Http\Middleware;

use App\Services\UiLanguageService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $defaultLocale = config('app.locale', 'en');
        $preferred = session('locale')
            ?: Auth::guard('student')->user()?->language
            ?: Auth::user()?->language
            ?: $request->cookie('locale')
            ?: $defaultLocale;
        $languages = app(UiLanguageService::class);
        $locale = $languages->supports((string) $preferred)
            ? strtolower((string) $preferred)
            : $defaultLocale;

        session()->put('locale', $locale);
        App::setLocale($locale);
        session()->put('direction', $languages->direction($locale));

        return $next($request);
    }
}