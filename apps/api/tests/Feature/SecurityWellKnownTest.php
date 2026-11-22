<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityWellKnownTest extends TestCase
{
    public function test_it_returns_the_configured_pins(): void
    {
        config(['security.cert_pins_sha256' => ['abc123=', 'def456=']]);

        $response = $this->getJson('/.well-known/security-cert.json')->assertOk();

        $response->assertJsonPath('pins-sha256', ['abc123=', 'def456=']);
        $response->assertJsonPath('include-subdomains', true);
        $this->assertNotEmpty($response->json('expires'));
    }

    public function test_it_defaults_to_an_empty_pin_list(): void
    {
        config(['security.cert_pins_sha256' => []]);

        $this->getJson('/.well-known/security-cert.json')
            ->assertOk()
            ->assertJsonPath('pins-sha256', []);
    }
}
