<?php

namespace Tests\Feature;

use App\Models\{ApprovalHeader, ApprovalItem, Customer, SaleReturn, SaleReturnItem};
use Tests\TestCase;

class ApprovalReturnPdfCustomerTest extends TestCase
{
    public function test_customer_details_are_resolved_from_multiple_original_approvals(): void
    {
        $customer = (new Customer)->forceFill(['id' => 10, 'company_id' => 1,
            'name' => 'Lado Creation', 'city' => 'Mumbai', 'mobile_no' => '123123133']);
        $return = $this->makeReturn();
        $items = collect([1, 2])->map(function ($id) use ($customer) {
            $approval = (new ApprovalHeader)->setRelation('customer', $customer);
            $approvalItem = (new ApprovalItem(['purity' => 93.5, 'waste_percent' => 6.5]))->setRelation('approval', $approval)
                ->setRelation('itemSet', null)->setRelation('item', null);
            return (new SaleReturnItem)->setRelation('approvalItem', $approvalItem)
                ->setRelation('saleItem', null)->setRelation('itemSet', null);
        });
        $return->setRelation('items', $items);
        $html = view('company.returns.return_pdf', ['return' => $return])->render();
        $this->assertStringContainsString('>93.500</td>', $html);
        $this->assertStringContainsString('>6.500</td>', $html);
        $this->assertStringContainsString('Waste %', $html);
        $this->assertStringNotContainsString('94%', $html);
        foreach (['Lado Creation', 'Mumbai', '123123133'] as $value) {
            $this->assertSame(1, substr_count($html, $value));
        }
        $customer->company_id = 2;
        $html = view('company.returns.return_pdf', ['return' => $return])->render();
        $this->assertStringNotContainsString('Lado Creation', $html);
    }

    public function test_header_customer_and_missing_customer_render_successfully(): void
    {
        $return = $this->makeReturn();
        $customer = (new Customer)->forceFill(['id' => 10, 'company_id' => 1,
            'name' => 'Header Customer', 'city' => 'Mumbai', 'mobile_no' => '9876543210']);
        $approval = (new ApprovalHeader)->setRelation('customer', $customer)->setRelation('items', collect());
        $return->setRelation('approval', $approval);
        $html = view('company.returns.return_pdf', ['return' => $return])->render();
        $this->assertStringContainsString('Header Customer', $html);
        $this->assertStringContainsString('9876543210', $html);
        $return->setRelation('approval', null);
        $this->assertStringContainsString('Approval Return', view('company.returns.return_pdf', ['return' => $return])->render());
    }

    private function makeReturn(): SaleReturn
    {
        return (new SaleReturn)->forceFill(['company_id' => 1, 'source_type' => 'approval',
            'return_date' => '2026-10-05', 'return_voucher_no' => 'SR-TEST'])
            ->setRelation('sale', null)->setRelation('approval', null)->setRelation('items', collect());
    }
}
