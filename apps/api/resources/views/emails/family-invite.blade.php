<!DOCTYPE html>
<html>
<body style="font-family: sans-serif; padding: 24px;">
    <h2>{{ $inviterName }} invited you to their HealthSync family</h2>
    <p>They've added you as a "{{ $relationship }}" to their family health record vault.</p>
    <p>
        <a href="{{ $acceptUrl }}" style="display:inline-block;padding:12px 20px;background:#0f766e;color:#fff;text-decoration:none;border-radius:6px;">
            Accept invite
        </a>
    </p>
    <p style="font-size: 13px; color: #666;">
        Not expecting this? <a href="{{ $declineUrl }}">Decline the invite</a> instead.
    </p>
</body>
</html>
