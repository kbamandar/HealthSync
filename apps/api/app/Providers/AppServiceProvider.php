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

        // Builds a full ZIP of every record/vital/reminder/doctor — expensive
        // disk + I/O per hit, far below the blanket 120/min API budget.
        RateLimiter::for('data-export', function (Request $request) {
            return Limit::perDay(5)->by($request->user()?->id ?? $request->ip());
        });

        // Spawns a tesseract/pdftotext subprocess per call (30s timeout each).
        RateLimiter::for('ocr', function (Request $request) {
            return Limit::perHour(30)->by($request->user()?->id ?? $request->ip());
        });

        // Unauthenticated: generates a dompdf PDF on every hit with zero
        // cost barrier to the caller, so this is keyed by IP, not a user.
        RateLimiter::for('public-share', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
