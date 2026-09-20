<?php

namespace Tests\Unit;

use App\Services\Ocr\LabReportParser;
use PHPUnit\Framework\TestCase;

class LabReportParserTest extends TestCase
{
    public function test_parses_date_lab_name_and_test_values(): void
    {
        $text = "Apollo Diagnostics\n12/03/2026\nHemoglobin 13.8 g/dL\nTotal Cholesterol 182 mg/dL\nLDL 104 mg/dL";

        $result = (new LabReportParser)->parse($text);

        $this->assertSame('2026-03-12', $result['record_date']);
        $this->assertSame('Apollo Diagnostics', $result['lab_name']);
        $this->assertCount(3, $result['test_values']);
        $this->assertSame(['name' => 'Hemoglobin', 'value' => 13.8, 'unit' => 'g/dL'], $result['test_values'][0]);
        $this->assertSame('Total Cholesterol', $result['test_values'][1]['name']);
        $this->assertSame(182.0, $result['test_values'][1]['value']);
    }

    public function test_parses_written_out_date_format(): void
    {
        $result = (new LabReportParser)->parse("City Clinic\n12 Mar 2026\nSome notes here");

        $this->assertSame('2026-03-12', $result['record_date']);
    }

    public function test_returns_nulls_and_empty_array_for_unstructured_text(): void
    {
        $result = (new LabReportParser)->parse('just some free-form text with no structure');

        $this->assertNull($result['record_date']);
        $this->assertSame([], $result['test_values']);
    }

    public function test_ignores_lines_that_do_not_end_in_a_number(): void
    {
        $result = (new LabReportParser)->parse("Lab Name\nPatient notes without any values here");

        $this->assertSame([], $result['test_values']);
    }
}
