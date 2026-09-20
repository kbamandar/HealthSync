<?php

namespace Tests\Support;

/**
 * Builds a minimal, real, pdftotext-parseable single-page PDF from plain
 * text lines — no xref table (poppler's recovery mode handles that fine,
 * verified against the actual pdftotext binary). Lets OCR pipeline tests
 * exercise genuine text extraction deterministically, instead of relying
 * on image OCR accuracy or mocking the extractor away entirely.
 */
class FakePdf
{
    public static function withLines(array $lines): string
    {
        $y = 270;
        $ops = [];
        foreach ($lines as $line) {
            $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
            $ops[] = "BT /F1 12 Tf 20 {$y} Td ({$escaped}) Tj ET";
            $y -= 20;
        }
        $content = implode("\n", $ops);
        $len = strlen($content);

        $pdf = "%PDF-1.1\n";
        $pdf .= "1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n";
        $pdf .= "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n";
        $pdf .= "3 0 obj<</Type/Page/Parent 2 0 R/Resources<</Font<</F1 4 0 R>>>>/MediaBox[0 0 300 300]/Contents 5 0 R>>endobj\n";
        $pdf .= "4 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\n";
        $pdf .= "5 0 obj<</Length {$len}>>\nstream\n{$content}\nendstream\nendobj\n";
        $pdf .= "trailer<</Size 6/Root 1 0 R>>\n%%EOF";

        return $pdf;
    }
}
