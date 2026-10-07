<?php

namespace App\Http\Middleware;

use App\Services\UrlRedirectService;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RedirectMissingPages
{
    public function __construct(private readonly UrlRedirectService $redirects) {}

    public function handle(Request $request, Closure $next)
    {
        try {
            $response = $next($request);
        } catch (NotFoundHttpException|ModelNotFoundException $exception) {
            return $this->redirectOrThrow($request, $exception);
        }

        if ($response->getStatusCode() !== 404) {
            return $response;
        }

        return $this->redirectFor($request) ?: $response;
    }

    private function redirectOrThrow(Request $request, NotFoundHttpException|ModelNotFoundException $exception)
    {
        $response = $this->redirectFor($request);

        if ($response) {
            return $response;
        }

        throw $exception;
    }

    private function redirectFor(Request $request)
    {
        $redirect = $this->redirects->findForRequest($request);

        if (! $redirect) {
            return null;
        }

        $this->redirects->recordHit($redirect);

        return new RedirectResponse($redirect->target_path, $redirect->status_code);
    }
}
