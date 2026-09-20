<?php

namespace Tests\Feature;

use App\Models\FamilyMember;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HealthRecordTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(User $user): array
    {
        $token = app(TokenService::class)->issueAccessToken($user);

        return ['Authorization' => "Bearer {$token}"];
    }

    /** Provisions the family group + 'self' member and returns the member id. */
    private function selfMemberId(User $user): string
    {
        $this->getJson('/api/v1/family/members', $this->authHeaders($user));

        return FamilyMember::where('relationship', 'self')->firstOrFail()->id;
    }

    public function test_store_creates_a_record_for_a_member_in_the_caller_family_group(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $response = $this->postJson('/api/v1/records', [
            'member_id' => $memberId,
            'category' => 'lab_report',
            'title' => 'CBC + Lipid Profile',
            'record_date' => '2026-03-12',
            'doctor_name' => 'Dr. Priya Mehta',
            'hospital_clinic' => 'Apollo Hospital',
            'custom_tags' => ['Annual checkup'],
        ], $this->authHeaders($user))->assertOk();

        $response->assertJsonPath('data.category', 'lab_report');
        $response->assertJsonPath('data.title', 'CBC + Lipid Profile');
        $response->assertJsonPath('data.custom_tags.0', 'Annual checkup');
        $response->assertJsonPath('data.is_favourite', false);

        $this->assertDatabaseCount('health_records', 1);
    }

    public function test_store_rejects_a_member_from_another_family_group(): void
    {
        $owner = User::factory()->create(['name' => 'Mandar Owner']);
        $stranger = User::factory()->create(['name' => 'Someone Else']);
        $strangerMemberId = $this->selfMemberId($stranger);

        $this->postJson('/api/v1/records', [
            'member_id' => $strangerMemberId,
            'category' => 'lab_report',
        ], $this->authHeaders($owner))->assertStatus(422);
    }

    public function test_store_rejects_unknown_category(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $this->postJson('/api/v1/records', [
            'member_id' => $memberId,
            'category' => 'not_a_real_category',
        ], $this->authHeaders($user))->assertStatus(422);
    }

    public function test_index_lists_only_the_callers_family_records(): void
    {
        $owner = User::factory()->create(['name' => 'Mandar Owner']);
        $stranger = User::factory()->create(['name' => 'Someone Else']);

        $this->postJson('/api/v1/records', [
            'member_id' => $this->selfMemberId($owner),
            'category' => 'lab_report',
            'title' => 'Owner record',
        ], $this->authHeaders($owner));

        $this->postJson('/api/v1/records', [
            'member_id' => $this->selfMemberId($stranger),
            'category' => 'lab_report',
            'title' => 'Stranger record',
        ], $this->authHeaders($stranger));

        $response = $this->getJson('/api/v1/records', $this->authHeaders($owner))->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'Owner record');
        $response->assertJsonPath('meta.total', 1);
    }

    public function test_index_filters_by_category(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $this->postJson('/api/v1/records', ['member_id' => $memberId, 'category' => 'lab_report', 'title' => 'Labs'], $this->authHeaders($user));
        $this->postJson('/api/v1/records', ['member_id' => $memberId, 'category' => 'prescription', 'title' => 'Meds'], $this->authHeaders($user));

        $response = $this->getJson('/api/v1/records?category=prescription', $this->authHeaders($user))->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'Meds');
    }

    public function test_full_upload_flow_registers_a_file_on_the_record(): void
    {
        Storage::fake('health-records');
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $recordId = $this->postJson('/api/v1/records', [
            'member_id' => $memberId,
            'category' => 'lab_report',
        ], $this->authHeaders($user))->json('data.id');

        $uploadUrlResponse = $this->postJson("/api/v1/records/{$recordId}/upload-url", [
            'mime_type' => 'application/pdf',
        ], $this->authHeaders($user))->assertOk();

        $key = $uploadUrlResponse->json('data.s3_key');
        $uploadUrl = $uploadUrlResponse->json('data.upload_url');

        $this->call('PUT', $uploadUrl, [], [], [], [], 'raw-pdf-bytes')
            ->assertNoContent();

        $registerResponse = $this->postJson("/api/v1/records/{$recordId}/files", [
            's3_key' => $key,
            'mime_type' => 'application/pdf',
        ], $this->authHeaders($user))->assertOk();

        $registerResponse->assertJsonPath('data.file_type', 'pdf');
        $registerResponse->assertJsonPath('data.mime_type', 'application/pdf');

        $show = $this->getJson("/api/v1/records/{$recordId}", $this->authHeaders($user))->assertOk();
        $show->assertJsonCount(1, 'data.files');
    }

    public function test_register_file_rejects_a_key_that_was_never_uploaded(): void
    {
        Storage::fake('health-records');
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $recordId = $this->postJson('/api/v1/records', [
            'member_id' => $memberId,
            'category' => 'lab_report',
        ], $this->authHeaders($user))->json('data.id');

        $this->postJson("/api/v1/records/{$recordId}/files", [
            's3_key' => 'never-uploaded.pdf',
            'mime_type' => 'application/pdf',
        ], $this->authHeaders($user))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'FILE_NOT_UPLOADED');
    }

    public function test_update_toggles_favourite_and_tags(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $recordId = $this->postJson('/api/v1/records', [
            'member_id' => $memberId,
            'category' => 'lab_report',
        ], $this->authHeaders($user))->json('data.id');

        $response = $this->putJson("/api/v1/records/{$recordId}", [
            'is_favourite' => true,
            'custom_tags' => ['urgent', 'follow-up'],
        ], $this->authHeaders($user))->assertOk();

        $response->assertJsonPath('data.is_favourite', true);
        $response->assertJsonPath('data.custom_tags.1', 'follow-up');
    }

    public function test_destroy_soft_deletes_and_moves_to_recycle_bin(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $recordId = $this->postJson('/api/v1/records', [
            'member_id' => $memberId,
            'category' => 'lab_report',
            'title' => 'To delete',
        ], $this->authHeaders($user))->json('data.id');

        $this->deleteJson("/api/v1/records/{$recordId}", [], $this->authHeaders($user))->assertOk();

        $this->getJson('/api/v1/records', $this->authHeaders($user))->assertJsonCount(0, 'data');

        $bin = $this->getJson('/api/v1/records/recycle-bin', $this->authHeaders($user))->assertOk();
        $bin->assertJsonCount(1, 'data');
        $bin->assertJsonPath('data.0.title', 'To delete');
    }

    public function test_restore_brings_a_record_back_from_the_recycle_bin(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $recordId = $this->postJson('/api/v1/records', [
            'member_id' => $memberId,
            'category' => 'lab_report',
        ], $this->authHeaders($user))->json('data.id');

        $this->deleteJson("/api/v1/records/{$recordId}", [], $this->authHeaders($user));
        $this->postJson("/api/v1/records/{$recordId}/restore", [], $this->authHeaders($user))->assertOk();

        $this->getJson('/api/v1/records', $this->authHeaders($user))->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/records/recycle-bin', $this->authHeaders($user))->assertJsonCount(0, 'data');
    }

    public function test_another_user_cannot_view_update_or_delete_someone_elses_record(): void
    {
        $owner = User::factory()->create(['name' => 'Mandar Owner']);
        $intruder = User::factory()->create(['name' => 'Random Person']);

        $recordId = $this->postJson('/api/v1/records', [
            'member_id' => $this->selfMemberId($owner),
            'category' => 'lab_report',
        ], $this->authHeaders($owner))->json('data.id');

        $this->getJson("/api/v1/records/{$recordId}", $this->authHeaders($intruder))->assertStatus(404);
        $this->putJson("/api/v1/records/{$recordId}", ['title' => 'Hacked'], $this->authHeaders($intruder))->assertStatus(404);
        $this->deleteJson("/api/v1/records/{$recordId}", [], $this->authHeaders($intruder))->assertStatus(404);

        $this->assertDatabaseHas('health_records', ['id' => $recordId, 'is_deleted' => false]);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/records')->assertStatus(401);
        $this->postJson('/api/v1/records', [])->assertStatus(401);
    }
}
