<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Http\Kernel;
use App\Http\Middleware\{Tech4LearnWorkspaceContext,Tech4LearnWorkspaceGate};

final class Tech4LearnWorkspaceProvider extends ServiceProvider
{
    public function boot():void
    {
        $kernel=$this->app->make(Kernel::class);
        $kernel->prependMiddleware(Tech4LearnWorkspaceContext::class);
        $kernel->appendMiddlewareToGroup('web',Tech4LearnWorkspaceGate::class);
        $this->loadRoutesFrom(base_path('routes/tech4learn-workspace.php'));
        $this->loadViewsFrom(resource_path('views/tech4learn'),'tech4learn');
    }
}
