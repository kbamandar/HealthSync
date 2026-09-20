<?php

namespace Tests\Feature\Auth;

use App\Mail\OtpCodeMail;
use App\Models\ConsentRecord;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OtpAuthTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'test@example.com';

    private const MOBILE = '+919999999999';

    private function sendOtpAndCaptureCode(): string
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

        return $code;
    }

    public function test_send_otp_emails_a_six_digit_code(): void
    {
        $code = $this->sendOtpAndCaptureCode();

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertDatabaseCount('otp_codes', 1);
    }

    public function test_verify_with_wrong_code_is_rejected(): void
    {
        $this->sendOtpAndCaptureCode();

        $this->postJson('/api/v1/auth/otp/verify', [
            'email' => self::EMAIL,
            'mobile' => self::MOBILE,
            'otp' => '000000',
            'consent' => ['privacy_policy_version' => '1.0', 'terms_of_service_version' => '1.0'],
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'OTP_INVALID');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_verify_registers_a_new_user_with_consent_and_issues_tokens(): void
    {
        $code = $this->sendOtpAndCaptureCode();

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'email' => self::EMAIL,
            'mobile' => self::MOBILE,
            'otp' => $code,
            'consent' => ['privacy_policy_version' => '1.0', 'terms_of_service_version' => '1.0'],
        ])->assertOk();

        $response->assertJsonPath('data.user.email', self::EMAIL);
        $response->assertJsonPath('data.user.profile_complete', false);
        $this->assertNotEmpty($response->json('data.access_token'));
        $this->assertNotEmpty($response->json('data.refresh_token'));

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('refresh_tokens', 1);
        $this->assertSame(2, ConsentRecord::count());
    }

    public function test_verify_without_consent_rejects_new_registration(): void
    {
        $code = $this->sendOtpAndCaptureCode();

        $this->postJson('/api/v1/auth/otp/verify', [
            'email' => self::EMAIL,
            'mobile' => self::MOBILE,
            'otp' => $code,
        ])->assertStatus(422);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_verify_for_an_existing_user_does_not_require_consent_again(): void
    {
        User::factory()->create(['email' => self::EMAIL, 'mobile' => self::MOBILE]);

        $code = $this->sendOtpAndCaptureCode();

        $this->postJson('/api/v1/auth/otp/verify', [
            'email' => self::EMAIL,
            'mobile' => self::MOBILE,
            'otp' => $code,
        ])->assertOk();

        $this->assertSame(0, ConsentRecord::count());
    }

    public function test_a_consumed_code_cannot_be_reused(): void
    {
        $code = $this->sendOtpAndCaptureCode();

        $this->postJson('/api/v1/auth/otp/verify', [
            'email' => self::EMAIL,
            'mobile' => self::MOBILE,
            'otp' => $code,
            'consent' => ['privacy_policy_version' => '1.0', 'terms_of_service_version' => '1.0'],
        ])->assertOk();

        $this->postJson('/api/v1/auth/otp/verify', [
            'email' => self::EMAIL,
            'mobile' => self::MOBILE,
            'otp' => $code,
        ])->assertStatus(422)->assertJsonPath('error.code', 'OTP_INVALID');
    }

    public function test_send_otp_is_rate_limited_after_five_attempts(): void
    {
        Mail::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/otp/send', [
                'email' => self::EMAIL,
                'mobile' => self::MOBILE,
            ])->assertOk();
        }

        $this->postJson('/api/v1/auth/otp/send', [
            'email' => self::EMAIL,
            'mobile' => self::MOBILE,
        ])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'OTP_RATE_LIMITED');
    }

    public function test_refresh_rotates_the_token_and_invalidates_the_old_one(): void
    {
        $code = $this->sendOtpAndCaptureCode();

        $tokens = $this->postJson('/api/v1/auth/otp/verify', [
            'email' => self::EMAIL,
            'mobile' => self::MOBILE,
            'otp' => $code,
            'consent' => ['privacy_policy_version' => '1.0', 'terms_of_service_version' => '1.0'],
        ])->json('data');

        $refreshed = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $tokens['refresh_token'],
        ])->assertOk()->json('data');

        $this->assertNotSame($tokens['access_token'], $refreshed['access_token']);
        $this->assertNotSame($tokens['refresh_token'], $refreshed['refresh_token']);

        // Reusing the rotated-out token must fail.
        $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $tokens['refresh_token'],
        ])->assertStatus(401)->assertJsonPath('error.code', 'REFRESH_TOKEN_INVALID');

        // The freshly issued one must still work.
        $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $refreshed['refresh_token'],
        ])->assertOk();
    }

    public function test_logout_revokes_the_refresh_token(): void
    {
        $code = $this->sendOtpAndCaptureCode();

        $tokens = $this->postJson('/api/v1/auth/otp/verify', [
            'email' => self::EMAIL,
            'mobile' => self::MOBILE,
            'otp' => $code,
            'consent' => ['privacy_policy_version' => '1.0', 'terms_of_service_version' => '1.0'],
        ])->json('data');

        $this->postJson('/api/v1/auth/logout', [
            'refresh_token' => $tokens['refresh_token'],
        ])->assertOk();

        $this->assertNotNull(RefreshToken::first()->revoked_at);

        $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $tokens['refresh_token'],
        ])->assertStatus(401);
    }
}
