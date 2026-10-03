<?php

namespace App\Providers;

use App\Services\AinoClient;
use Carbon\Carbon;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AinoClient::class, fn () => new AinoClient(config('aino')));
    }

    public function boot(): void
    {
        Carbon::setLocale(config('app.locale', 'id'));
        \Illuminate\Http\Middleware\TrustProxies::at(config('siwarga.trusted_proxies'));
        Paginator::defaultView('components.pagination');

        Gate::define('pengurus', fn ($user) => $user->isPengurus());
        Gate::define('admin', fn ($user) => $user->isAdmin());
    }
}
