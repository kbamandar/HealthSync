<?php

namespace Tests\Feature;

use App\Mail\FamilyInviteMail;
use App\Models\FamilyGroup;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FamilyMemberTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(User $user): array
    {
        $token = app(TokenService::class)->issueAccessToken($user);

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_index_auto_provisions_family_group_and_self_member(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);

        $response = $this->getJson('/api/v1/family/members', $this->authHeaders($user))->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.relationship', 'self');
        $response->assertJsonPath('data.0.display_name', 'Mandar Owner');
        $response->assertJsonPath('data.0.member_user_id', $user->id);

        $this->assertDatabaseCount('family_groups', 1);
        $this->assertSame($user->id, FamilyGroup::first()->owner_id);
    }

    public function test_index_does_not_provision_self_member_before_profile_is_complete(): void
    {
        $user = User::factory()->create(['name' => null]);

        $this->getJson('/api/v1/family/members', $this->authHeaders($user))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // The group itself is still created so it exists once a name is set.
        $this->assertDatabaseCount('family_groups', 1);
        $this->assertDatabaseCount('family_members', 0);
    }

    public function test_add_guardian_managed_member(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);

        $response = $this->postJson('/api/v1/family/members', [
            'relationship' => 'child',
            'display_name' => 'Aarav',
            'is_guardian_managed' => true,
            'date_of_birth' => '2018-05-01',
        ], $this->authHeaders($user))->assertOk();

        $response->assertJsonPath('data.is_guardian_managed', true);
        $response->assertJsonPath('data.invite_status', 'accepted');
        $response->assertJsonPath('data.access_level', 'full_access');
        $response->assertJsonPath('data.member_user_id', null);
    }

    public function test_invite_mode_requires_email(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);

        $this->postJson('/api/v1/family/members', [
            'relationship' => 'spouse',
            'display_name' => 'Priya',
        ], $this->authHeaders($user))->assertStatus(422);
    }

    public function test_invite_mode_sends_email_and_creates_pending_member(): void
    {
        Mail::fake();
        $user = User::factory()->create(['name' => 'Mandar Owner']);

        $response = $this->postJson('/api/v1/family/members', [
            'relationship' => 'spouse',
            'display_name' => 'Priya',
            'email' => 'priya@example.com',
        ], $this->authHeaders($user))->assertOk();

        $response->assertJsonPath('data.invite_status', 'pending');
        $response->assertJsonPath('data.invite_email', 'priya@example.com');

        Mail::assertSent(FamilyInviteMail::class, fn (FamilyInviteMail $mail) => $mail->hasTo('priya@example.com'));

        $this->assertNotNull(FamilyMember::where('display_name', 'Priya')->firstOrFail()->invite_token);
    }

    public function test_updating_self_relationship_is_rejected(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $this->getJson('/api/v1/family/members', $this->authHeaders($user)); // provisions self member
        $self = FamilyMember::where('relationship', 'self')->firstOrFail();

        $this->putJson("/api/v1/family/members/{$self->id}", [
            'relationship' => 'child',
        ], $this->authHeaders($user))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'SELF_MEMBER_IMMUTABLE');
    }

    public function test_updating_other_fields_on_self_member_is_allowed(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $this->getJson('/api/v1/family/members', $this->authHeaders($user));
        $self = FamilyMember::where('relationship', 'self')->firstOrFail();

        $this->putJson("/api/v1/family/members/{$self->id}", [
            'blood_group' => 'O+',
        ], $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.blood_group', 'O+');
    }

    public function test_removing_self_member_is_rejected(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $this->getJson('/api/v1/family/members', $this->authHeaders($user));
        $self = FamilyMember::where('relationship', 'self')->firstOrFail();

        $this->deleteJson("/api/v1/family/members/{$self->id}", [], $this->authHeaders($user))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'SELF_MEMBER_IMMUTABLE');
    }

    public function test_destroy_soft_removes_and_excludes_from_index(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);
        $member = $this->createGuardianManagedChild($user);

        $this->deleteJson("/api/v1/family/members/{$member->id}", [], $this->authHeaders($user))->assertOk();

        $this->assertNotNull($member->fresh()->removed_at);

        $this->getJson('/api/v1/family/members', $this->authHeaders($user))
            ->assertJsonCount(1, 'data'); // just self left
    }

    public function test_another_user_cannot_manage_a_member_in_someone_elses_group(): void
    {
        $owner = User::factory()->create(['name' => 'Mandar Owner']);
        $intruder = User::factory()->create(['name' => 'Random Person']);
        $member = $this->createGuardianManagedChild($owner);

        $this->putJson("/api/v1/family/members/{$member->id}", [
            'display_name' => 'Hacked',
        ], $this->authHeaders($intruder))->assertStatus(404);

        $this->deleteJson("/api/v1/family/members/{$member->id}", [], $this->authHeaders($intruder))
            ->assertStatus(404);

        $this->assertSame('Aarav', $member->fresh()->display_name);
        $this->assertNull($member->fresh()->removed_at);
    }

    public function test_accept_invite_links_the_invitees_account(): void
    {
        Mail::fake();
        $owner = User::factory()->create(['name' => 'Mandar Owner']);
        $invitee = User::factory()->create(['email' => 'priya@example.com']);

        $this->postJson('/api/v1/family/members', [
            'relationship' => 'spouse',
            'display_name' => 'Priya',
            'email' => 'priya@example.com',
        ], $this->authHeaders($owner));
        $member = FamilyMember::where('display_name', 'Priya')->firstOrFail();

        $response = $this->postJson('/api/v1/family/invite/accept', [
            'invite_token' => $member->invite_token,
        ], $this->authHeaders($invitee))->assertOk();

        $response->assertJsonPath('data.invite_status', 'accepted');
        $response->assertJsonPath('data.member_user_id', $invitee->id);
        $this->assertNull($member->fresh()->invite_token);
    }

    public function test_accept_invite_with_wrong_email_is_rejected(): void
    {
        Mail::fake();
        $owner = User::factory()->create(['name' => 'Mandar Owner']);
        $stranger = User::factory()->create(['email' => 'stranger@example.com']);

        $this->postJson('/api/v1/family/members', [
            'relationship' => 'spouse',
            'display_name' => 'Priya',
            'email' => 'priya@example.com',
        ], $this->authHeaders($owner));
        $member = FamilyMember::where('display_name', 'Priya')->firstOrFail();

        $this->postJson('/api/v1/family/invite/accept', [
            'invite_token' => $member->invite_token,
        ], $this->authHeaders($stranger))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'INVITE_EMAIL_MISMATCH');

        $this->assertSame('pending', $member->fresh()->invite_status);
    }

    public function test_accept_invite_with_unknown_token_is_rejected(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);

        $this->postJson('/api/v1/family/invite/accept', [
            'invite_token' => 'not-a-real-token',
        ], $this->authHeaders($user))
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'INVITE_INVALID');
    }

    public function test_decline_invite(): void
    {
        Mail::fake();
        $owner = User::factory()->create(['name' => 'Mandar Owner']);
        $invitee = User::factory()->create(['email' => 'priya@example.com']);

        $this->postJson('/api/v1/family/members', [
            'relationship' => 'spouse',
            'display_name' => 'Priya',
            'email' => 'priya@example.com',
        ], $this->authHeaders($owner));
        $member = FamilyMember::where('display_name', 'Priya')->firstOrFail();

        $this->postJson('/api/v1/family/invite/decline', [
            'invite_token' => $member->invite_token,
        ], $this->authHeaders($invitee))->assertOk();

        $this->assertSame('declined', $member->fresh()->invite_status);
        $this->assertNull($member->fresh()->invite_token);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/family/members')->assertStatus(401);
        $this->postJson('/api/v1/family/members', [])->assertStatus(401);
    }

    private function createGuardianManagedChild(User $owner): FamilyMember
    {
        $this->postJson('/api/v1/family/members', [
            'relationship' => 'child',
            'display_name' => 'Aarav',
            'is_guardian_managed' => true,
        ], $this->authHeaders($owner));

        return FamilyMember::where('display_name', 'Aarav')->firstOrFail();
    }
}
