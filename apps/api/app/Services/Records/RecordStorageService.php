<?php

namespace App\Services\Records;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stands in for S3 presigned URLs (no AWS account in this sandbox). The two-
 * step contract the plan describes — get an upload URL, PUT the file, then
 * register it — is preserved exactly so a real S3 client can be dropped in
 * later without changing the API surface: internal signed routes
 * (routes/api.php `internal.records.*`) play the role of the S3 bucket.
 */
class RecordStorageService
{
    private const DISK = 'health-records';

    private const ALLOWED_MIME_TYPES = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    private const MAX_FILE_SIZE_BYTES = 20 * 1024 * 1024; // 20MB

    public function extensionForMimeType(string $mimeType): string
    {
        if (! isset(self::ALLOWED_MIME_TYPES[$mimeType])) {
            throw ValidationException::withMessages([
                'mime_type' => 'Only PDF, JPG, and PNG files are supported.',
            ]);
        }

        return self::ALLOWED_MIME_TYPES[$mimeType];
    }

    public function isAllowedMimeType(string $mimeType): bool
    {
        return isset(self::ALLOWED_MIME_TYPES[$mimeType]);
    }

    public function maxFileSizeBytes(): int
    {
        return self::MAX_FILE_SIZE_BYTES;
    }

    /**
     * @return array{key: string, url: string, expires_in: int}
     */
    public function createUploadUrl(string $mimeType): array
    {
        $extension = $this->extensionForMimeType($mimeType);
        $key = Str::uuid()->toString().'.'.$extension;
        $expiresIn = 900; // 15 minutes, matching the plan's presigned-URL expiry

        $url = URL::temporarySignedRoute(
            'internal.records.upload',
            now()->addSeconds($expiresIn),
            ['key' => $key],
        );

        return ['key' => $key, 'url' => $url, 'expires_in' => $expiresIn];
    }

    public function createDownloadUrl(string $key): string
    {
        return URL::temporarySignedRoute(
            'internal.records.download',
            now()->addMinutes(15),
            ['key' => $key],
        );
    }

    public function put(string $key, string $contents): void
    {
        Storage::disk(self::DISK)->put($key, $contents);
    }

    public function exists(string $key): bool
    {
        return Storage::disk(self::DISK)->exists($key);
    }

    public function size(string $key): int
    {
        return Storage::disk(self::DISK)->size($key);
    }

    public function get(string $key): string
    {
        return Storage::disk(self::DISK)->get($key);
    }

    public function mimeType(string $key): ?string
    {
        return Storage::disk(self::DISK)->mimeType($key) ?: null;
    }

    public function delete(string $key): void
    {
        Storage::disk(self::DISK)->delete($key);
    }
}
