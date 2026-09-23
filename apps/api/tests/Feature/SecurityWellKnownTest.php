<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityWellKnownTest extends TestCase
{
    public function test_certificate_pin_endpoint_is_public_and_returns_pins(): void
    {
        config(['security.cert_pins_sha256' => ['AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=']]);

        $response = $this->getJson('/.well-known/security-cert.json')->assertOk();

        $response->assertJsonPath('pins-sha256.0', 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
        $response->assertJsonPath('include-subdomains', true);
        $this->assertNotNull($response->json('expires'));
    }

    public function test_certificate_pin_endpoint_defaults_to_an_empty_pin_list(): void
    {
        config(['security.cert_pins_sha256' => []]);

        $this->getJson('/.well-known/security-cert.json')
            ->assertOk()
            ->assertJsonPath('pins-sha256', []);
    }
}
