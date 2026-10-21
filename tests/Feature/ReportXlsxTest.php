<?php
namespace Tests\Feature;
use App\Services\ReportXlsx;
use Tests\TestCase;
class ReportXlsxTest extends TestCase
{
    public function test_export_is_valid_ooxml_with_literal_text_and_numeric_cells(): void
    {
        $response = ReportXlsx::download([['Name','Qty'],['=1+1',2],['A & B',3.25]], 'test.xlsx');
        $path = $response->getFile()->getPathname();
        try {
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($path));
            foreach (['[Content_Types].xml','_rels/.rels','xl/workbook.xml','xl/_rels/workbook.xml.rels','xl/worksheets/sheet1.xml'] as $part) {
                $this->assertNotFalse(simplexml_load_string($zip->getFromName($part)));
            }
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertStringContainsString('=1+1', $sheet);
            $this->assertStringNotContainsString('<f>', $sheet);
            $this->assertStringContainsString('<v>3.25</v>', $sheet);
            $zip->close();
        } finally { unlink($path); }
    }
}
