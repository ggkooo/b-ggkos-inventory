<?php

namespace App\Providers;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('backend-auth', function (Request $request): Limit {
            $clientKey = $request->header('X-API-KEY');

            if (! is_string($clientKey) || $clientKey === '') {
                $clientKey = $request->ip();
            }

            return Limit::perMinute((int) config('backends.auth_rate_limit_per_minute', 60))
                ->by('auth:'.$clientKey);
        });

        RateLimiter::for('backend-write', function (Request $request): Limit {
            $clientKey = $request->header('X-API-KEY');

            if (! is_string($clientKey) || $clientKey === '') {
                $clientKey = $request->ip();
            }

            return Limit::perMinute((int) config('backends.write_rate_limit_per_minute', 120))
                ->by('write:'.$clientKey);
        });

        if ($this->app->runningInConsole()) {
            Event::listen(CommandFinished::class, function (CommandFinished $event): void {
                if ($event->command !== 'migrate:fresh' || $event->exitCode !== 0) {
                    return;
                }

                Artisan::call('db:seed', [
                    '--class' => DatabaseSeeder::class,
                    '--force' => true,
                    '--no-interaction' => true,
                ]);
            });
        }
    }
}
