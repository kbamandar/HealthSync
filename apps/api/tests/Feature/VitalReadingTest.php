<?php

namespace Tests\Feature;

use App\Models\FamilyMember;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VitalReadingTest extends TestCase
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

    public function test_store_logs_a_normal_reading(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $response = $this->postJson('/api/v1/vitals', [
            'member_id' => $memberId,
            'vital_type' => 'heart_rate',
            'value' => 72,
            'reading_context' => 'resting',
        ], $this->authHeaders($user))->assertOk();

        $response->assertJsonPath('data.vital_type', 'heart_rate');
        $response->assertJsonPath('data.unit', 'bpm');
        $response->assertJsonPath('data.is_abnormal', false);
        $response->assertJsonPath('data.zone', 'normal');
    }

    public function test_store_flags_an_abnormal_reading(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $response = $this->postJson('/api/v1/vitals', [
            'member_id' => $memberId,
            'vital_type' => 'bp_systolic',
            'value' => 165,
        ], $this->authHeaders($user))->assertOk();

        $response->assertJsonPath('data.is_abnormal', true);
        $response->assertJsonPath('data.zone', 'abnormal');
    }

    public function test_store_flags_a_borderline_reading_without_marking_it_abnormal(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $response = $this->postJson('/api/v1/vitals', [
            'member_id' => $memberId,
            'vital_type' => 'bp_systolic',
            'value' => 128,
        ], $this->authHeaders($user))->assertOk();

        $response->assertJsonPath('data.is_abnormal', false);
        $response->assertJsonPath('data.zone', 'borderline');
    }

    public function test_weight_is_never_flagged_abnormal(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $response = $this->postJson('/api/v1/vitals', [
            'member_id' => $memberId,
            'vital_type' => 'weight',
            'value' => 250,
        ], $this->authHeaders($user))->assertOk();

        $response->assertJsonPath('data.is_abnormal', false);
        $response->assertJsonPath('data.zone', 'normal');
    }

    public function test_store_rejects_a_member_from_another_family_group(): void
    {
        $owner = User::factory()->create(['name' => 'Mandar Owner']);
        $stranger = User::factory()->create(['name' => 'Someone Else']);
        $strangerMemberId = $this->selfMemberId($stranger);

        $this->postJson('/api/v1/vitals', [
            'member_id' => $strangerMemberId,
            'vital_type' => 'heart_rate',
            'value' => 72,
        ], $this->authHeaders($owner))->assertStatus(422);
    }

    public function test_index_filters_by_vital_type_and_scopes_to_caller_group(): void
    {
        $owner = User::factory()->create(['name' => 'Mandar Owner']);
        $stranger = User::factory()->create(['name' => 'Someone Else']);

        $this->postJson('/api/v1/vitals', [
            'member_id' => $this->selfMemberId($owner),
            'vital_type' => 'heart_rate',
            'value' => 70,
        ], $this->authHeaders($owner));
        $this->postJson('/api/v1/vitals', [
            'member_id' => $this->selfMemberId($owner),
            'vital_type' => 'weight',
            'value' => 70,
        ], $this->authHeaders($owner));
        $this->postJson('/api/v1/vitals', [
            'member_id' => $this->selfMemberId($stranger),
            'vital_type' => 'heart_rate',
            'value' => 70,
        ], $this->authHeaders($stranger));

        $response = $this->getJson('/api/v1/vitals?vital_type=heart_rate', $this->authHeaders($owner))->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.vital_type', 'heart_rate');
    }

    public function test_destroy_removes_a_reading(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $readingId = $this->postJson('/api/v1/vitals', [
            'member_id' => $memberId,
            'vital_type' => 'heart_rate',
            'value' => 72,
        ], $this->authHeaders($user))->json('data.id');

        $this->deleteJson("/api/v1/vitals/{$readingId}", [], $this->authHeaders($user))->assertOk();

        $this->getJson('/api/v1/vitals', $this->authHeaders($user))->assertJsonCount(0, 'data');
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/vitals')->assertStatus(401);
        $this->postJson('/api/v1/vitals', [])->assertStatus(401);
    }
}
