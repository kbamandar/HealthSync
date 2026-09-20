<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
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

        Auth::setUser($user);

        return $next($request);
    }
}
