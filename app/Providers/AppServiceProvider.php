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

        // Hosting tanpa akses terminal (mis. Railway): buat tautan public/storage otomatis bila belum ada
        if (! file_exists(public_path('storage'))) {
            try {
                @mkdir(storage_path('app/public'), 0775, true);
                app('files')->link(storage_path('app/public'), public_path('storage'));
            } catch (\Throwable) {
                // abaikan; bisa dibuat manual dengan `php artisan storage:link`
            }
        }

        Gate::define('pengurus', fn ($user) => $user->isPengurus());
        Gate::define('admin', fn ($user) => $user->isAdmin());
    }
}
