<?php

namespace App\Services\Vitals;

/**
 * Server-side reference ranges backing the plan's "abnormal threshold
 * detection" task. Ranges are standard adult clinical rules of thumb, not
 * per-patient/age-adjusted — that level of nuance is out of scope for the
 * MVP and isn't in the schema either (no age-banded reference table).
 */
class VitalThresholdService
{
    private const RANGES = [
        'bp_systolic' => ['normal' => [90, 120], 'borderline' => [80, 139]],
        'bp_diastolic' => ['normal' => [60, 80], 'borderline' => [50, 89]],
        'sugar_fasting' => ['normal' => [70, 100], 'borderline' => [60, 125]],
        'sugar_pp' => ['normal' => [0, 139], 'borderline' => [0, 199]],
        'sugar_random' => ['normal' => [0, 139], 'borderline' => [0, 199]],
        'hba1c' => ['normal' => [0, 5.6], 'borderline' => [0, 6.4]],
        'bmi' => ['normal' => [18.5, 24.9], 'borderline' => [16, 29.9]],
        'heart_rate' => ['normal' => [60, 100], 'borderline' => [50, 110]],
        'spo2' => ['normal' => [95, 100], 'borderline' => [90, 100]],
        'temperature' => ['normal' => [97, 99], 'borderline' => [95, 100.4]],
        // No general-purpose reference range for raw body weight without
        // height/age context — never flagged.
        'weight' => null,
    ];

    private const UNITS = [
        'bp_systolic' => 'mmHg',
        'bp_diastolic' => 'mmHg',
        'sugar_fasting' => 'mg/dL',
        'sugar_pp' => 'mg/dL',
        'sugar_random' => 'mg/dL',
        'hba1c' => '%',
        'weight' => 'kg',
        'bmi' => 'kg/m2',
        'heart_rate' => 'bpm',
        'spo2' => '%',
        'temperature' => 'F',
    ];

    public const VITAL_TYPES = [
        'bp_systolic', 'bp_diastolic', 'sugar_fasting', 'sugar_pp', 'sugar_random',
        'hba1c', 'weight', 'bmi', 'heart_rate', 'spo2', 'temperature',
    ];

    /** @return 'normal'|'borderline'|'abnormal' */
    public function classify(string $vitalType, float $value): string
    {
        $range = self::RANGES[$vitalType] ?? null;

        if ($range === null) {
            return 'normal';
        }

        [$normalMin, $normalMax] = $range['normal'];
        if ($value >= $normalMin && $value <= $normalMax) {
            return 'normal';
        }

        [$borderlineMin, $borderlineMax] = $range['borderline'];
        if ($value >= $borderlineMin && $value <= $borderlineMax) {
            return 'borderline';
        }

        return 'abnormal';
    }

    public function isAbnormal(string $vitalType, float $value): bool
    {
        return $this->classify($vitalType, $value) === 'abnormal';
    }

    public function unitFor(string $vitalType): string
    {
        return self::UNITS[$vitalType] ?? '';
    }
}
