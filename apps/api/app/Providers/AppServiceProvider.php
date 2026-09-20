<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
        // Authenticated requests are throttled per-user (so one abusive
        // family member can't lock out the rest of the household); anyone
        // without a valid access token yet (OTP send/verify, refresh) is
        // throttled per-IP.
        RateLimiter::for('api', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();

            return Limit::perMinute(120)->by($key);
        });
    }
}
