<?php

namespace Tests\Feature;

use App\Services\Ocr\OcrExtractor;
use Tests\Support\FakePdf;
use Tests\TestCase;

class OcrExtractionTest extends TestCase
{
    public function test_extracts_real_text_and_structured_fields_from_a_pdf(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ocr_test_').'.pdf';
        file_put_contents($path, FakePdf::withLines([
            'Apollo Diagnostics',
            '12/03/2026',
            'Hemoglobin 13.8 g/dL',
            'Total Cholesterol 182 mg/dL',
        ]));

        $result = app(OcrExtractor::class)->extract($path, 'application/pdf');
        unlink($path);

        $this->assertNotNull($result['raw_text']);
        $this->assertStringContainsString('Apollo Diagnostics', $result['raw_text']);
        $this->assertSame('2026-03-12', $result['fields']['record_date']);
        $this->assertSame('Apollo Diagnostics', $result['fields']['lab_name']);
        $this->assertCount(2, $result['fields']['test_values']);
    }

    public function test_degrades_gracefully_for_a_corrupt_file_instead_of_throwing(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ocr_test_').'.pdf';
        file_put_contents($path, 'this is not a real pdf');

        $result = app(OcrExtractor::class)->extract($path, 'application/pdf');
        unlink($path);

        $this->assertNull($result['raw_text']);
        $this->assertSame(['record_date' => null, 'lab_name' => null, 'test_values' => []], $result['fields']);
    }

    public function test_returns_empty_result_for_an_unsupported_mime_type(): void
    {
        $result = app(OcrExtractor::class)->extract('/tmp/does-not-matter', 'application/octet-stream');

        $this->assertNull($result['raw_text']);
    }

    public function test_extracts_prescription_fields_when_category_is_prescription(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ocr_test_').'.pdf';
        file_put_contents($path, FakePdf::withLines([
            'Dr. Ramesh Sharma, MD',
            '22/11/2026',
            'Tab. Metformin 500mg - 1-0-1 (after food) x 30 days',
        ]));

        $result = app(OcrExtractor::class)->extract($path, 'application/pdf', 'prescription');
        unlink($path);

        $this->assertSame('Dr. Ramesh Sharma', $result['fields']['doctor_name']);
        $this->assertSame('2026-11-22', $result['fields']['prescription_date']);
        $this->assertCount(1, $result['fields']['medicines']);
        $this->assertSame('Metformin 500mg', $result['fields']['medicines'][0]['name']);
    }

    public function test_degrades_gracefully_for_a_corrupt_prescription_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ocr_test_').'.pdf';
        file_put_contents($path, 'this is not a real pdf');

        $result = app(OcrExtractor::class)->extract($path, 'application/pdf', 'prescription');
        unlink($path);

        $this->assertNull($result['raw_text']);
        $this->assertSame(['doctor_name' => null, 'prescription_date' => null, 'medicines' => []], $result['fields']);
    }
}
