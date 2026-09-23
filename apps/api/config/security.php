<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Certificate pinning
    |--------------------------------------------------------------------------
    |
    | SHA-256 hashes (base64, SPKI pin format) of the certificates the mobile
    | client should pin against, served at GET /.well-known/security-cert.json
    | so a build can fetch and update its pin set without an app release.
    | Real TLS termination happens at the load balancer (see infra/terraform)
    | — this sandbox has no production certificate to hash, so these are
    | blank/placeholder until a real one exists. Always configure at least
    | two pins (current + backup) so rotating the certificate never locks
    | out pinned clients.
    |
    */
    'cert_pins_sha256' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CERT_PIN_SHA256', '')),
    ))),

];
