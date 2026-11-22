<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mobile Certificate Pins
    |--------------------------------------------------------------------------
    |
    | Comma-separated SHA-256 SPKI pins (base64), served at the public
    | /.well-known/security-cert.json endpoint for mobile certificate
    | pinning. Blank until a real production certificate exists — TLS
    | terminates at the load balancer, so there is nothing to pin in local
    | development.
    |
    */

    'cert_pins_sha256' => array_filter(array_map(
        'trim',
        explode(',', (string) env('CERT_PIN_SHA256', '')),
    )),

];
