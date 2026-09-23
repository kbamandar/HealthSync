<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use App\Models\RefreshToken;
use App\Models\User;
use App\Services\Auth\TokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWithJwt
{
    public function __construct(private readonly TokenService $tokens) {}

    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization', '');

        if (! str_starts_with($header, 'Bearer ')) {
            return ApiResponse::error('UNAUTHENTICATED', 'Missing or invalid Authorization header.', status: 401);
        }

        $token = substr($header, 7);
        $payload = $this->tokens->decodeAccessToken($token);

        if (! $payload || ! isset($payload->sub)) {
            return ApiResponse::error('UNAUTHENTICATED', 'Invalid or expired access token.', status: 401);
        }

        $user = User::find($payload->sub);

        if (! $user || ! $user->is_active) {
            return ApiResponse::error('UNAUTHENTICATED', 'Invalid or expired access token.', status: 401);
        }

        // A short-lived (15 min default) access token is otherwise pure
        // bearer-stateless — logging out or revoking a device from Settings
        // only marked the refresh token revoked, leaving any access token
        // already in an attacker's hands (e.g. a stolen phone) working for
        // up to its full remaining TTL. Checking the session it was issued
        // alongside is still live closes that gap at the cost of one indexed
        // lookup per request. Tokens with no `sid` claim (only ever produced
        // by TokenService::issueAccessToken() called directly, i.e. test
        // helpers — every real login issues one via issueTokenPair) skip
        // this check rather than being rejected outright.
        if (isset($payload->sid)) {
            $session = RefreshToken::find($payload->sid);

            if (! $session || ! $session->isActive()) {
                return ApiResponse::error('UNAUTHENTICATED', 'This session has been signed out.', status: 401);
            }
        }

        Auth::setUser($user);
        $request->attributes->set('session_id', $payload->sid ?? null);

        return $next($request);
    }
}
