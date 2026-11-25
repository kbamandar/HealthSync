<?php

namespace App\Services\Ocr\Concerns;

use Carbon\Carbon;
use Exception;

/** Shared date-finding heuristic for heuristic OCR field parsers. */
trait ParsesOcrDates
{
    private const DATE_PATTERNS = [
        '/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})\b/',
        '/\b(\d{1,2}\s+[A-Za-z]{3,9}\s+\d{2,4})\b/',
        '/\b([A-Za-z]{3,9}\s+\d{1,2},?\s+\d{2,4})\b/',
    ];

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

    private function looksLikeADateLine(string $line): bool
    {
        foreach (self::DATE_PATTERNS as $pattern) {
            if (preg_match($pattern, $line) && mb_strlen(trim($line)) < 20) {
                return true;
            }
        }

        return false;
    }
}
