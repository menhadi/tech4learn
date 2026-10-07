<?php

namespace App\Http\Controllers;

use App\Models\SeoIntegration;
use App\Services\SearchConsoleService;
use App\Support\SaasAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Throwable;

class SeoIntegrationController extends Controller
{
    public function redirectToGoogle()
    {
        SaasAccess::abortIfFeatureDisabled('ai_seo');

        if (! config('services.google_search_console.client_id') || ! config('services.google_search_console.client_secret')) {
            return back()->with('error', 'Add GOOGLE_SEARCH_CONSOLE_CLIENT_ID and GOOGLE_SEARCH_CONSOLE_CLIENT_SECRET before connecting Search Console.');
        }

        return $this->googleProvider()
            ->scopes(['https://www.googleapis.com/auth/webmasters.readonly'])
            ->with([
                'access_type' => 'offline',
                'prompt' => 'consent',
                'include_granted_scopes' => 'true',
            ])
            ->redirect();
    }

    public function handleGoogleCallback(Request $request, SearchConsoleService $searchConsole)
    {
        SaasAccess::abortIfFeatureDisabled('ai_seo');

        if (! Schema::hasTable('seo_integrations')) {
            return redirect()->route('admin.seo.dashboard')->with('error', 'Run database migrations before connecting Search Console.');
        }

        try {
            $googleUser = $this->googleProvider()->user();
            $integration = $this->integration() ?? new SeoIntegration([
                'organization_id' => $this->tenantId(),
                'provider' => 'google_search_console',
            ]);

            $integration->fill([
                'access_token' => $googleUser->token,
                'refresh_token' => $googleUser->refreshToken ?: $integration->refresh_token,
                'token_expires_at' => now()->addSeconds(max(60, (int) ($googleUser->expiresIn ?? 3600) - 60)),
                'status' => 'connected',
                'last_error' => null,
            ])->save();

            $properties = $searchConsole->properties($integration);
            $propertyUrl = $this->bestProperty($properties);
            $integration->data = array_merge($integration->data ?? [], ['properties' => $properties]);
            $integration->property_url = $propertyUrl ?: $integration->property_url;
            $integration->save();

            if ($integration->property_url) {
                $searchConsole->sync($integration);
            }

            return redirect()->route('admin.seo.dashboard')
                ->with('success', $integration->property_url
                    ? 'Search Console connected and synchronized.'
                    : 'Google connected. Choose a Search Console property to continue.');
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('admin.seo.dashboard')
                ->with('error', 'Search Console connection failed: ' . $exception->getMessage());
        }
    }

    public function selectProperty(Request $request, SearchConsoleService $searchConsole)
    {
        SaasAccess::abortIfFeatureDisabled('ai_seo');
        $integration = $this->integrationOrFail();
        $validated = $request->validate(['property_url' => 'required|string|max:500']);
        $allowed = collect($integration->data['properties'] ?? [])->pluck('url');

        abort_unless($allowed->contains($validated['property_url']), 422, 'Choose a property returned by Google.');

        $integration->update(['property_url' => $validated['property_url'], 'last_error' => null]);

        try {
            $searchConsole->sync($integration);
            return back()->with('success', 'Search Console property selected and synchronized.');
        } catch (Throwable $exception) {
            $integration->update(['last_error' => $exception->getMessage()]);
            return back()->with('error', 'Property saved, but the first sync failed: ' . $exception->getMessage());
        }
    }

    public function sync(SearchConsoleService $searchConsole)
    {
        SaasAccess::abortIfFeatureDisabled('ai_seo');
        $integration = $this->integrationOrFail();

        try {
            $searchConsole->sync($integration);
            return back()->with('success', 'Search Console data refreshed.');
        } catch (Throwable $exception) {
            $integration->update(['last_error' => $exception->getMessage()]);
            return back()->with('error', 'Search Console sync failed: ' . $exception->getMessage());
        }
    }

    public function disconnect()
    {
        SaasAccess::abortIfFeatureDisabled('ai_seo');
        $this->integration()?->delete();

        return back()->with('success', 'Search Console disconnected and stored tokens removed.');
    }

    private function integration(): ?SeoIntegration
    {
        return SeoIntegration::query()
            ->forOrganization($this->tenantId())
            ->where('provider', 'google_search_console')
            ->first();
    }

    private function integrationOrFail(): SeoIntegration
    {
        return $this->integration() ?? abort(404, 'Search Console is not connected.');
    }

    private function tenantId(): ?int
    {
        return class_exists(AppSupportTenant::class) ? AppSupportTenant::id() : null;
    }

    private function googleProvider(): GoogleProvider
    {
        $config = config('services.google_search_console');
        $config['redirect'] = $config['redirect'] ?: route('admin.seo.google.callback');

        return Socialite::buildProvider(GoogleProvider::class, $config);
    }

    private function bestProperty(array $properties): ?string
    {
        if (count($properties) === 1) return $properties[0]['url'];

        $domain = strtolower((string) (getConfiguration()->domain_name ?? request()->getHost()));

        return collect($properties)
            ->first(fn (array $property) => str_contains(strtolower($property['url']), $domain))['url'] ?? null;
    }
}
