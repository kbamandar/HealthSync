<?php

namespace App\Jobs;

use App\Mail\DataExportReadyMail;
use App\Models\FamilyGroup;
use App\Models\User;
use App\Services\Compliance\DataExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class GenerateDataExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly string $userId, private readonly string $familyGroupId) {}

    public function handle(DataExportService $exports): void
    {
        $user = User::find($this->userId);
        $group = FamilyGroup::find($this->familyGroupId);

        if (! $user || ! $group) {
            return;
        }

        $key = $exports->generate($user, $group);
        $downloadUrl = $exports->createDownloadUrl($key);

        Mail::to($user->email)->send(new DataExportReadyMail($downloadUrl));
    }
}
