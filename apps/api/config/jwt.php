<?php

return [
    // Dedicated signing secret — deliberately separate from APP_KEY, which
    // Laravel uses for encryption, not token signing.
    'secret' => env('JWT_SECRET'),

    'algo' => 'HS256',

    // Access token lifetime, minutes.
    'access_ttl' => (int) env('JWT_ACCESS_TTL', 15),

    // Refresh token lifetime, days.
    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 7),
];
