<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\AuditEvent;
use App\Models\RefreshToken;
use Illuminate\Http\Request;

/**
 * "Device trust management" (Sprint 7). A session is a live refresh token —
 * there's no separate sessions table, since a refresh token already tracks
 * everything a device row would (ip, user agent, issued/expiry, revocation).
 */
class SessionController extends Controller
{
    public function index(Request $request)
    {
        $currentSessionId = $request->attributes->get('session_id');

        $sessions = RefreshToken::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get();

        return ApiResponse::success($sessions->map(fn (RefreshToken $t) => [
            'id' => $t->id,
            'device_name' => $t->device_name,
            'platform' => $t->platform,
            'ip_address' => $t->ip_address,
            'is_current' => $currentSessionId !== null && $t->id === $currentSessionId,
            'created_at' => $t->created_at->toIso8601String(),
            'expires_at' => $t->expires_at->toIso8601String(),
        ])->all());
    }

    public function destroy(Request $request, string $id)
    {
        $session = RefreshToken::query()
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->whereNull('revoked_at')
            ->first();

        if (! $session) {
            return ApiResponse::error('NOT_FOUND', 'Session not found.', status: 404);
        }

        $session->update(['revoked_at' => now()]);

        AuditEvent::record('session.revoke', $request->user()->id, $request, ['refresh_token_id' => $session->id]);

        return ApiResponse::success();
    }
}
