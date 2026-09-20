<?php

namespace Tests\Feature;

use App\Mail\ReminderDueMail;
use App\Models\FamilyMember;
use App\Models\Reminder;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReminderSchedulerTest extends TestCase
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

    public function test_dispatch_due_sends_email_and_deactivates_a_one_off_reminder(): void
    {
        Mail::fake();
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $reminderId = $this->postJson('/api/v1/reminders', [
            'member_id' => $memberId,
            'reminder_type' => 'appointment',
            'title' => 'Dr. Mehta follow-up',
            'due_at' => now()->subMinute()->toIso8601String(),
        ], $this->authHeaders($user))->json('data.id');

        $this->artisan('reminders:dispatch-due')->assertSuccessful();

        Mail::assertSent(ReminderDueMail::class, fn (ReminderDueMail $mail) => $mail->hasTo($user->email));

        $reminder = Reminder::findOrFail($reminderId);
        $this->assertFalse($reminder->is_active);
        $this->assertNotNull($reminder->last_sent_at);
    }

    public function test_dispatch_due_advances_a_daily_recurring_reminder_instead_of_deactivating_it(): void
    {
        Mail::fake();
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $originalDueAt = now()->subMinute();
        $reminderId = $this->postJson('/api/v1/reminders', [
            'member_id' => $memberId,
            'reminder_type' => 'medication',
            'title' => 'Metformin 500mg',
            'due_at' => $originalDueAt->toIso8601String(),
            'recurrence' => 'daily',
        ], $this->authHeaders($user))->json('data.id');

        $this->artisan('reminders:dispatch-due')->assertSuccessful();

        $reminder = Reminder::findOrFail($reminderId);
        $this->assertTrue($reminder->is_active);
        $this->assertEqualsWithDelta(
            $originalDueAt->copy()->addDay()->timestamp,
            $reminder->due_at->timestamp,
            5,
        );
    }

    public function test_dispatch_due_ignores_reminders_that_are_not_yet_due(): void
    {
        Mail::fake();
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $this->postJson('/api/v1/reminders', [
            'member_id' => $memberId,
            'reminder_type' => 'appointment',
            'title' => 'Not due yet',
            'due_at' => now()->addDay()->toIso8601String(),
        ], $this->authHeaders($user));

        $this->artisan('reminders:dispatch-due')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_dispatch_due_ignores_inactive_reminders(): void
    {
        Mail::fake();
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $id = $this->postJson('/api/v1/reminders', [
            'member_id' => $memberId,
            'reminder_type' => 'appointment',
            'title' => 'Disabled',
            'due_at' => now()->subMinute()->toIso8601String(),
        ], $this->authHeaders($user))->json('data.id');

        $this->putJson("/api/v1/reminders/{$id}", ['is_active' => false], $this->authHeaders($user));

        $this->artisan('reminders:dispatch-due')->assertSuccessful();

        Mail::assertNothingSent();
    }
}
