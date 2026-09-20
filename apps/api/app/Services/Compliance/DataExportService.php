<?php

namespace App\Services\Compliance;

use App\Models\Doctor;
use App\Models\FamilyGroup;
use App\Models\FamilyMember;
use App\Models\HealthRecord;
use App\Models\Reminder;
use App\Models\User;
use App\Models\VitalReading;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Builds the "download a copy of your data" ZIP (Sprint 7 NFR). Real S3 +
 * Gotenberg-grade infra doesn't exist in this sandbox, so the ZIP is built
 * with PHP's bundled ZipArchive and stored on the local 'data-exports' disk
 * behind the same signed-URL contract as record files (see
 * RecordStorageService) rather than a real S3 presigned URL.
 */
class DataExportService
{
    private const DISK = 'data-exports';

    public function generate(User $user, FamilyGroup $group): string
    {
        $key = Str::uuid()->toString().'.zip';
        $tempPath = tempnam(sys_get_temp_dir(), 'export_').'.zip';

        $zip = new ZipArchive;
        $zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('profile.json', $this->json([
            'id' => $user->id,
            'email' => $user->email,
            'mobile' => $user->mobile,
            'name' => $user->name,
            'date_of_birth' => $user->date_of_birth?->toDateString(),
            'gender' => $user->gender,
            'blood_group' => $user->blood_group,
        ]));

        $zip->addFromString('family_members.json', $this->json(
            FamilyMember::where('family_group_id', $group->id)->get()->map(fn (FamilyMember $m) => [
                'id' => $m->id,
                'display_name' => $m->display_name,
                'relationship' => $m->relationship,
                'date_of_birth' => $m->date_of_birth?->toDateString(),
                'gender' => $m->gender,
                'blood_group' => $m->blood_group,
            ])->all(),
        ));

        $zip->addFromString('health_records.json', $this->json(
            HealthRecord::where('family_group_id', $group->id)->active()->get()->map(fn (HealthRecord $r) => [
                'id' => $r->id,
                'member_id' => $r->member_id,
                'category' => $r->category,
                'title' => $r->title,
                'record_date' => $r->record_date?->toDateString(),
                'doctor_name' => $r->doctor_name,
                'hospital_clinic' => $r->hospital_clinic,
                'notes' => $r->notes,
                'custom_tags' => $r->custom_tags,
                'created_at' => $r->created_at->toIso8601String(),
            ])->all(),
        ));

        $zip->addFromString('vitals.json', $this->json(
            VitalReading::where('family_group_id', $group->id)->get()->map(fn (VitalReading $v) => [
                'id' => $v->id,
                'member_id' => $v->member_id,
                'vital_type' => $v->vital_type,
                'value' => $v->value,
                'unit' => $v->unit,
                'recorded_at' => $v->recorded_at->toIso8601String(),
            ])->all(),
        ));

        $zip->addFromString('reminders.json', $this->json(
            Reminder::where('family_group_id', $group->id)->get()->map(fn (Reminder $r) => [
                'id' => $r->id,
                'member_id' => $r->member_id,
                'reminder_type' => $r->reminder_type,
                'title' => $r->title,
                'due_at' => $r->due_at->toIso8601String(),
                'recurrence' => $r->recurrence,
            ])->all(),
        ));

        $zip->addFromString('doctors.json', $this->json(
            Doctor::where('family_group_id', $group->id)->get()->map(fn (Doctor $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'speciality' => $d->speciality,
                'hospital_clinic' => $d->hospital_clinic,
                'phone' => $d->phone,
            ])->all(),
        ));

        $zip->close();

        Storage::disk(self::DISK)->put($key, file_get_contents($tempPath));
        unlink($tempPath);

        return $key;
    }

    public function createDownloadUrl(string $key): string
    {
        return URL::temporarySignedRoute(
            'internal.exports.download',
            now()->addHours(24),
            ['key' => $key],
        );
    }

    private function json(mixed $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
