<?php

namespace App\Http\Controllers;

use App\Models\UrlRedirect;
use App\Services\UrlRedirectService;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UrlRedirectController extends Controller
{
    public function index(Request $request)
    {
        $redirects = $this->tenantQuery()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));
                $query->where(function ($query) use ($search) {
                    $query->where('source_path', 'like', '%'.$search.'%')
                        ->orWhere('target_path', 'like', '%'.$search.'%')
                        ->orWhere('note', 'like', '%'.$search.'%');
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('redirects.index', compact('redirects'));
    }

    public function store(Request $request)
    {
        $data = $this->validateRedirect($request);
        $this->tenantQuery()->create($data + ['organization_id' => $this->tenantId()]);

        return redirect()->route('redirects.index')->with('success', 'Redirect created successfully.');
    }

    public function update(Request $request, UrlRedirect $redirect)
    {
        $redirect = $this->ownedRedirect($redirect);
        $redirect->update($this->validateRedirect($request, $redirect));

        return redirect()->route('redirects.index')->with('success', 'Redirect updated successfully.');
    }

    public function destroy(UrlRedirect $redirect)
    {
        $this->ownedRedirect($redirect)->delete();

        return redirect()->route('redirects.index')->with('success', 'Redirect deleted successfully.');
    }

    private function validateRedirect(Request $request, ?UrlRedirect $redirect = null): array
    {
        $sourcePath = UrlRedirectService::normalizeSourcePath($request->input('source_path'));
        $targetPath = UrlRedirectService::normalizeTargetPath($request->input('target_path'));
        $request->merge([
            'source_path' => $sourcePath,
            'target_path' => $targetPath,
            'is_active' => $request->boolean('is_active'),
        ]);

        $validator = validator($request->all(), [
            'source_path' => [
                'required',
                'string',
                'max:700',
                Rule::unique('url_redirects', 'source_path')
                    ->where('organization_id', $this->tenantId())
                    ->ignore($redirect?->id),
            ],
            'target_path' => ['required', 'string', 'max:2048', 'regex:#^/(?!/)#'],
            'status_code' => ['required', 'integer', Rule::in([301, 302])],
            'is_active' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'target_path.regex' => 'Use an internal destination beginning with one slash, for example /courses.',
        ]);

        $validator->after(function ($validator) use ($sourcePath, $targetPath, $redirect, $request) {
            if (! $sourcePath || ! $targetPath) {
                return;
            }

            $targetPathOnly = UrlRedirectService::targetPathOnly($targetPath);

            if ($sourcePath === $targetPathOnly) {
                $validator->errors()->add('target_path', 'The destination must be different from the old URL.');
            }

            if ($this->isProtectedPath($sourcePath)) {
                $validator->errors()->add('source_path', 'Admin, account, API, and storage paths cannot be managed here.');
            }

            if ($this->isProtectedPath($targetPathOnly)) {
                $validator->errors()->add('target_path', 'Choose a public website page as the destination.');
            }

            if (! $request->boolean('is_active')) {
                return;
            }

            $otherRedirects = $this->tenantQuery()
                ->where('is_active', true)
                ->when($redirect, fn ($query) => $query->whereKeyNot($redirect->getKey()));

            if ((clone $otherRedirects)->where('source_path', $targetPathOnly)->exists()) {
                $validator->errors()->add('target_path', 'This destination redirects again. Point directly to the final working page.');
            }

            if ((clone $otherRedirects)->where(function ($query) use ($sourcePath) {
                $query->where('target_path', $sourcePath)
                    ->orWhere('target_path', 'like', $sourcePath.'?%');
            })->exists()) {
                $validator->errors()->add('source_path', 'Another active redirect already points here. Point both old URLs directly to the final page.');
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    private function tenantQuery()
    {
        return UrlRedirect::query()->forOrganization($this->tenantId());
    }

    private function tenantId(): int
    {
        return (int) Tenant::id(request()->getHost());
    }

    private function ownedRedirect(UrlRedirect $redirect): UrlRedirect
    {
        abort_unless((int) $redirect->organization_id === $this->tenantId(), 404);

        return $redirect;
    }

    private function isProtectedPath(string $path): bool
    {
        foreach (['/admin', '/api', '/auth', '/login', '/logout', '/password', '/student', '/storage'] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }
}
