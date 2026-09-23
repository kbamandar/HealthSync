<?php

namespace Tests\Feature;

use App\Mail\DataExportReadyMail;
use App\Models\AuditEvent;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ComplianceTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(User $user): array
    {
        $token = app(TokenService::class)->issueAccessToken($user);

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_audit_log_lists_the_users_own_events_only(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $stranger = User::factory()->create(['name' => 'Someone Else']);

        AuditEvent::record('login.otp', $user->id);
        AuditEvent::record('record.upload', $user->id);
        AuditEvent::record('login.otp', $stranger->id);

        $response = $this->getJson('/api/v1/audit-log', $this->authHeaders($user))->assertOk();

        $response->assertJsonCount(2, 'data');
        $this->assertSame(2, $response->json('meta.total'));
        $this->assertContains('login.otp', collect($response->json('data'))->pluck('event_type')->all());
    }

    public function test_data_export_emails_a_download_link(): void
    {
        Mail::fake();

        $user = User::factory()->create(['name' => 'Mandar Owner', 'email' => 'export@example.com']);

        $this->postJson('/api/v1/data-export', [], $this->authHeaders($user))->assertOk();

        Mail::assertSent(DataExportReadyMail::class, function (DataExportReadyMail $mail) {
            return str_contains($mail->downloadUrl, '/internal/exports/download/');
        });

        $this->assertDatabaseHas('audit_events', ['user_id' => $user->id, 'event_type' => 'data_export.requested']);
    }

    public function test_data_export_is_throttled_well_under_ten_requests_per_day(): void
    {
        Mail::fake();

        $user = User::factory()->create(['name' => 'Mandar Owner', 'email' => 'export@example.com']);
        $headers = $this->authHeaders($user);

        $statuses = [];
        for ($i = 0; $i < 10; $i++) {
            $statuses[] = $this->postJson('/api/v1/data-export', [], $headers)->getStatusCode();
        }

        $this->assertContains(429, $statuses, 'Expected the data-export endpoint to throttle within 10 requests.');
        $this->assertSame(429, end($statuses), 'Expected throttling to still be in effect on the final attempt.');
    }

    public function test_account_deletion_can_be_requested_and_cancelled(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $headers = $this->authHeaders($user);

        $response = $this->postJson('/api/v1/account/delete', [], $headers)->assertOk();
        $this->assertNotNull($response->json('data.deletion_requested_at'));
        $this->assertNotNull($user->fresh()->deletion_requested_at);

        $this->deleteJson('/api/v1/account/delete', [], $headers)->assertOk();
        $this->assertNull($user->fresh()->deletion_requested_at);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/audit-log')->assertStatus(401);
        $this->postJson('/api/v1/data-export')->assertStatus(401);
        $this->postJson('/api/v1/account/delete')->assertStatus(401);
    }
}
