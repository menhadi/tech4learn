<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class GoogleAnalyticsController extends Controller
{
    public function index(\Illuminate\Http\Request $request, \App\Services\GoogleAnalyticsSettings $settings): \Illuminate\View\View
    {
        return view('configurations.analytics', ['analytics' => $settings->current($request->getHost()), 'host' => \App\Services\GoogleAnalyticsSettings::hostKey($request->getHost()), 'ready' => \Illuminate\Support\Facades\Schema::hasTable('google_analytics_settings')]);
    }

    public function update(\Illuminate\Http\Request $request, \App\Services\GoogleAnalyticsSettings $settings): \Illuminate\Http\RedirectResponse
    {
        abort_if(env('DEMO_MODE', false), 403, 'Changes are disabled in demo mode.');
        abort_unless(\Illuminate\Support\Facades\Schema::hasTable('google_analytics_settings'), 503, 'Analytics settings require the pending application migration.');
        $data = $request->validate([
            'enabled' => 'required|boolean',
            'measurement_id' => ['nullable', 'required_if:enabled,1', 'string', 'regex:/^G-[A-Z0-9]{4,20}$/D'],
        ]);
        $settings->save($request->getHost(), $request->boolean('enabled'), $data['measurement_id'] ?? null);

        return redirect()->route('configurations.analytics')->with('success', 'Analytics settings saved. Check GA4 Realtime to verify incoming visits.');
    }
}
