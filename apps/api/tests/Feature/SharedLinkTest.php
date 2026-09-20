<?php

namespace Tests\Feature;

use App\Models\FamilyMember;
use App\Models\SharedLink;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharedLinkTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(User $user): array
    {
        $token = app(TokenService::class)->issueAccessToken($user);

        return ['Authorization' => "Bearer {$token}"];
    }

    private function selfMemberId(User $user): string
    {
        $this->getJson('/api/v1/family/members', $this->authHeaders($user));

        return FamilyMember::where('relationship', 'self')->firstOrFail()->id;
    }

    public function test_share_record_creates_a_link_with_qr_code(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $recordId = $this->postJson('/api/v1/records', [
            'member_id' => $memberId,
            'category' => 'lab_report',
            'title' => 'CBC + Lipid Profile',
        ], $this->authHeaders($user))->json('data.id');

        $response = $this->postJson('/api/v1/share/record', [
            'record_id' => $recordId,
        ], $this->authHeaders($user))->assertOk();

        $response->assertJsonPath('data.link_type', 'record');
        $response->assertJsonPath('data.label', 'CBC + Lipid Profile');
        $this->assertStringContainsString('<svg', $response->json('data.qr_code_svg'));
        $this->assertNotEmpty($response->json('data.url'));
    }

    public function test_share_record_rejects_a_record_from_another_family_group(): void
    {
        $owner = User::factory()->create(['name' => 'Mandar Owner']);
        $stranger = User::factory()->create(['name' => 'Someone Else']);

        $recordId = $this->postJson('/api/v1/records', [
            'member_id' => $this->selfMemberId($stranger),
            'category' => 'lab_report',
        ], $this->authHeaders($stranger))->json('data.id');

        $this->postJson('/api/v1/share/record', ['record_id' => $recordId], $this->authHeaders($owner))
            ->assertStatus(422);
    }

    public function test_share_summary_creates_a_link(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $response = $this->postJson('/api/v1/share/summary', [
            'member_id' => $memberId,
        ], $this->authHeaders($user))->assertOk();

        $response->assertJsonPath('data.link_type', 'health_summary');
        $response->assertJsonPath('data.label', 'Health summary — Mandar Owner');
    }

    public function test_public_access_returns_record_json_and_increments_access_count(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $recordId = $this->postJson('/api/v1/records', [
            'member_id' => $memberId,
            'category' => 'lab_report',
            'title' => 'CBC + Lipid Profile',
        ], $this->authHeaders($user))->json('data.id');

        $shareResponse = $this->postJson('/api/v1/share/record', ['record_id' => $recordId], $this->authHeaders($user));
        $url = $shareResponse->json('data.url');
        $path = parse_url($url, PHP_URL_PATH);

        $response = $this->getJson($path)->assertOk();
        $response->assertJsonPath('data.title', 'CBC + Lipid Profile');

        $link = SharedLink::where('record_id', $recordId)->firstOrFail();
        $this->assertSame(1, $link->access_count);
    }

    public function test_public_access_returns_a_pdf_for_a_health_summary_link(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $shareResponse = $this->postJson('/api/v1/share/summary', ['member_id' => $memberId], $this->authHeaders($user));
        $path = parse_url($shareResponse->json('data.url'), PHP_URL_PATH);

        $response = $this->get($path)->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_public_access_rejects_an_expired_link(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $recordId = $this->postJson('/api/v1/records', [
            'member_id' => $memberId,
            'category' => 'lab_report',
        ], $this->authHeaders($user))->json('data.id');

        $this->postJson('/api/v1/share/record', ['record_id' => $recordId], $this->authHeaders($user));
        $link = SharedLink::where('record_id', $recordId)->firstOrFail();
        $link->update(['expires_at' => now()->subDay()]);

        $this->getJson("/api/public/share/{$link->token}")
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'LINK_INVALID');
    }

    public function test_public_access_rejects_a_revoked_link(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $recordId = $this->postJson('/api/v1/records', [
            'member_id' => $memberId,
            'category' => 'lab_report',
        ], $this->authHeaders($user))->json('data.id');

        $linkId = $this->postJson('/api/v1/share/record', ['record_id' => $recordId], $this->authHeaders($user))
            ->json('data.id');

        $this->deleteJson("/api/v1/share/{$linkId}", [], $this->authHeaders($user))->assertOk();

        $link = SharedLink::findOrFail($linkId);
        $this->getJson("/api/public/share/{$link->token}")->assertStatus(404);
    }

    public function test_public_access_respects_max_access_limit(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $recordId = $this->postJson('/api/v1/records', [
            'member_id' => $memberId,
            'category' => 'lab_report',
        ], $this->authHeaders($user))->json('data.id');

        $this->postJson('/api/v1/share/record', [
            'record_id' => $recordId,
            'max_access' => 1,
        ], $this->authHeaders($user));

        $link = SharedLink::where('record_id', $recordId)->firstOrFail();

        $this->getJson("/api/public/share/{$link->token}")->assertOk();
        $this->getJson("/api/public/share/{$link->token}")->assertStatus(404);
    }

    public function test_index_lists_only_the_callers_own_links(): void
    {
        $owner = User::factory()->create(['name' => 'Mandar Owner']);
        $stranger = User::factory()->create(['name' => 'Someone Else']);

        $recordId = $this->postJson('/api/v1/records', [
            'member_id' => $this->selfMemberId($owner),
            'category' => 'lab_report',
            'title' => 'Owner record',
        ], $this->authHeaders($owner))->json('data.id');
        $this->postJson('/api/v1/share/record', ['record_id' => $recordId], $this->authHeaders($owner));

        $this->postJson('/api/v1/share/summary', [
            'member_id' => $this->selfMemberId($stranger),
        ], $this->authHeaders($stranger));

        $response = $this->getJson('/api/v1/share', $this->authHeaders($owner))->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.label', 'Owner record');
        $this->assertArrayNotHasKey('qr_code_svg', $response->json('data.0'));
    }

    public function test_destroy_revokes_a_link_owned_by_another_user_is_rejected(): void
    {
        $owner = User::factory()->create(['name' => 'Mandar Owner']);
        $intruder = User::factory()->create(['name' => 'Random Person']);

        $recordId = $this->postJson('/api/v1/records', [
            'member_id' => $this->selfMemberId($owner),
            'category' => 'lab_report',
        ], $this->authHeaders($owner))->json('data.id');
        $linkId = $this->postJson('/api/v1/share/record', ['record_id' => $recordId], $this->authHeaders($owner))
            ->json('data.id');

        $this->deleteJson("/api/v1/share/{$linkId}", [], $this->authHeaders($intruder))->assertStatus(404);
        $this->assertFalse(SharedLink::findOrFail($linkId)->is_revoked);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/share')->assertStatus(401);
        $this->postJson('/api/v1/share/record', [])->assertStatus(401);
    }
}
