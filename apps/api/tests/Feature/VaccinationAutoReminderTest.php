<?php

namespace Tests\Feature;

use App\Models\FamilyMember;
use App\Models\Reminder;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VaccinationAutoReminderTest extends TestCase
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

    public function test_uploading_a_vaccination_record_creates_a_one_year_follow_up_reminder(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $recordId = $this->postJson('/api/v1/records', [
            'member_id' => $memberId,
            'category' => 'vaccination',
            'title' => 'Flu shot',
            'record_date' => '2026-03-12',
        ], $this->authHeaders($user))->json('data.id');

        $reminder = Reminder::where('linked_record_id', $recordId)->firstOrFail();

        $this->assertSame('vaccination', $reminder->reminder_type);
        $this->assertSame('Follow-up: Flu shot', $reminder->title);
        $this->assertSame('2027-03-12', $reminder->due_at->toDateString());
        $this->assertTrue($reminder->is_active);
    }

    public function test_uploading_a_non_vaccination_record_does_not_create_a_reminder(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $this->postJson('/api/v1/records', [
            'member_id' => $memberId,
            'category' => 'lab_report',
            'title' => 'CBC',
        ], $this->authHeaders($user));

        $this->assertDatabaseCount('reminders', 0);
    }
}
