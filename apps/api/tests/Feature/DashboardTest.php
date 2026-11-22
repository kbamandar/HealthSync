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

    public function test_health_score_with_no_data_returns_a_neutral_baseline(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $response = $this->getJson("/api/v1/members/{$memberId}/health-score", $this->authHeaders($user))
            ->assertOk();

        $response->assertJsonPath('data.factors.0.key', 'annual_checkup');
        $response->assertJsonPath('data.factors.0.status', 'Not scheduled');
        $response->assertJsonPath('data.factors.1.key', 'vitals_trend');
        $response->assertJsonPath('data.factors.1.status', 'No recent readings');
        $response->assertJsonPath('data.factors.2.key', 'medication_adherence');
        $response->assertJsonPath('data.factors.2.status', 'No active medications');
        $response->assertJsonPath('data.factors.3.key', 'records_completeness');
        $this->assertIsInt($response->json('data.score'));
        $this->assertNotEmpty($response->json('data.summary'));
    }

    public function test_health_score_rewards_a_well_maintained_member(): void
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
        ], $this->authHeaders($user))->assertOk();

        $this->postJson('/api/v1/vitals', [
            'member_id' => $memberId,
            'vital_type' => 'bp_systolic',
            'value' => 115,
        ], $this->authHeaders($user))->assertOk();

        $this->postJson('/api/v1/reminders', [
            'member_id' => $memberId,
            'reminder_type' => 'annual_checkup',
            'title' => 'Yearly physical',
            'due_at' => now()->addMonths(6)->toIso8601String(),
        ], $this->authHeaders($user))->assertOk();

        $this->postJson('/api/v1/reminders', [
            'member_id' => $memberId,
            'reminder_type' => 'medication',
            'title' => 'Metformin 500mg',
            'due_at' => now()->addHours(6)->toIso8601String(),
        ], $this->authHeaders($user))->assertOk();

        $response = $this->getJson("/api/v1/members/{$memberId}/health-score", $this->authHeaders($user))
            ->assertOk();

        $response->assertJsonPath('data.factors.0.status', 'Good');
        $response->assertJsonPath('data.factors.1.status', 'Good');
        $response->assertJsonPath('data.factors.2.status', 'Good');
        $response->assertJsonPath('data.factors.3.status', 'Good');
        $this->assertGreaterThanOrEqual(90, $response->json('data.score'));
        $this->assertSame('excellent', $response->json('data.status'));
    }

    public function test_health_score_flags_overdue_reminders(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $this->postJson('/api/v1/reminders', [
            'member_id' => $memberId,
            'reminder_type' => 'annual_checkup',
            'title' => 'Overdue physical',
            'due_at' => now()->subDays(40)->toIso8601String(),
        ], $this->authHeaders($user))->assertOk();

        $this->postJson('/api/v1/reminders', [
            'member_id' => $memberId,
            'reminder_type' => 'medication',
            'title' => 'Missed dose',
            'due_at' => now()->subHours(2)->toIso8601String(),
        ], $this->authHeaders($user))->assertOk();

        $response = $this->getJson("/api/v1/members/{$memberId}/health-score", $this->authHeaders($user))
            ->assertOk();

        $response->assertJsonPath('data.factors.0.status', 'Overdue');
        $response->assertJsonPath('data.factors.2.status', 'Behind');
    }

    public function test_health_score_rejects_a_member_outside_the_callers_family_group(): void
    {
        $owner = User::factory()->create(['name' => 'Mandar Owner']);
        $stranger = User::factory()->create(['name' => 'Someone Else']);
        $strangerMemberId = $this->selfMemberId($stranger);

        $this->getJson("/api/v1/members/{$strangerMemberId}/health-score", $this->authHeaders($owner))
            ->assertStatus(404);
    }

    public function test_health_insights_with_no_data_returns_an_empty_list(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $response = $this->getJson("/api/v1/members/{$memberId}/health-insights", $this->authHeaders($user))
            ->assertOk();

        $response->assertJsonCount(0, 'data.insights');
    }

    public function test_health_insights_flags_a_worsening_vital_trend(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        foreach ([
            ['value' => 130, 'daysAgo' => 3],
            ['value' => 145, 'daysAgo' => 2],
            ['value' => 150, 'daysAgo' => 1],
        ] as $reading) {
            $this->postJson('/api/v1/vitals', [
                'member_id' => $memberId,
                'vital_type' => 'bp_systolic',
                'value' => $reading['value'],
                'recorded_at' => now()->subDays($reading['daysAgo'])->toIso8601String(),
            ], $this->authHeaders($user))->assertOk();
        }

        $response = $this->getJson("/api/v1/members/{$memberId}/health-insights", $this->authHeaders($user))
            ->assertOk();

        $response->assertJsonCount(1, 'data.insights');
        $response->assertJsonPath('data.insights.0.id', 'vital_trend:bp_systolic');
        $response->assertJsonPath('data.insights.0.severity', 'warning');
    }

    public function test_health_insights_flags_overdue_medication_and_checkup_reminders(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $this->postJson('/api/v1/reminders', [
            'member_id' => $memberId,
            'reminder_type' => 'medication',
            'title' => 'Missed dose',
            'due_at' => now()->subHours(2)->toIso8601String(),
        ], $this->authHeaders($user))->assertOk();

        $this->postJson('/api/v1/reminders', [
            'member_id' => $memberId,
            'reminder_type' => 'annual_checkup',
            'title' => 'Overdue physical',
            'due_at' => now()->subDays(10)->toIso8601String(),
        ], $this->authHeaders($user))->assertOk();

        $response = $this->getJson("/api/v1/members/{$memberId}/health-insights", $this->authHeaders($user))
            ->assertOk();

        $ids = collect($response->json('data.insights'))->pluck('id');
        $this->assertTrue($ids->contains('medication_overdue'));
        $this->assertTrue($ids->contains('annual_checkup_overdue'));
    }

    public function test_health_insights_rejects_a_member_outside_the_callers_family_group(): void
    {
        $owner = User::factory()->create(['name' => 'Mandar Owner']);
        $stranger = User::factory()->create(['name' => 'Someone Else']);
        $strangerMemberId = $this->selfMemberId($stranger);

        $this->getJson("/api/v1/members/{$strangerMemberId}/health-insights", $this->authHeaders($owner))
            ->assertStatus(404);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/dashboard')->assertStatus(401);
    }
}
