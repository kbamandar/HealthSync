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

        // Building a data export touches every table in the family group,
        // zips them, and queues an email — the blanket per-minute API limit
        // doesn't stop a user from sustaining that load continuously.
        RateLimiter::for('data-export', function (Request $request) {
            return Limit::perDay(5)->by($request->user()?->id ?? $request->ip());
        });

        // Each registered file dispatches an OCR job; bounded generously
        // above normal usage so it still allows uploading a real batch of
        // records in one sitting.
        RateLimiter::for('file-uploads', function (Request $request) {
            return Limit::perHour(60)->by($request->user()?->id ?? $request->ip());
        });
    }
}
