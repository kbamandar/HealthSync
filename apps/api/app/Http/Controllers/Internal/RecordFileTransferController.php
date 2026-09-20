<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Services\Records\RecordStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Handlers behind the signed routes RecordStorageService hands out in place
 * of real S3 presigned URLs. Reached without app auth — Laravel's `signed`
 * middleware is the only gate, exactly like an S3 presigned URL's own
 * signature is the only gate on that request.
 */
class RecordFileTransferController extends Controller
{
    public function __construct(private readonly RecordStorageService $storage) {}

    public function upload(Request $request, string $key): Response
    {
        $contents = $request->getContent();

        if (strlen($contents) === 0 || strlen($contents) > $this->storage->maxFileSizeBytes()) {
            return response('', 413);
        }

        $this->storage->put($key, $contents);

        return response('', 204);
    }

    public function download(Request $request, string $key)
    {
        if (! $this->storage->exists($key)) {
            abort(404);
        }

        return response($this->storage->get($key), 200, [
            'Content-Type' => $this->storage->mimeType($key) ?? 'application/octet-stream',
        ]);
    }
}
