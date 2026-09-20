<?php

namespace Tests\Feature;

use App\Models\FamilyMember;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorTest extends TestCase
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

    public function test_store_and_index_a_doctor(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);

        $this->postJson('/api/v1/doctors', [
            'name' => 'Dr. Ramesh Sharma',
            'speciality' => 'Cardiologist',
            'hospital_clinic' => 'Apollo Hospital, Delhi',
            'phone' => '+911234567890',
        ], $this->authHeaders($user))->assertOk()->assertJsonPath('data.name', 'Dr. Ramesh Sharma');

        $response = $this->getJson('/api/v1/doctors', $this->authHeaders($user))->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.speciality', 'Cardiologist');
    }

    public function test_index_search_filters_by_name_or_speciality(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $headers = $this->authHeaders($user);

        $this->postJson('/api/v1/doctors', ['name' => 'Dr. Ramesh Sharma', 'speciality' => 'Cardiologist'], $headers);
        $this->postJson('/api/v1/doctors', ['name' => 'Dr. Priya Kapoor', 'speciality' => 'Pediatrician'], $headers);

        $response = $this->getJson('/api/v1/doctors?q=cardio', $headers)->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Dr. Ramesh Sharma');
    }

    public function test_update_and_destroy_a_doctor(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $headers = $this->authHeaders($user);

        $id = $this->postJson('/api/v1/doctors', ['name' => 'Dr. Ramesh Sharma'], $headers)->json('data.id');

        $this->putJson("/api/v1/doctors/{$id}", ['phone' => '+919999999999'], $headers)
            ->assertOk()
            ->assertJsonPath('data.phone', '+919999999999');

        $this->deleteJson("/api/v1/doctors/{$id}", [], $headers)->assertOk();
        $this->assertDatabaseMissing('doctors', ['id' => $id]);
    }

    public function test_another_user_cannot_manage_a_doctor_in_someone_elses_group(): void
    {
        $owner = User::factory()->create(['name' => 'Mandar Owner']);
        $intruder = User::factory()->create(['name' => 'Random Person']);

        $id = $this->postJson('/api/v1/doctors', ['name' => 'Dr. Ramesh Sharma'], $this->authHeaders($owner))
            ->json('data.id');

        $this->putJson("/api/v1/doctors/{$id}", ['name' => 'Hacked'], $this->authHeaders($intruder))
            ->assertStatus(404);
        $this->deleteJson("/api/v1/doctors/{$id}", [], $this->authHeaders($intruder))
            ->assertStatus(404);
    }

    public function test_visits_aggregates_matching_health_records(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $headers = $this->authHeaders($user);
        $memberId = $this->selfMemberId($user);

        $doctorId = $this->postJson('/api/v1/doctors', [
            'name' => 'Dr. Ramesh Sharma',
            'hospital_clinic' => 'Apollo Hospital, Delhi',
        ], $headers)->json('data.id');

        $this->postJson('/api/v1/records', [
            'member_id' => $memberId,
            'category' => 'lab_report',
            'title' => 'Lipid Profile Report',
            'record_date' => '2026-03-12',
            'doctor_name' => 'Dr. Ramesh Sharma',
        ], $headers)->assertOk();

        $this->postJson('/api/v1/records', [
            'member_id' => $memberId,
            'category' => 'lab_report',
            'title' => 'Unrelated record',
        ], $headers)->assertOk();

        $response = $this->getJson("/api/v1/doctors/{$doctorId}/visits", $headers)->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'Lipid Profile Report');
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/doctors')->assertStatus(401);
        $this->postJson('/api/v1/doctors', [])->assertStatus(401);
    }
}
