<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defends against stray HTML/script markup in free-text fields (notes,
 * titles, doctor names, etc.) reaching storage or downstream renderers
 * (email templates, PDFs). Trims and strips tags on every string input;
 * validation rules still own type/length/enum checks.
 */
class SanitizeInput
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->merge($this->clean($request->all()));

        return $next($request);
    }

    private function clean(array $input): array
    {
        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $input[$key] = $this->clean($value);
            } elseif (is_string($value)) {
                $input[$key] = trim(strip_tags($value));
            }
        }

        return $input;
    }
}
