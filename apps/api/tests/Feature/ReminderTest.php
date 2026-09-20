<?php

namespace Tests\Feature;

use App\Models\FamilyMember;
use App\Models\Reminder;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReminderTest extends TestCase
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

    public function test_store_creates_a_reminder(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $response = $this->postJson('/api/v1/reminders', [
            'member_id' => $memberId,
            'reminder_type' => 'medication',
            'title' => 'Metformin 500mg',
            'due_at' => now()->addHours(2)->toIso8601String(),
            'recurrence' => 'daily',
        ], $this->authHeaders($user))->assertOk();

        $response->assertJsonPath('data.title', 'Metformin 500mg');
        $response->assertJsonPath('data.recurrence', 'daily');
        $response->assertJsonPath('data.notify_via.0', 'push');
        $response->assertJsonPath('data.is_active', true);
    }

    public function test_store_rejects_unknown_reminder_type(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $this->postJson('/api/v1/reminders', [
            'member_id' => $memberId,
            'reminder_type' => 'not_a_type',
            'title' => 'Test',
            'due_at' => now()->addHour()->toIso8601String(),
        ], $this->authHeaders($user))->assertStatus(422);
    }

    public function test_store_rejects_a_member_from_another_family_group(): void
    {
        $owner = User::factory()->create(['name' => 'Mandar Owner']);
        $stranger = User::factory()->create(['name' => 'Someone Else']);

        $this->postJson('/api/v1/reminders', [
            'member_id' => $this->selfMemberId($stranger),
            'reminder_type' => 'medication',
            'title' => 'Test',
            'due_at' => now()->addHour()->toIso8601String(),
        ], $this->authHeaders($owner))->assertStatus(422);
    }

    public function test_index_filters_by_upcoming(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $this->postJson('/api/v1/reminders', [
            'member_id' => $memberId,
            'reminder_type' => 'appointment',
            'title' => 'Past reminder',
            'due_at' => now()->subDay()->toIso8601String(),
        ], $this->authHeaders($user));

        $this->postJson('/api/v1/reminders', [
            'member_id' => $memberId,
            'reminder_type' => 'appointment',
            'title' => 'Future reminder',
            'due_at' => now()->addDay()->toIso8601String(),
        ], $this->authHeaders($user));

        $response = $this->getJson('/api/v1/reminders?upcoming=1', $this->authHeaders($user))->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'Future reminder');
    }

    public function test_update_can_disable_a_reminder(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $id = $this->postJson('/api/v1/reminders', [
            'member_id' => $memberId,
            'reminder_type' => 'medication',
            'title' => 'Test',
            'due_at' => now()->addHour()->toIso8601String(),
        ], $this->authHeaders($user))->json('data.id');

        $this->putJson("/api/v1/reminders/{$id}", ['is_active' => false], $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_destroy_removes_a_reminder(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $id = $this->postJson('/api/v1/reminders', [
            'member_id' => $memberId,
            'reminder_type' => 'medication',
            'title' => 'Test',
            'due_at' => now()->addHour()->toIso8601String(),
        ], $this->authHeaders($user))->json('data.id');

        $this->deleteJson("/api/v1/reminders/{$id}", [], $this->authHeaders($user))->assertOk();

        $this->assertDatabaseMissing('reminders', ['id' => $id]);
    }

    public function test_another_user_cannot_manage_a_reminder_in_someone_elses_group(): void
    {
        $owner = User::factory()->create(['name' => 'Mandar Owner']);
        $intruder = User::factory()->create(['name' => 'Random Person']);

        $id = $this->postJson('/api/v1/reminders', [
            'member_id' => $this->selfMemberId($owner),
            'reminder_type' => 'medication',
            'title' => 'Test',
            'due_at' => now()->addHour()->toIso8601String(),
        ], $this->authHeaders($owner))->json('data.id');

        $this->putJson("/api/v1/reminders/{$id}", ['title' => 'Hacked'], $this->authHeaders($intruder))
            ->assertStatus(404);
        $this->deleteJson("/api/v1/reminders/{$id}", [], $this->authHeaders($intruder))
            ->assertStatus(404);

        $this->assertSame('Test', Reminder::find($id)->title);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/reminders')->assertStatus(401);
        $this->postJson('/api/v1/reminders', [])->assertStatus(401);
    }
}
