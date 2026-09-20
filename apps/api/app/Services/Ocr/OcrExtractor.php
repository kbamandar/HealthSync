<?php

namespace App\Services\Ocr;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ExceptionInterface as ProcessExceptionInterface;
use Symfony\Component\Process\Process;

/**
 * Runs real local text/OCR extraction — tesseract for images, pdftotext
 * (poppler-utils) for PDFs. Stands in for the plan's Google Cloud Vision
 * integration: there's no GCP project/API key in this sandbox, but these
 * open-source tools need no credentials and give genuine (if less
 * accurate) extraction rather than a hardcoded stub. Any failure (binary
 * missing, corrupt file, timeout) degrades to an empty result rather than
 * throwing — OCR is a convenience, never a blocker to saving a record.
 */
class OcrExtractor
{
    public function __construct(private readonly LabReportParser $parser) {}

    /** @return array{raw_text: string|null, fields: array} */
    public function extract(string $absolutePath, string $mimeType): array
    {
        $rawText = match ($mimeType) {
            'application/pdf' => $this->runProcess(['pdftotext', '-layout', $absolutePath, '-']),
            'image/jpeg', 'image/png' => $this->runProcess(['tesseract', $absolutePath, 'stdout']),
            default => null,
        };

        if ($rawText === null || trim($rawText) === '') {
            return ['raw_text' => null, 'fields' => ['record_date' => null, 'lab_name' => null, 'test_values' => []]];
        }

        return ['raw_text' => $rawText, 'fields' => $this->parser->parse($rawText)];
    }

    private function runProcess(array $command): ?string
    {
        try {
            $process = new Process($command);
            $process->setTimeout(30);
            $process->run();

            if (! $process->isSuccessful()) {
                Log::info('OCR extraction command failed', ['command' => $command[0], 'error' => $process->getErrorOutput()]);

                return null;
            }

            return $process->getOutput();
        } catch (ProcessExceptionInterface $e) {
            Log::info('OCR extraction command unavailable', ['command' => $command[0], 'message' => $e->getMessage()]);

            return null;
        }
    }
}
