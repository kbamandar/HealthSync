<?php

namespace Tests\Feature\Auth;

use App\Mail\OtpCodeMail;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SessionManagementTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'test@example.com';

    private const MOBILE = '+919999999999';

    private function loginWithDevice(string $deviceName, string $platform): array
    {
        Mail::fake();

        $this->postJson('/api/v1/auth/otp/send', [
            'email' => self::EMAIL,
            'mobile' => self::MOBILE,
        ])->assertOk();

        $code = null;
        Mail::assertSent(OtpCodeMail::class, function (OtpCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'email' => self::EMAIL,
            'mobile' => self::MOBILE,
            'otp' => $code,
            'consent' => ['privacy_policy_version' => '1.0', 'terms_of_service_version' => '1.0'],
            'device_name' => $deviceName,
            'platform' => $platform,
        ])->assertOk();

        return $response->json('data');
    }

    public function test_sessions_lists_active_devices_and_flags_the_current_one(): void
    {
        $firstLogin = $this->loginWithDevice('iPhone 15 Pro', 'ios');

        // Second device, same user (OTP flow re-authenticates the same account).
        $secondLogin = $this->loginWithDevice('Pixel 8', 'android');

        $headers = ['Authorization' => 'Bearer '.$secondLogin['access_token']];

        $response = $this->getJson('/api/v1/sessions', $headers)->assertOk();

        $response->assertJsonCount(2, 'data');
        $devices = collect($response->json('data'));

        $current = $devices->firstWhere('is_current', true);
        $this->assertNotNull($current);
        $this->assertSame('Pixel 8', $current['device_name']);
        $this->assertSame('android', $current['platform']);

        $other = $devices->firstWhere('is_current', false);
        $this->assertSame('iPhone 15 Pro', $other['device_name']);

        // Sanity: the first login's own access token flags itself as current too.
        $firstHeaders = ['Authorization' => 'Bearer '.$firstLogin['access_token']];
        $firstResponse = $this->getJson('/api/v1/sessions', $firstHeaders)->assertOk();
        $this->assertSame('iPhone 15 Pro', collect($firstResponse->json('data'))->firstWhere('is_current', true)['device_name']);
    }

    public function test_revoking_a_session_removes_it_from_the_list_and_invalidates_its_refresh_token(): void
    {
        $firstLogin = $this->loginWithDevice('iPhone 15 Pro', 'ios');
        $secondLogin = $this->loginWithDevice('Pixel 8', 'android');

        $headers = ['Authorization' => 'Bearer '.$secondLogin['access_token']];

        $sessions = $this->getJson('/api/v1/sessions', $headers)->json('data');
        $iphoneSessionId = collect($sessions)->firstWhere('device_name', 'iPhone 15 Pro')['id'];

        $this->deleteJson("/api/v1/sessions/{$iphoneSessionId}", [], $headers)->assertOk();

        $remaining = $this->getJson('/api/v1/sessions', $headers)->json('data');
        $this->assertCount(1, $remaining);
        $this->assertSame('Pixel 8', $remaining[0]['device_name']);

        // The revoked device's own refresh token no longer works.
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $firstLogin['refresh_token']])
            ->assertStatus(401);
    }

    public function test_revoking_a_session_immediately_invalidates_its_still_unexpired_access_token(): void
    {
        $firstLogin = $this->loginWithDevice('iPhone 15 Pro', 'ios');
        $secondLogin = $this->loginWithDevice('Pixel 8', 'android');

        $secondHeaders = ['Authorization' => 'Bearer '.$secondLogin['access_token']];
        $firstHeaders = ['Authorization' => 'Bearer '.$firstLogin['access_token']];

        // Sanity: the iPhone's access token still works before revocation.
        $this->getJson('/api/v1/users/me', $firstHeaders)->assertOk();

        $sessions = $this->getJson('/api/v1/sessions', $secondHeaders)->json('data');
        $iphoneSessionId = collect($sessions)->firstWhere('device_name', 'iPhone 15 Pro')['id'];
        $this->deleteJson("/api/v1/sessions/{$iphoneSessionId}", [], $secondHeaders)->assertOk();

        // The access token itself hasn't expired, but its session has been
        // revoked — it must stop working immediately, not after its full TTL.
        $this->getJson('/api/v1/users/me', $firstHeaders)->assertStatus(401);

        // The other device's own session/token is unaffected.
        $this->getJson('/api/v1/users/me', $secondHeaders)->assertOk();
    }

    public function test_cannot_revoke_a_session_belonging_to_another_user(): void
    {
        $victim = $this->loginWithDevice('Victim Phone', 'ios');

        $intruder = User::factory()->create(['name' => 'Intruder']);
        $intruderToken = app(TokenService::class)->issueAccessToken($intruder);

        $victimSessions = $this->getJson('/api/v1/sessions', [
            'Authorization' => 'Bearer '.$victim['access_token'],
        ])->json('data');

        $this->deleteJson(
            "/api/v1/sessions/{$victimSessions[0]['id']}",
            [],
            ['Authorization' => "Bearer {$intruderToken}"],
        )->assertStatus(404);
    }

    public function test_security_headers_are_present_on_api_responses(): void
    {
        $this->getJson('/api/v1/sessions')
            ->assertHeader('Strict-Transport-Security')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
