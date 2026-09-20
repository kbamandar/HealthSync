<?php

namespace Tests\Feature;

use App\Models\FamilyMember;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTimelineTest extends TestCase
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

    public function test_search_finds_records_by_title_and_groups_by_category(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $this->postJson('/api/v1/records', [
            'member_id' => $memberId,
            'category' => 'lab_report',
            'title' => 'CBC + Lipid Profile',
            'notes' => 'Total cholesterol was 182',
        ], $this->authHeaders($user));

        $this->postJson('/api/v1/records', [
            'member_id' => $memberId,
            'category' => 'prescription',
            'title' => 'Antibiotic course',
        ], $this->authHeaders($user));

        $response = $this->getJson('/api/v1/search?q=cholesterol', $this->authHeaders($user))->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.category', 'lab_report');
        $response->assertJsonCount(1, 'data.0.records');
        $response->assertJsonPath('data.0.records.0.title', 'CBC + Lipid Profile');
    }

    public function test_search_only_returns_the_callers_family_records(): void
    {
        $owner = User::factory()->create(['name' => 'Mandar Owner']);
        $stranger = User::factory()->create(['name' => 'Someone Else']);

        $this->postJson('/api/v1/records', [
            'member_id' => $this->selfMemberId($stranger),
            'category' => 'lab_report',
            'title' => 'Diabetes panel',
        ], $this->authHeaders($stranger));

        $response = $this->getJson('/api/v1/search?q=diabetes', $this->authHeaders($owner))->assertOk();

        $response->assertJsonCount(0, 'data');
    }

    public function test_search_requires_a_query_of_at_least_two_characters(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);

        $this->getJson('/api/v1/search?q=a', $this->authHeaders($user))->assertStatus(422);
    }

    public function test_timeline_merges_records_and_vitals_sorted_by_date(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $this->postJson('/api/v1/records', [
            'member_id' => $memberId,
            'category' => 'lab_report',
            'title' => 'CBC',
            'record_date' => '2026-03-01',
        ], $this->authHeaders($user));

        $this->postJson('/api/v1/vitals', [
            'member_id' => $memberId,
            'vital_type' => 'heart_rate',
            'value' => 72,
            'recorded_at' => '2026-03-05T08:00:00Z',
        ], $this->authHeaders($user));

        $response = $this->getJson('/api/v1/timeline', $this->authHeaders($user))->assertOk();

        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.kind', 'vital');
        $response->assertJsonPath('data.0.sub_type', 'heart_rate');
        $response->assertJsonPath('data.0.member_name', 'Mandar Owner');
        $response->assertJsonPath('data.1.kind', 'record');
        $response->assertJsonPath('data.1.title', 'CBC');
        $response->assertJsonPath('meta.total', 2);
    }

    public function test_timeline_filters_by_member(): void
    {
        $owner = User::factory()->create(['name' => 'Mandar Owner']);
        $ownerMemberId = $this->selfMemberId($owner);

        $this->postJson('/api/v1/family/members', [
            'relationship' => 'child',
            'display_name' => 'Aarav',
            'is_guardian_managed' => true,
        ], $this->authHeaders($owner));
        $childId = FamilyMember::where('display_name', 'Aarav')->firstOrFail()->id;

        $this->postJson('/api/v1/records', [
            'member_id' => $ownerMemberId,
            'category' => 'lab_report',
            'title' => "Owner's record",
        ], $this->authHeaders($owner));

        $this->postJson('/api/v1/records', [
            'member_id' => $childId,
            'category' => 'vaccination',
            'title' => "Aarav's vaccine",
        ], $this->authHeaders($owner));

        $response = $this->getJson("/api/v1/timeline?member_id={$childId}", $this->authHeaders($owner))->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', "Aarav's vaccine");
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/search?q=test')->assertStatus(401);
        $this->getJson('/api/v1/timeline')->assertStatus(401);
    }
}
