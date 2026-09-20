<?php

namespace Tests\Feature;

use App\Models\FamilyMember;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakePdf;
use Tests\TestCase;

class HealthRecordOcrTest extends TestCase
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

    /** Uploads a real, OCR-able PDF onto a fresh record and returns its id. */
    private function uploadOcrablePdf(User $user, string $memberId): string
    {
        $recordId = $this->postJson('/api/v1/records', [
            'member_id' => $memberId,
            'category' => 'lab_report',
        ], $this->authHeaders($user))->json('data.id');

        $uploadUrlResponse = $this->postJson("/api/v1/records/{$recordId}/upload-url", [
            'mime_type' => 'application/pdf',
        ], $this->authHeaders($user));

        $key = $uploadUrlResponse->json('data.s3_key');
        $uploadUrl = $uploadUrlResponse->json('data.upload_url');

        $pdf = FakePdf::withLines(['Apollo Diagnostics', '12/03/2026', 'Hemoglobin 13.8 g/dL']);
        $this->call('PUT', $uploadUrl, [], [], [], [], $pdf);

        $this->postJson("/api/v1/records/{$recordId}/files", [
            's3_key' => $key,
            'mime_type' => 'application/pdf',
        ], $this->authHeaders($user));

        return $recordId;
    }

    public function test_registering_a_file_runs_ocr_synchronously_in_tests_and_stores_results(): void
    {
        Storage::fake('health-records');
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $recordId = $this->uploadOcrablePdf($user, $memberId);

        $show = $this->getJson("/api/v1/records/{$recordId}", $this->authHeaders($user))->assertOk();

        $show->assertJsonPath('data.files.0.ocr_extracted', true);
        $show->assertJsonPath('data.files.0.ocr_data.fields.record_date', '2026-03-12');
        $show->assertJsonPath('data.files.0.ocr_data.fields.lab_name', 'Apollo Diagnostics');
    }

    public function test_apply_ocr_fills_in_blank_metadata_fields_only(): void
    {
        Storage::fake('health-records');
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $recordId = $this->uploadOcrablePdf($user, $memberId);

        $response = $this->postJson("/api/v1/records/{$recordId}/apply-ocr", [], $this->authHeaders($user))->assertOk();

        $response->assertJsonPath('data.record_date', '2026-03-12');
        $response->assertJsonPath('data.hospital_clinic', 'Apollo Diagnostics');
        $this->assertStringContainsString('Hemoglobin', $response->json('data.notes'));
    }

    public function test_apply_ocr_does_not_overwrite_fields_the_user_already_set(): void
    {
        Storage::fake('health-records');
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $recordId = $this->uploadOcrablePdf($user, $memberId);
        $this->putJson("/api/v1/records/{$recordId}", ['hospital_clinic' => 'My Own Clinic'], $this->authHeaders($user));

        $response = $this->postJson("/api/v1/records/{$recordId}/apply-ocr", [], $this->authHeaders($user))->assertOk();

        $response->assertJsonPath('data.hospital_clinic', 'My Own Clinic');
    }

    public function test_apply_ocr_reports_not_ready_when_no_ocr_data_exists(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $memberId = $this->selfMemberId($user);

        $recordId = $this->postJson('/api/v1/records', [
            'member_id' => $memberId,
            'category' => 'lab_report',
        ], $this->authHeaders($user))->json('data.id');

        $this->postJson("/api/v1/records/{$recordId}/apply-ocr", [], $this->authHeaders($user))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'OCR_NOT_READY');
    }
}
