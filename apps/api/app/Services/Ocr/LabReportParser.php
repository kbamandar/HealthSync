<?php

namespace App\Services\Ocr;

use Carbon\Carbon;
use Exception;

/**
 * Heuristic, regex-based structured-field extraction from raw OCR/PDF text.
 * There is no ML model here — just pattern matching good enough to pre-fill
 * a form the user can still edit (per the plan's own fallback: "always
 * allow manual entry").
 */
class LabReportParser
{
    private const DATE_PATTERNS = [
        '/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})\b/',
        '/\b(\d{1,2}\s+[A-Za-z]{3,9}\s+\d{2,4})\b/',
        '/\b([A-Za-z]{3,9}\s+\d{1,2},?\s+\d{2,4})\b/',
    ];

    private const VALUE_LINE_PATTERN = '/^([A-Za-z][A-Za-z0-9()\/\-\s]{1,40}?)\s+(\d+(?:\.\d+)?)\s*([A-Za-z%\/\x{00B5}]+)?\s*$/u';

    public function parse(string $rawText): array
    {
        return [
            'record_date' => $this->findDate($rawText),
            'lab_name' => $this->findLabName($rawText),
            'test_values' => $this->findTestValues($rawText),
        ];
    }

    private function findDate(string $text): ?string
    {
        // Numeric d/m/y is parsed explicitly as day-first (Indian convention
        // — this is an Indian health app) rather than via Carbon::parse's
        // ambiguous, locale-dependent m/d/y guess for slash dates.
        if (preg_match(self::DATE_PATTERNS[0], $text, $match)) {
            try {
                $parsed = Carbon::createFromFormat('d/m/Y', sprintf('%02d/%02d/%04d',
                    $match[1], $match[2], strlen($match[3]) === 2 ? 2000 + (int) $match[3] : (int) $match[3]));
                if ($this->isPlausibleDate($parsed)) {
                    return $parsed->toDateString();
                }
            } catch (Exception) {
                // fall through to the other patterns
            }
        }

        foreach ([self::DATE_PATTERNS[1], self::DATE_PATTERNS[2]] as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                try {
                    $parsed = Carbon::parse($match[0]);
                    if ($this->isPlausibleDate($parsed)) {
                        return $parsed->toDateString();
                    }
                } catch (Exception) {
                    continue;
                }
            }
        }

        return null;
    }

    private function isPlausibleDate(Carbon $date): bool
    {
        return $date->year >= 2000 && $date->year <= (int) date('Y') + 1;
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

    private function looksLikeADateLine(string $line): bool
    {
        foreach (self::DATE_PATTERNS as $pattern) {
            if (preg_match($pattern, $line) && mb_strlen(trim($line)) < 20) {
                return true;
            }
        }

        return false;
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
