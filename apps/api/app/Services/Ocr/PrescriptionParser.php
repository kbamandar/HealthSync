<?php

namespace App\Services\Ocr;

use App\Services\Ocr\Concerns\ParsesOcrDates;

/**
 * Heuristic, regex-based structured-field extraction for prescription OCR
 * text — same "good enough to pre-fill a form the user can still edit"
 * standard as LabReportParser. Recognizes the common Indian Rx shorthand:
 * "Tab./Cap./Syp./Inj. <name> - <dosage-code> (<timing>) x <n> days", where
 * the dosage code is a morning-afternoon-night triplet like "1-0-1".
 */
class PrescriptionParser
{
    use ParsesOcrDates;

    private const MEDICINE_LINE_PATTERN =
        '/^(?:Tab\.?|Cap\.?|Syp\.?|Inj\.?|Tablet|Capsule)\s+'
        .'([A-Za-z0-9][A-Za-z0-9\s]{1,60}?)\s*-\s*'
        .'([\d]+(?:-[\d]+){1,2})\s*'
        .'(?:\(([^)]+)\))?\s*'
        .'(?:x\s*(\d+)\s*days?)?\s*$/i';

    private const DOCTOR_LINE_PATTERN = '/\b(Dr\.?\s+[A-Za-z.\s]{2,60})/';

    private const TIME_SLOT_LABELS = ['morning', 'afternoon', 'night'];

    public function parse(string $rawText): array
    {
        return [
            'doctor_name' => $this->findDoctorName($rawText),
            'prescription_date' => $this->findDate($rawText),
            'medicines' => $this->findMedicines($rawText),
        ];
    }

    private function findDoctorName(string $text): ?string
    {
        foreach (preg_split('/\r?\n/', $text) as $line) {
            if (preg_match(self::DOCTOR_LINE_PATTERN, $line, $match)) {
                return trim(preg_replace('/\s+/', ' ', $match[1]));
            }
        }

        return null;
    }

    /** @return array<int, array{name: string, frequency: string, duration_days: int|null}> */
    private function findMedicines(string $text): array
    {
        $results = [];

        foreach (preg_split('/\r?\n/', $text) as $line) {
            if (! preg_match(self::MEDICINE_LINE_PATTERN, trim($line), $match)) {
                continue;
            }

            $name = trim($match[1]);
            if (mb_strlen($name) < 2) {
                continue;
            }

            $timing = isset($match[3]) && $match[3] !== '' ? trim($match[3]) : null;
            $durationDays = isset($match[4]) && $match[4] !== '' ? (int) $match[4] : null;

            $results[] = [
                'name' => $name,
                'frequency' => $this->describeFrequency($match[2], $timing),
                'duration_days' => $durationDays,
            ];

            if (count($results) >= 20) {
                break;
            }
        }

        return $results;
    }

    private function describeFrequency(string $dosageCode, ?string $timing): string
    {
        $slots = explode('-', $dosageCode);
        $activeSlots = array_keys(array_filter($slots, fn ($slot) => $slot !== '0'));
        $count = count($activeSlots);

        $frequency = match ($count) {
            0 => 'As needed',
            1 => 'Once daily',
            2 => 'Twice daily',
            3 => 'Three times daily',
            default => "{$count} times daily",
        };

        if ($count === 1 && isset(self::TIME_SLOT_LABELS[$activeSlots[0]])) {
            return $frequency.', '.self::TIME_SLOT_LABELS[$activeSlots[0]];
        }

        if ($timing !== null) {
            return $frequency.', '.lcfirst($timing);
        }

        return $frequency;
    }
}
