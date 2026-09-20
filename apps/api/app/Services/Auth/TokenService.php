<?php

namespace App\Services\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TokenService
{
    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int}
     */
    public function issueTokenPair(User $user, ?Request $request = null): array
    {
        return [
            'access_token' => $this->issueAccessToken($user),
            'refresh_token' => $this->issueRefreshToken($user, $request),
            'expires_in' => config('jwt.access_ttl') * 60,
        ];
    }

    public function issueAccessToken(User $user): string
    {
        $now = now();

        $payload = [
            'sub' => $user->id,
            'jti' => Str::uuid()->toString(),
            'iat' => $now->timestamp,
            'exp' => $now->copy()->addMinutes(config('jwt.access_ttl'))->timestamp,
        ];

        return JWT::encode($payload, config('jwt.secret'), config('jwt.algo'));
    }

    /**
     * Returns the plaintext refresh token — only the hash is persisted.
     */
    public function issueRefreshToken(User $user, ?Request $request = null): string
    {
        $plaintext = Str::random(64);

        RefreshToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plaintext),
            'expires_at' => now()->addDays(config('jwt.refresh_ttl')),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);

        return $plaintext;
    }

    public function decodeAccessToken(string $token): ?object
    {
        try {
            return JWT::decode($token, new Key(config('jwt.secret'), config('jwt.algo')));
        } catch (\Throwable) {
            return null;
        }
    }

    public function findActiveRefreshToken(string $plaintext): ?RefreshToken
    {
        $token = RefreshToken::query()
            ->where('token_hash', hash('sha256', $plaintext))
            ->first();

        return $token && $token->isActive() ? $token : null;
    }

    /**
     * Rotation: revoke the presented token and issue a fresh pair.
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int}
     */
    public function rotate(RefreshToken $refreshToken, ?Request $request = null): array
    {
        $user = $refreshToken->user;
        $newRefreshToken = $this->issueRefreshToken($user, $request);

        $replacement = RefreshToken::query()
            ->where('token_hash', hash('sha256', $newRefreshToken))
            ->first();

        $refreshToken->update([
            'revoked_at' => now(),
            'replaced_by_id' => $replacement->id,
        ]);

        return [
            'access_token' => $this->issueAccessToken($user),
            'refresh_token' => $newRefreshToken,
            'expires_in' => config('jwt.access_ttl') * 60,
        ];
    }

    public function revoke(RefreshToken $refreshToken): void
    {
        $refreshToken->update(['revoked_at' => now()]);
    }
}
