<?php

namespace App\Services\Ocr;

use App\Services\Ocr\Concerns\ParsesOcrDates;

/**
 * Heuristic, regex-based structured-field extraction from raw OCR/PDF text.
 * There is no ML model here — just pattern matching good enough to pre-fill
 * a form the user can still edit (per the plan's own fallback: "always
 * allow manual entry").
 */
class LabReportParser
{
    use ParsesOcrDates;

    private const VALUE_LINE_PATTERN = '/^([A-Za-z][A-Za-z0-9()\/\-\s]{1,40}?)\s+(\d+(?:\.\d+)?)\s*([A-Za-z%\/\x{00B5}]+)?\s*$/u';

    public function parse(string $rawText): array
    {
        return [
            'record_date' => $this->findDate($rawText),
            'lab_name' => $this->findLabName($rawText),
            'test_values' => $this->findTestValues($rawText),
        ];
    }

    private function findLabName(string $text): ?string
    {
        foreach (preg_split('/\r?\n/', $text) as $line) {
            $line = trim($line);
            if ($line === '' || mb_strlen($line) < 3) {
                continue;
            }
            if (preg_match('/^\d/', $line)) {
                continue;
            }
            if ($this->looksLikeADateLine($line)) {
                continue;
            }

            return mb_substr($line, 0, 255);
        }

        return null;
    }

    /** @return array<int, array{name: string, value: float, unit: string|null}> */
    private function findTestValues(string $text): array
    {
        $results = [];

        foreach (preg_split('/\r?\n/', $text) as $line) {
            if (! preg_match(self::VALUE_LINE_PATTERN, trim($line), $match)) {
                continue;
            }

            $name = trim($match[1]);
            if (mb_strlen($name) < 3) {
                continue;
            }

            $results[] = [
                'name' => $name,
                'value' => (float) $match[2],
                'unit' => isset($match[3]) && $match[3] !== '' ? $match[3] : null,
            ];

            if (count($results) >= 20) {
                break;
            }
        }

        return $results;
    }
}
