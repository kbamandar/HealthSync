<?php

namespace App\Jobs;

use App\Models\RecordFile;
use App\Services\Ocr\OcrExtractor;
use App\Services\Records\RecordStorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExtractRecordFileOcr implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly string $recordFileId) {}

    public function handle(OcrExtractor $extractor, RecordStorageService $storage): void
    {
        $file = RecordFile::find($this->recordFileId);

        if (! $file || ! in_array($file->mime_type, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
            return;
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'ocr_').'.'.$file->file_type;

        try {
            file_put_contents($tempPath, $storage->get($file->s3_key));
            $result = $extractor->extract($tempPath, $file->mime_type);

            $file->update([
                'ocr_extracted' => true,
                'ocr_data' => $result,
            ]);
        } finally {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }
}
