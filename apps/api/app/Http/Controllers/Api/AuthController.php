<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\AuditEvent;
use App\Models\ConsentRecord;
use App\Models\User;
use App\Services\Auth\OtpService;
use App\Services\Auth\TokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly TokenService $tokens,
    ) {}

    public function sendOtp(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'mobile' => ['required', 'string', 'max:15'],
        ]);

        $key = 'otp-send:'.$data['mobile'];

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return ApiResponse::error(
                'OTP_RATE_LIMITED',
                "Too many OTP requests. Try again in {$seconds} seconds.",
                status: 429,
            );
        }

        RateLimiter::hit($key, 600);

        $this->otp->send($data['email'], $data['mobile']);

        return ApiResponse::success(['expires_in' => 300]);
    }

    public function verifyOtp(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'mobile' => ['required', 'string', 'max:15'],
            'otp' => ['required', 'string', 'size:6'],
            'consent' => ['sometimes', 'array'],
            'consent.privacy_policy_version' => ['required_with:consent'],
            'consent.terms_of_service_version' => ['required_with:consent'],
        ]);

        $result = $this->otp->verify($data['email'], $data['mobile'], $data['otp']);

        if ($result !== 'ok') {
            $errors = [
                'invalid' => ['OTP_INVALID', 'The code you entered is incorrect.'],
                'expired' => ['OTP_EXPIRED', 'This code has expired. Request a new one.'],
                'too_many_attempts' => ['OTP_TOO_MANY_ATTEMPTS', 'Too many incorrect attempts. Request a new code.'],
            ];
            [$code, $message] = $errors[$result];

            return ApiResponse::error($code, $message, status: 422);
        }

        $user = User::where('email', $data['email'])->first();
        $isNewUser = $user === null;

        if ($isNewUser) {
            if (! $request->filled('consent')) {
                throw ValidationException::withMessages([
                    'consent' => 'Consent to the Privacy Policy and Terms of Service is required to register.',
                ]);
            }

            $user = User::create([
                'email' => $data['email'],
                'mobile' => $data['mobile'],
                'is_active' => true,
            ]);

            $now = now();
            foreach (['privacy_policy', 'terms_of_service'] as $type) {
                ConsentRecord::create([
                    'user_id' => $user->id,
                    'consent_type' => $type,
                    'version' => $data['consent'][$type.'_version'],
                    'consented_at' => $now,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }
        }

        AuditEvent::record('login.otp', $user->id, $request, ['is_new_user' => $isNewUser]);

        $tokens = $this->tokens->issueTokenPair($user, $request);

        return ApiResponse::success([
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'name' => $user->name,
                'profile_complete' => $user->name !== null,
            ],
            ...$tokens,
        ]);
    }

    public function refresh(Request $request)
    {
        $data = $request->validate([
            'refresh_token' => ['required', 'string'],
        ]);

        $refreshToken = $this->tokens->findActiveRefreshToken($data['refresh_token']);

        if (! $refreshToken) {
            return ApiResponse::error('REFRESH_TOKEN_INVALID', 'This refresh token is invalid, expired, or already used.', status: 401);
        }

        $newTokens = $this->tokens->rotate($refreshToken, $request);

        return ApiResponse::success($newTokens);
    }

    public function logout(Request $request)
    {
        $data = $request->validate([
            'refresh_token' => ['required', 'string'],
        ]);

        $refreshToken = $this->tokens->findActiveRefreshToken($data['refresh_token']);

        if ($refreshToken) {
            $this->tokens->revoke($refreshToken);
        }

        return ApiResponse::success();
    }
}
