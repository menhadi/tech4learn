<?php

namespace Tests\Unit;

use App\Http\Controllers\SeoIntegrationController;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Laravel\Socialite\Contracts\Factory;
use Laravel\Socialite\SocialiteManager;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class GoogleOAuthIsolationTest extends TestCase
{
    public function test_seo_provider_does_not_replace_cached_student_login_credentials(): void
    {
        $previousContainer = Container::getInstance();
        $previousFacadeApplication = Facade::getFacadeApplication();
        $container = new Container;
        Container::setInstance($container);
        Facade::setFacadeApplication($container);
        Facade::clearResolvedInstances();

        try {
            $login = ['client_id' => 'login-client', 'client_secret' => 'login-secret', 'redirect' => 'https://example.com/auth/google/callback'];
            $seo = ['client_id' => 'seo-client', 'client_secret' => 'seo-secret', 'redirect' => 'https://example.com/admin/seo/google/callback'];
            $container->instance('config', new Repository(['services' => ['google' => $login, 'google_search_console' => $seo]]));
            $container->instance('request', Request::create('https://example.com'));
            $manager = new SocialiteManager($container);
            $container->instance(Factory::class, $manager);
            $loginProvider = $manager->driver('google');

            $method = new ReflectionMethod(SeoIntegrationController::class, 'googleProvider');
            $seoProvider = $method->invoke(new SeoIntegrationController);
            parse_str(parse_url($seoProvider->stateless()->redirect()->getTargetUrl(), PHP_URL_QUERY), $seoQuery);
            parse_str(parse_url($loginProvider->stateless()->redirect()->getTargetUrl(), PHP_URL_QUERY), $loginQuery);

            self::assertSame('seo-client', $seoQuery['client_id']);
            self::assertSame($seo['redirect'], $seoQuery['redirect_uri']);
            self::assertSame('login-client', $loginQuery['client_id']);
            self::assertSame($login['redirect'], $loginQuery['redirect_uri']);
            self::assertSame($login, $container['config']->get('services.google'));
        } finally {
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($previousFacadeApplication);
            Container::setInstance($previousContainer);
        }
    }
}
