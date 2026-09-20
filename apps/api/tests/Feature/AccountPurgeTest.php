<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\FamilyGroup;
use App\Models\FamilyMember;
use App\Models\HealthRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AccountPurgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_purge_deletes_accounts_past_their_grace_period(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner', 'deletion_requested_at' => now()->subDays(31)]);
        $group = FamilyGroup::create(['owner_id' => $user->id]);
        $member = FamilyMember::create([
            'family_group_id' => $group->id,
            'member_user_id' => $user->id,
            'added_by_user_id' => $user->id,
            'relationship' => 'self',
            'display_name' => 'Mandar Owner',
            'access_level' => 'full_access',
            'invite_status' => 'accepted',
        ]);
        $record = HealthRecord::create([
            'family_group_id' => $group->id,
            'member_id' => $member->id,
            'uploaded_by_id' => $user->id,
            'category' => 'lab_report',
            'title' => 'Old report',
            'is_favourite' => false,
            'is_deleted' => false,
        ]);
        Doctor::create(['family_group_id' => $group->id, 'name' => 'Dr. Test']);

        Artisan::call('accounts:purge-deleted');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('family_groups', ['id' => $group->id]);
        $this->assertDatabaseMissing('family_members', ['id' => $member->id]);
        $this->assertDatabaseMissing('health_records', ['id' => $record->id]);
        $this->assertDatabaseMissing('doctors', ['family_group_id' => $group->id]);
    }

    public function test_purge_leaves_accounts_still_inside_their_grace_period(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner', 'deletion_requested_at' => now()->subDays(5)]);

        Artisan::call('accounts:purge-deleted');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_purge_ignores_accounts_with_no_deletion_requested(): void
    {
        $user = User::factory()->create(['name' => 'Mandar Owner']);

        Artisan::call('accounts:purge-deleted');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
}
