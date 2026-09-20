<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/users/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_garbage_bearer_token_is_rejected(): void
    {
        $this->getJson('/api/v1/users/me', ['Authorization' => 'Bearer not-a-real-token'])
            ->assertStatus(401);
    }

    public function test_authenticated_user_can_fetch_own_profile(): void
    {
        $user = User::factory()->create(['name' => null]);
        $token = app(TokenService::class)->issueAccessToken($user);

        $this->getJson('/api/v1/users/me', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.profile_complete', false);
    }

    public function test_authenticated_user_can_update_profile(): void
    {
        $user = User::factory()->create(['name' => null]);
        $token = app(TokenService::class)->issueAccessToken($user);

        $this->putJson('/api/v1/users/me', [
            'name' => 'Mandar Test',
            'gender' => 'male',
            'blood_group' => 'O+',
        ], ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('data.name', 'Mandar Test')
            ->assertJsonPath('data.profile_complete', true);

        $this->assertSame('Mandar Test', $user->fresh()->name);
    }

    public function test_photo_upload_returns_a_presigned_url_for_a_valid_mime_type(): void
    {
        $user = User::factory()->create();
        $token = app(TokenService::class)->issueAccessToken($user);

        $response = $this->postJson('/api/v1/users/me/photo', [
            'mime_type' => 'image/jpeg',
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertOk();
        $this->assertStringEndsWith('.jpg', $response->json('data.s3_key'));
        $this->assertNotEmpty($response->json('data.upload_url'));
    }

    public function test_photo_upload_rejects_unsupported_mime_type(): void
    {
        $user = User::factory()->create();
        $token = app(TokenService::class)->issueAccessToken($user);

        $this->postJson('/api/v1/users/me/photo', [
            'mime_type' => 'application/pdf',
        ], ['Authorization' => "Bearer {$token}"])->assertStatus(422);
    }
}
