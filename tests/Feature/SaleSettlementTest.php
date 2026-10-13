<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Services\SaleSettlement;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SaleSettlementTest extends TestCase
{
    public function test_received_cash_is_applied_only_when_checkbox_is_checked(): void
    {
        $sale = new Sale(['net_total'=>1800,'received_amount'=>500,'paid_amount'=>0,'use_silver_balance'=>false]);
        $this->assertEquals(1800, $sale->net_total - $sale->appliedReceivedAmount());
        $sale->use_silver_balance = true;
        $this->assertEquals(1300, $sale->net_total - $sale->appliedReceivedAmount());
        $sale->use_silver_balance = false;
        $this->assertEquals(500, $sale->received_amount);
        $this->assertEquals(1800, $sale->net_total - $sale->appliedReceivedAmount());
    }

    public function test_browser_checkbox_persists_checked_and_unchecked_values(): void
    {
        DB::statement('CREATE TABLE sales (id integer primary key autoincrement, use_silver_balance integer, created_at text, updated_at text)');
        $sale = new Sale;
        foreach ([''=>false, 'use_silver_balance=0&use_silver_balance=1'=>true, 'use_silver_balance=0'=>false] as $body=>$expected) {
            parse_str($body, $input);
            $request = \Illuminate\Http\Request::create('/', 'POST', $input);
            $sale->use_silver_balance = $request->boolean('use_silver_balance');
            $sale->save();
            $this->assertSame($expected, $sale->fresh()->use_silver_balance);
        }
    }

    public function test_7024_bill_with_5000_received_needs_real_credit_to_clear_2024(): void
    {
        $service = new SaleSettlement;
        $rows = [['metal'=>'silver','fine_weight'=>1756,'metal_rate'=>0,'labour_amount'=>7024]];
        $withoutAdvance = $service->calculate($rows, ['cash'=>0], true, 5000);
        $this->assertEquals(0, $withoutAdvance['cash_used']);
        $this->assertEquals(2024, $withoutAdvance['net_cash'] - 5000);
        $withAdvance = $service->calculate($rows, ['cash'=>2024], true, 5000);
        $this->assertEquals(2024, $withAdvance['cash_used']);
        $this->assertEquals(0, $withAdvance['net_cash'] - 5000);
    }

    public function test_cash_and_metal_are_settled_independently(): void
    {
        $service = new SaleSettlement;
        $row = ['metal'=>'Silver','fine_weight'=>100,'metal_rate'=>200,'metal_amount'=>20000,'labour_amount'=>1000,'other_amount'=>50];
        $cashOnly = $service->calculate([$row], ['cash'=>50000], true);
        $this->assertEquals(21050, $cashOnly['cash_used']);
        $this->assertEquals(0, $cashOnly['metal_out']['silver']);
        $this->assertEquals(0, $cashOnly['net_cash']);
        $mixed = $service->calculate([$row], ['cash'=>5000,'silver'=>40], true);
        $this->assertEquals(13050, $mixed['gross_cash']);
        $this->assertEquals(8050, $mixed['net_cash']);
        $this->assertEquals(40, $mixed['metal_out']['silver']);
        $row['metal_rate'] = 0; // Ignore stale metal amount when rate was cleared.
        $unpriced = $service->calculate([$row], ['cash'=>50000,'silver'=>40], true);
        $this->assertEquals(1050, $unpriced['cash_used']);
        $this->assertEquals(100, $unpriced['metal_out']['silver']);
        $this->assertEquals(40, $unpriced['metal_adjusted']['silver']);
        $disabled = $service->calculate([$row], ['cash'=>50000], false);
        $this->assertEquals(0, $disabled['cash_used']);
        $this->assertEquals(100, $disabled['metal_out']['silver']);
        $this->assertEquals(0, $disabled['metal_adjusted']['silver']);
        $paid = $service->calculate([$row], ['cash'=>50000], true, 1050);
        $this->assertEquals(0, $paid['cash_used']);
    }

    public function test_repeated_save_does_not_consume_advance_twice(): void
    {
        foreach ([
            'customers'=>'id integer primary key, company_id integer',
            'sales'=>'id integer primary key, company_id integer, customer_id integer, sale_date text, use_silver_balance integer, received_amount real, paid_amount real, net_total real, created_at text, updated_at text',
            'items'=>'id integer primary key, metal text',
            'item_sets'=>'id integer primary key, item_id integer',
            'sale_items'=>'id integer primary key, sale_id integer, itemset_id integer, product_id integer, fine_weight real, metal_rate real, metal_amount real, labour_amount real, other_amount real, total_amount real, created_at text, updated_at text',
            'customer_advance_ledgers'=>'id integer primary key autoincrement, company_id integer, customer_id integer, entry_date text, entry_type text, reference_type text, reference_id integer, cash_in real default 0, cash_out real default 0, metal_type text, metal_in real default 0, metal_out real default 0, rate real, remarks text, created_by integer, created_at text, updated_at text',
        ] as $table=>$columns) DB::statement("CREATE TABLE $table ($columns)");
        DB::table('customers')->insert(['id'=>1,'company_id'=>1]);
        DB::table('items')->insert(['id'=>1,'metal'=>'Silver']);
        DB::table('sales')->insert(['id'=>1,'company_id'=>1,'customer_id'=>1,'sale_date'=>'2026-10-08','use_silver_balance'=>1,'received_amount'=>500,'paid_amount'=>0,'net_total'=>0]);
        DB::table('sale_items')->insert(['id'=>1,'sale_id'=>1,'product_id'=>1,'fine_weight'=>100,'metal_rate'=>0,'metal_amount'=>20000,'labour_amount'=>1000,'other_amount'=>0]);
        DB::table('customer_advance_ledgers')->insert(['company_id'=>1,'customer_id'=>1,'entry_date'=>'2026-10-01','cash_in'=>50000,'metal_type'=>'silver','metal_in'=>40]);
        $service = new SaleSettlement;
        $sale = Sale::findOrFail(1);
        $service->sync($sale, null);
        $service->sync($sale, null);
        $this->assertEquals(49500, DB::table('customer_advance_ledgers')->sum(DB::raw('cash_in-cash_out')));
        $this->assertEquals(-60, DB::table('customer_advance_ledgers')->sum(DB::raw('metal_in-metal_out')));
        $this->assertEquals(500, $sale->fresh()->net_total);
        $this->assertEquals(1000, DB::table('sale_items')->value('total_amount'));
        $this->assertEquals(3, DB::table('customer_advance_ledgers')->count());
        $this->assertEquals(40, $service->adjustmentSummary($sale)['silver']);
        $this->assertEquals(500, $service->adjustmentSummary($sale)['cash']);
        // A cash receipt must not label an unpriced metal debt as adjusted credit.
        DB::table('customer_advance_ledgers')->whereNull('reference_type')->update(['metal_in'=>0]);
        $sale->received_amount = 500;
        $sale->save();
        $service->sync($sale, null);
        $this->assertEquals(0, $service->adjustmentSummary($sale)['silver']);
        $this->assertEquals(-100, DB::table('customer_advance_ledgers')->sum(DB::raw('metal_in-metal_out')));
        $sale->use_silver_balance = false;
        $sale->save();
        $service->sync($sale, null);
        $this->assertEquals(50000, DB::table('customer_advance_ledgers')->sum(DB::raw('cash_in-cash_out')));
        $this->assertEquals(1000, $sale->fresh()->net_total);
        $sale->use_silver_balance = true;
        $sale->received_amount = 0;
        $sale->save();
        $service->sync($sale, null);
        $this->assertFalse($sale->fresh()->use_silver_balance);
        $this->assertEquals(0, $service->adjustmentSummary($sale)['cash']);
        // Existing fine credit enables adjustment without a new cash receipt.
        DB::table('customer_advance_ledgers')->whereNull('reference_type')->update(['metal_in'=>1000]);
        $sale->use_silver_balance = true;
        $sale->save();
        $service->sync($sale, null);
        $service->sync($sale, null);
        $this->assertTrue($sale->fresh()->use_silver_balance);
        $this->assertEquals(100, $service->adjustmentSummary($sale)['silver']);
        $this->assertEquals(900, DB::table('customer_advance_ledgers')->sum(DB::raw('metal_in-metal_out')));
        // Availability must not automatically check the user's unchecked choice.
        $sale->use_silver_balance = false;
        $sale->save();
        $service->sync($sale, null);
        $this->assertFalse($sale->fresh()->use_silver_balance);
        $this->assertEquals(0, $service->adjustmentSummary($sale)['silver']);
    }
}
