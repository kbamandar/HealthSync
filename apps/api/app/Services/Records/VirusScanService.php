<?php

namespace App\Services\Records;

/**
 * Stands in for the plan's ClamAV Lambda trigger — no ClamAV binary or AWS
 * Lambda is available in this sandbox. Kept as its own service (rather than
 * inlined in the controller) so swapping in a real scanner later is a
 * one-file change with the call site untouched.
 */
class VirusScanService
{
    public function scan(string $storageKey): bool
    {
        return true;
    }
}
