<?php

namespace App\Http\Controllers;

class SecurityWellKnownController extends Controller
{
    public function certificate()
    {
        return response()->json([
            'pins-sha256' => config('security.cert_pins_sha256'),
            'include-subdomains' => true,
            'expires' => now()->addDays(60)->toIso8601String(),
        ]);
    }
}
