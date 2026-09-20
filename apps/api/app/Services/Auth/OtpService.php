<?php

namespace App\Services\Auth;

use App\Mail\OtpCodeMail;
use App\Models\OtpCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class OtpService
{
    private const CODE_TTL_MINUTES = 5;

    private const MAX_ATTEMPTS = 5;

    public function send(string $email, string $mobile): void
    {
        $code = (string) random_int(100000, 999999);

        OtpCode::create([
            'email' => $email,
            'mobile' => $mobile,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
        ]);

        Mail::to($email)->send(new OtpCodeMail($code, self::CODE_TTL_MINUTES));
    }

    /**
     * @return string one of: ok, invalid, expired, too_many_attempts
     */
    public function verify(string $email, string $mobile, string $code): string
    {
        $otp = OtpCode::query()
            ->where('email', $email)
            ->where('mobile', $mobile)
            ->whereNull('consumed_at')
            ->latest('created_at')
            ->first();

        if (! $otp) {
            return 'invalid';
        }

        if ($otp->attempts >= self::MAX_ATTEMPTS) {
            return 'too_many_attempts';
        }

        if ($otp->expires_at->isPast()) {
            return 'expired';
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');

            return 'invalid';
        }

        $otp->update(['consumed_at' => now()]);

        return 'ok';
    }
}
