<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(User $user): array
    {
        $token = app(TokenService::class)->issueAccessToken($user);

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_registers_a_device_token(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);

        $this->postJson('/api/v1/devices', [
            'push_token' => 'expo-push-token-abc',
            'platform' => 'android',
        ], $this->authHeaders($user))->assertOk();

        $this->assertDatabaseHas('devices', [
            'user_id' => $user->id,
            'push_token' => 'expo-push-token-abc',
            'platform' => 'android',
        ]);
    }

    public function test_re_registering_the_same_token_updates_it_instead_of_duplicating(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);

        $this->postJson('/api/v1/devices', [
            'push_token' => 'same-token',
            'platform' => 'ios',
        ], $this->authHeaders($user));

        $this->postJson('/api/v1/devices', [
            'push_token' => 'same-token',
            'platform' => 'ios',
        ], $this->authHeaders($user));

        $this->assertSame(1, Device::where('push_token', 'same-token')->count());
    }

    public function test_rejects_an_unknown_platform(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);

        $this->postJson('/api/v1/devices', [
            'push_token' => 'token',
            'platform' => 'windows',
        ], $this->authHeaders($user))->assertStatus(422);
    }
}
