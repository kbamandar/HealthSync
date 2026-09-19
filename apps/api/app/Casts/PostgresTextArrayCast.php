<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * Eloquent's built-in 'array' cast assumes a JSON-text column; custom_tags is
 * a native Postgres text[], which PDO returns/accepts as a '{a,b,"c d"}'
 * literal, not JSON. This translates between that literal and a PHP array.
 */
class PostgresTextArrayCast implements CastsAttributes
{
    public function get($model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || $value === '{}') {
            return [];
        }

        $inner = substr($value, 1, -1);
        if ($inner === '') {
            return [];
        }

        preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"|([^,]+)/', $inner, $matches, PREG_SET_ORDER);

        return array_map(
            fn (array $m) => $m[1] !== '' ? str_replace('\\"', '"', $m[1]) : $m[2],
            $matches,
        );
    }

    public function set($model, string $key, mixed $value, array $attributes): string
    {
        $items = $value ?? [];

        $escaped = array_map(
            fn (string $item) => '"'.str_replace('"', '\\"', $item).'"',
            $items,
        );

        return '{'.implode(',', $escaped).'}';
    }
}
