<?php

namespace App\Console\Commands;

use App\Models\AuditEvent;
use App\Models\ConsentRecord;
use App\Models\Device;
use App\Models\Doctor;
use App\Models\FamilyGroup;
use App\Models\FamilyMember;
use App\Models\HealthRecord;
use App\Models\RecordFile;
use App\Models\RefreshToken;
use App\Models\Reminder;
use App\Models\SharedLink;
use App\Models\User;
use App\Models\VitalReading;
use App\Services\Records\RecordStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Hard-deletes accounts whose 30-day grace period (ComplianceController::
 * requestAccountDeletion) has elapsed. Deletes explicitly, child-first,
 * rather than relying on DB cascade constraints (none of the FKs here are
 * ON DELETE CASCADE) — that keeps the purge auditable and lets audit_events
 * be anonymised (user_id set null) instead of destroyed, since the security
 * trail itself is not the user's personal data to erase.
 */
class PurgeDeletedAccounts extends Command
{
    protected $signature = 'accounts:purge-deleted';

    protected $description = 'Permanently delete accounts past their 30-day account-deletion grace period';

    public function handle(RecordStorageService $storage): int
    {
        $users = User::query()
            ->whereNotNull('deletion_requested_at')
            ->where('deletion_requested_at', '<=', now()->subDays(30))
            ->get();

        foreach ($users as $user) {
            DB::transaction(fn () => $this->purge($user, $storage));
        }

        $this->info("Purged {$users->count()} account(s).");

        return self::SUCCESS;
    }

    private function purge(User $user, RecordStorageService $storage): void
    {
        $group = FamilyGroup::where('owner_id', $user->id)->first();

        if ($group) {
            $recordIds = HealthRecord::where('family_group_id', $group->id)->pluck('id');
            $memberIds = FamilyMember::where('family_group_id', $group->id)->pluck('id');

            foreach (RecordFile::whereIn('record_id', $recordIds)->get() as $file) {
                $storage->delete($file->s3_key);
            }

            RecordFile::whereIn('record_id', $recordIds)->delete();
            SharedLink::whereIn('record_id', $recordIds)->orWhereIn('member_id', $memberIds)->delete();
            HealthRecord::where('family_group_id', $group->id)->delete();
            VitalReading::where('family_group_id', $group->id)->delete();
            Reminder::where('family_group_id', $group->id)->delete();
            Doctor::where('family_group_id', $group->id)->delete();
            FamilyMember::where('family_group_id', $group->id)->delete();
            $group->delete();
        }

        SharedLink::where('created_by_id', $user->id)->delete();
        ConsentRecord::where('user_id', $user->id)->delete();
        Device::where('user_id', $user->id)->delete();
        RefreshToken::where('user_id', $user->id)->delete();
        AuditEvent::where('user_id', $user->id)->update(['user_id' => null]);

        $user->delete();
    }
}
