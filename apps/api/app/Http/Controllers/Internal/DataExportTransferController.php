<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Reached without app auth — Laravel's `signed` middleware is the only gate,
 * exactly like RecordFileTransferController stands in for an S3 presigned
 * URL (see that class for the full rationale).
 */
class DataExportTransferController extends Controller
{
    private const DISK = 'data-exports';

    public function download(Request $request, string $key)
    {
        if (! Storage::disk(self::DISK)->exists($key)) {
            abort(404);
        }

        return response(Storage::disk(self::DISK)->get($key), 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="healthsync-export.zip"',
        ]);
    }
}
