<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Certificate pinning (Sprint 8). Served outside the versioned /api/v1
 * prefix and without auth, matching the public, unauthenticated nature of
 * a real /.well-known/ endpoint — a client must be able to fetch this
 * before it trusts anything else the API says.
 */
class SecurityWellKnownController extends Controller
{
    public function certificate(): JsonResponse
    {
        return response()->json([
            'pins-sha256' => config('security.cert_pins_sha256'),
            'include-subdomains' => true,
            'expires' => now()->addDays(30)->toIso8601String(),
        ]);
    }
}
