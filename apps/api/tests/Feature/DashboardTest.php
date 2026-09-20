<?php

namespace Tests\Feature;

use App\Models\FamilyMember;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
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

    public function test_dashboard_aggregates_records_vitals_and_family_summary(): void
    {
        $user = User::factory()->create([
            'name' => 'Mandar Owner',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'blood_group' => 'O+',
        ]);
        $memberId = $this->selfMemberId($user);

        $this->postJson('/api/v1/records', [
            'member_id' => $memberId,
            'category' => 'lab_report',
            'title' => 'CBC',
        ], $this->authHeaders($user));

        $this->postJson('/api/v1/vitals', [
            'member_id' => $memberId,
            'vital_type' => 'bp_systolic',
            'value' => 165,
        ], $this->authHeaders($user));

        $response = $this->getJson('/api/v1/dashboard', $this->authHeaders($user))->assertOk();

        $response->assertJsonPath('data.stats.records_this_month', 1);
        $response->assertJsonPath('data.stats.vitals_needing_attention', 1);
        $response->assertJsonCount(1, 'data.recent_records');
        $response->assertJsonPath('data.recent_records.0.title', 'CBC');
        $response->assertJsonCount(1, 'data.latest_vitals');
        $response->assertJsonPath('data.latest_vitals.0.zone', 'abnormal');
        $response->assertJsonCount(1, 'data.family_summary');
        $response->assertJsonPath('data.family_summary.0.pending_count', 1);
    }

    public function test_dashboard_includes_upcoming_reminders_but_not_past_or_inactive_ones(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $this->postJson('/api/v1/reminders', [
            'member_id' => $memberId,
            'reminder_type' => 'medication',
            'title' => 'Metformin 500mg',
            'due_at' => now()->addHours(2)->toIso8601String(),
        ], $this->authHeaders($user));

        $this->postJson('/api/v1/reminders', [
            'member_id' => $memberId,
            'reminder_type' => 'appointment',
            'title' => 'Already past',
            'due_at' => now()->subDay()->toIso8601String(),
        ], $this->authHeaders($user));

        $response = $this->getJson('/api/v1/dashboard', $this->authHeaders($user))->assertOk();

        $response->assertJsonCount(1, 'data.upcoming_reminders');
        $response->assertJsonPath('data.upcoming_reminders.0.title', 'Metformin 500mg');
    }

    public function test_dashboard_scopes_to_the_callers_own_family_group(): void
    {
        $owner = User::factory()->create(['name' => 'Mandar Owner']);
        $stranger = User::factory()->create(['name' => 'Someone Else']);

        $this->postJson('/api/v1/records', [
            'member_id' => $this->selfMemberId($stranger),
            'category' => 'lab_report',
            'title' => 'Not mine',
        ], $this->authHeaders($stranger));

        $response = $this->getJson('/api/v1/dashboard', $this->authHeaders($owner))->assertOk();

        $response->assertJsonCount(0, 'data.recent_records');
    }

    public function test_health_score_endpoint_reports_not_implemented(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $this->getJson("/api/v1/members/{$memberId}/health-score", $this->authHeaders($user))
            ->assertStatus(501);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/dashboard')->assertStatus(401);
    }
}
