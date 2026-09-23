<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * The 'api' throttle middleware itself is disabled in the testing
 * environment (see bootstrap/app.php) so the feature suite isn't flaky —
 * this exercises the RateLimiter::for('api', ...) callback registered in
 * AppServiceProvider::boot() directly instead.
 */
class ApiRateLimiterTest extends TestCase
{
    public function test_authenticated_requests_are_keyed_by_user_id(): void
    {
        $user = new User(['id' => 'user-123']);
        $user->id = 'user-123';

        $request = Request::create('/api/v1/dashboard');
        $request->setUserResolver(fn () => $user);

        $limits = RateLimiter::limiter('api')($request);
        $limit = is_array($limits) ? $limits[0] : $limits;

        $this->assertInstanceOf(Limit::class, $limit);
        $this->assertSame('user-123', $limit->key);
        $this->assertSame(120, $limit->maxAttempts);
    }

    public function test_unauthenticated_requests_fall_back_to_ip(): void
    {
        $request = Request::create('/api/v1/auth/otp/send');
        $request->server->set('REMOTE_ADDR', '203.0.113.5');

        $limits = RateLimiter::limiter('api')($request);
        $limit = is_array($limits) ? $limits[0] : $limits;

        $this->assertSame('203.0.113.5', $limit->key);
    }

    /**
     * file-uploads is applied per-route (not disabled in testing), but 60/hr
     * is too many requests to fire in a fast test — this checks the
     * registered definition instead of exhausting the real limit.
     */
    public function test_file_uploads_limiter_allows_a_generous_hourly_batch(): void
    {
        $user = new User(['id' => 'user-123']);
        $user->id = 'user-123';

        $request = Request::create('/api/v1/records/some-id/files');
        $request->setUserResolver(fn () => $user);

        $limits = RateLimiter::limiter('file-uploads')($request);
        $limit = is_array($limits) ? $limits[0] : $limits;

        $this->assertSame('user-123', $limit->key);
        $this->assertSame(60, $limit->maxAttempts);
        $this->assertSame(3600, $limit->decaySeconds);
    }
}
