<?php

namespace App\Providers;

use App\Adapters\EstrellaAdapter;
use App\Services\JwtService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(JwtService::class, fn () => new JwtService(
            config('jwt.secret'),
            config('jwt.ttl_minutos'),
            config('jwt.emisor'),
        ));

        $this->app->bind(EstrellaAdapter::class, fn () => new EstrellaAdapter(
            config('services.estrella.url'),
            (int) config('services.estrella.timeout'),
            (int) config('services.estrella.intentos'),
            (int) config('services.estrella.backoff_ms'),
        ));
    }

    public function boot(): void {}
}
