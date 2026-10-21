<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReportPdfSyntaxTest extends TestCase
{
    public function test_all_report_pdf_templates_compile_to_valid_php(): void
    {
        foreach (glob(resource_path('views/company/reports/pdf/*.blade.php')) as $file) {
            $compiled = app('blade.compiler')->compileString(file_get_contents($file));
            try {
                token_get_all($compiled, TOKEN_PARSE);
            } catch (\ParseError $error) {
                $this->fail(basename($file).': '.$error->getMessage());
            }
            $this->assertNotEmpty($compiled);
        }
    }
}
