<?php
namespace Tests\Feature;

use App\Models\Sale;
use Tests\TestCase;

class SalePreviousBillsPdfTest extends TestCase
{
    public function test_checkbox_value_is_persisted_on_sale_create_and_edit(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        \Illuminate\Support\Facades\Schema::create('sales', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id(); $table->timestamps();
        });
        (require database_path('migrations/2026_10_03_000001_add_show_previous_bills_to_sales_table.php'))->up();
        // Browsers submit the hidden zero first, followed by the checked checkbox.
        parse_str('show_previous_bills=0&show_previous_bills=1', $input);
        $request = \Illuminate\Http\Request::create('/', 'POST', $input);
        $sale = Sale::withoutEvents(fn () => Sale::create(['show_previous_bills'=>$request->boolean('show_previous_bills')]));
        $this->assertTrue($sale->fresh()->show_previous_bills);
        Sale::withoutEvents(fn () => $sale->update(['show_previous_bills'=>false]));
        $this->assertFalse($sale->fresh()->show_previous_bills);
        Sale::withoutEvents(fn () => $sale->update(['show_previous_bills'=>$request->boolean('show_previous_bills')]));
        $this->assertTrue($sale->fresh()->show_previous_bills);
    }
    public function test_balance_tables_appear_regardless_of_adjustment_checkbox(): void
    {
        $sale = new Sale(['voucher_no'=>'CURRENT-001', 'sale_date'=>'2026-10-03', 'net_total'=>100]);
        $sale->setRelation('customer', null)->setRelation('saleItems', collect())->setRelation('payments', collect());
        $data = ['sale'=>$sale, 'company'=>(object)['name'=>'Test'], 'saleAdvanceUsage'=>['metal_used'=>['gold'=>1, 'silver'=>2, 'other'=>3]]];
        foreach ([false, true, false] as $enabled) {
            $sale->use_silver_balance = $enabled;
            $html = view('company.sales.invoice_pdf', $data)->render();
            foreach (['Metal Balance Adjustment', 'Adjusted Fine Wt'] as $label) {
                $this->assertStringNotContainsString($label, $html);
            }
            $this->assertStringNotContainsString('Refund Paid', $html);
            $this->assertStringNotContainsString('Adv Silver(Fine)', $html);
            $this->assertStringNotContainsString('Other Fine', $html);
            $this->assertStringNotContainsString('Opening Balance', $html);


            $this->assertStringContainsString('CURRENT-001', $html);
        }
    }
    public function test_adjustment_labels_follow_actual_cash_and_metal_used(): void
    {
        $sale = new Sale(['sale_date'=>'2026-10-03', 'net_total'=>100]);
        $sale->setRelation('customer', null)->setRelation('saleItems', collect())->setRelation('payments', collect());
        foreach ([[2024,0], [0,40], [2024,40]] as [$cash,$silver]) {
            $sale->use_silver_balance = true;
            $sale->received_amount = $cash;
            $html = view('company.sales.invoice_pdf', ['sale'=>$sale,'company'=>(object)['name'=>'Test'],
                'settlementAdjustments'=>['cash'=>$cash,'silver'=>$silver,'gold'=>0,'other'=>0]])->render();
            if ($cash) $this->assertStringContainsString('Adjusted Amount', $html);
            else $this->assertStringNotContainsString('Adjusted Amount', $html);
            if ($silver) $this->assertStringContainsString('40.000 g', $html);
            else $this->assertStringNotContainsString('Adjusted Fine Wt', $html);
        }
    }
    public function test_previous_bills_and_combined_total_require_saved_opt_in(): void
    {
        $sale = new Sale(['voucher_no'=>'CURRENT-001', 'sale_date'=>'2026-10-03', 'net_total'=>100]);
        $sale->setRelation('customer', null)->setRelation('saleItems', collect())->setRelation('payments', collect());
        $data = ['sale'=>$sale, 'company'=>(object)['name'=>'Test'],
            'previousPendingSales'=>[['voucher_no'=>'PREVIOUS-001','sale_date'=>'01-10-2026','total_amount'=>50,'received_amount'=>0,'pending_amount'=>50]]];
        $html = view('company.sales.invoice_pdf', $data)->render();
        $this->assertStringContainsString('CURRENT-001', $html);
        $this->assertStringNotContainsString('Previous Pending Bills', $html);
        $this->assertStringNotContainsString('Final Grand Total', $html);
        $sale->show_previous_bills = true;
        $html = view('company.sales.invoice_pdf', $data)->render();
        $this->assertStringContainsString('PREVIOUS-001', $html);
        $this->assertStringContainsString('Final Grand Total', $html);
        $this->assertStringContainsString('150.00', $html);
        $sale->show_previous_bills = false;
        $this->assertStringNotContainsString('PREVIOUS-001', view('company.sales.invoice_pdf', $data)->render());
        $this->assertEquals(100, $sale->net_total);
    }

    public function test_metal_box_includes_previous_debt_only_when_selected(): void
    {
        $sale = new Sale(['sale_date'=>'2026-10-08', 'net_total'=>1725]);
        $line = new \App\Models\SaleItem(['fine_weight'=>22.885, 'metal_rate'=>0, 'total_amount'=>1725]);
        $line->setRelation('itemset', null)->setRelation('product', null);
        $sale->setRelation('customer', null)->setRelation('saleItems', collect([$line]))->setRelation('payments', collect());
        $data = ['sale'=>$sale, 'company'=>(object)['name'=>'Test'],
            'saleAdvanceUsage'=>['silver_debit'=>999, 'silver_credit'=>0],
            'previousPendingSales'=>[['voucher_no'=>'PREVIOUS','sale_date'=>'01-10-2026','total_amount'=>750,'received_amount'=>0,'pending_amount'=>750,'metal_debit'=>9.950]]];
        foreach ([false=> '22.885/0.000', true=> '32.835/0.000'] as $enabled=>$expected) {
            $sale->show_previous_bills = (bool)$enabled;
            $html = view('company.sales.invoice_pdf', $data)->render();
            $this->assertStringContainsString($expected, $html);
            $this->assertStringNotContainsString('999.000/0.000', $html);
        }
    }
}
