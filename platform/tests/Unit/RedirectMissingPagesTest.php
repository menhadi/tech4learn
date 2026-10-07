<?php

namespace Tests\Unit;

use App\Http\Middleware\RedirectMissingPages;
use App\Models\UrlRedirect;
use App\Services\UrlRedirectService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class RedirectMissingPagesTest extends TestCase
{
    public function test_it_redirects_only_after_a_not_found_response(): void
    {
        $service = new FakeUrlRedirectService($this->redirect());
        $middleware = new RedirectMissingPages($service);
        $request = Request::create('/old-exam', 'GET');

        $response = $middleware->handle($request, fn () => new Response('missing', 404));

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('/courses/current-exam', $response->headers->get('Location'));
        $this->assertSame(1, $service->recordedHits);

        $workingResponse = $middleware->handle($request, fn () => new Response('working', 200));
        $this->assertSame(200, $workingResponse->getStatusCode());
        $this->assertSame(1, $service->recordedHits);
    }

    public function test_it_handles_model_not_found_errors_from_dynamic_public_routes(): void
    {
        $service = new FakeUrlRedirectService($this->redirect());
        $middleware = new RedirectMissingPages($service);

        $response = $middleware->handle(
            Request::create('/old-exam', 'GET'),
            fn () => throw new ModelNotFoundException
        );

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('/courses/current-exam', $response->headers->get('Location'));
    }

    public function test_it_preserves_a_real_404_when_no_mapping_exists(): void
    {
        $middleware = new RedirectMissingPages(new FakeUrlRedirectService(null));

        $this->expectException(ModelNotFoundException::class);

        $middleware->handle(
            Request::create('/unmapped-page', 'GET'),
            fn () => throw new ModelNotFoundException
        );
    }

    private function redirect(): UrlRedirect
    {
        return new UrlRedirect([
            'organization_id' => 1,
            'source_path' => '/old-exam',
            'target_path' => '/courses/current-exam',
            'status_code' => 301,
            'is_active' => true,
        ]);
    }
}

class FakeUrlRedirectService extends UrlRedirectService
{
    public int $recordedHits = 0;

    public function __construct(private readonly ?UrlRedirect $redirect) {}

    public function findForRequest(Request $request): ?UrlRedirect
    {
        return $this->redirect;
    }

    public function recordHit(UrlRedirect $redirect): void
    {
        $this->recordedHits++;
    }
}
