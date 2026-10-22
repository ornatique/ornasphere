<?php

namespace Tests\Feature;

use App\Models\JobworkIssue;
use App\Services\JobworkRawStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class JobworkRawStockTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            'companies'=>'id integer primary key',
            'items'=>'id integer primary key, company_id integer, item_name text, jobwork_item_type text',
            'customer_advance_vouchers'=>'id integer primary key, company_id integer, customer_id integer, voucher_date text',
            'customer_advance_voucher_items'=>'id integer primary key, voucher_id integer, product_id integer, net_weight real, gross_weight real, other_weight real, fine_weight real',
            'jobwork_issues'=>'id integer primary key, company_id integer, jobwork_date text',
            'jobwork_issue_items'=>'id integer primary key, jobwork_issue_id integer, item_id integer, net_wt real',
            'jobwork_receives'=>'id integer primary key, company_id integer, jobwork_issue_id integer',
            'jobwork_receive_items'=>'id integer primary key, jobwork_receive_id integer, item_id integer, receive_net_wt real',
        ] as $table=>$columns) DB::statement("CREATE TABLE $table ($columns)");
        (require database_path('migrations/2026_10_10_120000_create_jobwork_raw_stock_allocations.php'))->up();
        (require database_path('migrations/2026_10_13_160000_allow_raw_stock_debt.php'))->up();
        DB::table('companies')->insert(['id'=>1]);
        DB::table('items')->insert(['id'=>1,'company_id'=>1,'item_name'=>'Patru','jobwork_item_type'=>'raw']);
        DB::table('customer_advance_vouchers')->insert(['id'=>1,'company_id'=>1,'customer_id'=>9,'voucher_date'=>'2026-10-01']);
        DB::table('customer_advance_voucher_items')->insert(['id'=>1,'voucher_id'=>1,'product_id'=>1,'net_weight'=>500,'gross_weight'=>500,'other_weight'=>0,'fine_weight'=>467.5]);
        DB::table('jobwork_issues')->insert(['id'=>1,'company_id'=>1,'jobwork_date'=>'2026-10-10']);
        DB::table('jobwork_issue_items')->insert(['id'=>1,'jobwork_issue_id'=>1,'item_id'=>1,'net_wt'=>100]);
    }

    public function test_additional_material_after_receipt_preserves_allocations_and_receipts(): void
    {
        $this->sync();
        $this->receive(50);
        $service = new JobworkRawStock;
        $data = ['jobwork_date'=>'2026-10-10','job_worker_id'=>'','production_step_id'=>'',
            'items'=>[['id'=>1,'item_id'=>1,'net_wt'=>100],['item_id'=>1,'net_wt'=>25]]];
        $ids = $service->guardIssueUpdate(JobworkIssue::find(1), $data);
        $allocation = DB::table('jobwork_raw_stock_allocations')->first()->id;
        DB::table('jobwork_issue_items')->insert(['id'=>2,'jobwork_issue_id'=>1,'item_id'=>1,'net_wt'=>25]);
        $service->syncIssue(JobworkIssue::find(1), true, $ids);
        $this->assertEquals(425, $service->available(1)[1]);
        $this->assertTrue(DB::table('jobwork_raw_stock_allocations')->where('id',$allocation)->exists());
        $this->assertSame(1, DB::table('jobwork_receive_items')->count());
        $data['items'][0]['net_wt'] = 99;
        $this->expectException(ValidationException::class);
        $service->guardIssueUpdate(JobworkIssue::find(1), $data);
    }

    private function sync(int $id = 1): void
    {
        DB::transaction(function () use ($id) {
            $service = new JobworkRawStock;
            $service->lock(1);
            $service->syncIssue(JobworkIssue::findOrFail($id));
        });
    }

    private function receive(float $weight): void
    {
        DB::table('jobwork_receives')->insert(['id'=>1,'company_id'=>1,'jobwork_issue_id'=>1]);
        DB::table('jobwork_receive_items')->insert(['id'=>1,'jobwork_receive_id'=>1,'item_id'=>1,'receive_net_wt'=>$weight]);
    }

    public function test_receipt_issue_partial_return_and_resave(): void
    {
        $s = new JobworkRawStock;
        $this->assertEquals(500, $s->available(1)[1]);
        $this->sync();
        $this->sync();
        $this->assertEquals(400, $s->available(1)[1]);
        $this->receive(50);
        $s->validateReturns(1);
        $this->assertEquals(450, $s->available(1)[1]);
        $this->assertEquals(9, $s->receipts(1)->first()->customer_id);
        DB::table('jobwork_receive_items')->where('id',1)->update(['receive_net_wt'=>60]);
        $s->validateReturns(1);
        $this->assertEquals(460, $s->available(1)[1]);
        DB::table('jobwork_receive_items')->delete();
        $this->assertEquals(400, $s->available(1)[1]);
        DB::table('jobwork_issues')->where('id',1)->delete();
        $this->assertEquals(500, $s->available(1)[1]);
    }

    public function test_over_issue_rolls_back_allocations(): void
    {
        $this->sync();
        DB::table('jobwork_issue_items')->where('id',1)->update(['net_wt'=>501]);
        try { $this->sync(); $this->fail('Expected insufficient stock'); }
        catch (ValidationException $e) { $this->assertStringContainsString('only 500.000 g', $e->getMessage()); }
        $this->assertEquals(400, (new JobworkRawStock)->available(1)[1]);
    }

    public function test_temporary_shortage_saves_without_inventing_stock_and_can_be_reconciled(): void
    {
        $service = new JobworkRawStock;
        DB::table('customer_advance_voucher_items')->update(['net_weight' => 0]);
        $warnings = $service->syncIssue(JobworkIssue::findOrFail(1), true);
        $this->assertEquals(100, $warnings[0]['shortage_net_wt']);
        $this->assertTrue($warnings[0]['stock_deducted']);
        $this->assertEquals(1, DB::table('jobwork_raw_stock_allocations')->count());
        $this->assertEquals(-100, $service->available(1)[1]);
        $this->assertEquals(-100, $service->debtReport(1)->first()->net_weight);
        DB::table('customer_advance_voucher_items')->update(['net_weight' => 50]);
        $warnings = $service->syncIssue(JobworkIssue::findOrFail(1), true);
        $this->assertEquals(50, $warnings[0]['shortage_net_wt']);
        $this->assertEquals(-50, $service->available(1)[1]);
        DB::table('customer_advance_voucher_items')->update(['net_weight' => 500]);
        $this->assertSame([], $service->syncIssue(JobworkIssue::findOrFail(1), true));
        $this->assertEquals(400, $service->available(1)[1]);
        DB::table('customer_advance_voucher_items')->update(['net_weight' => 0]);
        $service->syncIssue(JobworkIssue::findOrFail(1), true);
        $this->receive(50);
        $this->assertEquals(-50, $service->available(1)[1]);
        DB::table('customer_advance_voucher_items')->update(['net_weight' => 200]);
        $this->assertEquals(150, $service->available(1)[1]);
    }

    public function test_edit_replaces_issue_and_finished_items_do_not_consume_raw_stock(): void
    {
        $this->sync();
        DB::table('jobwork_issue_items')->where('id',1)->update(['net_wt'=>150]);
        $this->sync();
        $this->assertEquals(350, (new JobworkRawStock)->available(1)[1]);
        DB::table('items')->insert(['id'=>2,'company_id'=>1,'item_name'=>'Finished chain','jobwork_item_type'=>'finish']);
        DB::table('jobwork_issue_items')->where('id',1)->update(['item_id'=>2]);
        $this->sync();
        $this->assertEquals(500, (new JobworkRawStock)->available(1)[1]);
    }

    public function test_return_cannot_exceed_issued_weight(): void
    {
        $this->sync(); $this->receive(101);
        $this->expectException(ValidationException::class);
        (new JobworkRawStock)->validateReturns(1, 1);
    }

    public function test_api_can_save_extra_return_without_creating_extra_stock(): void
    {
        $this->sync();
        DB::transaction(function () {
            $this->receive(120);
            (new JobworkRawStock)->validateReturns(1, 1, false);
        });
        $this->assertEquals(120, DB::table('jobwork_receive_items')->value('receive_net_wt'));
        $this->assertEquals(500, (new JobworkRawStock)->available(1)[1]);
    }

    public function test_stock_reissued_after_return_prevents_return_reduction(): void
    {
        $this->sync(); $this->receive(50);
        DB::table('jobwork_issues')->insert(['id'=>2,'company_id'=>1,'jobwork_date'=>'2026-10-10']);
        DB::table('jobwork_issue_items')->insert(['id'=>2,'jobwork_issue_id'=>2,'item_id'=>1,'net_wt'=>450]);
        $this->sync(2);
        $this->assertEquals(0, (new JobworkRawStock)->available(1)[1]);
        DB::table('jobwork_receive_items')->delete();
        $this->expectException(ValidationException::class);
        (new JobworkRawStock)->validateReturns(1, 1);
    }

    public function test_unrelated_invalid_raw_return_does_not_block_current_issue(): void
    {
        $this->sync(); $this->receive(20);
        DB::table('items')->insert(['id'=>2,'company_id'=>1,'item_name'=>'Ring Dokiya','jobwork_item_type'=>'raw']);
        DB::table('jobwork_issues')->insert(['id'=>2,'company_id'=>1,'jobwork_date'=>'2026-10-10']);
        DB::table('jobwork_issue_items')->insert(['id'=>2,'jobwork_issue_id'=>2,'item_id'=>2,'net_wt'=>10]);
        DB::table('jobwork_receives')->insert(['id'=>2,'company_id'=>1,'jobwork_issue_id'=>2]);
        DB::table('jobwork_receive_items')->insert(['id'=>2,'jobwork_receive_id'=>2,'item_id'=>2,'receive_net_wt'=>20]);
        $service = new JobworkRawStock;
        $service->validateReturns(1, 1);
        $this->assertEquals(420, $service->available(1)[1]);
        $this->expectException(ValidationException::class);
        $service->validateReturns(1, 2);
    }

    public function test_company_isolation_and_report_weight(): void
    {
        $this->sync();
        $this->assertSame([], (new JobworkRawStock)->available(2));
        $query = DB::table('customer_advance_voucher_items as cavi');
        (new JobworkRawStock)->joinReport($query,1);
        $row = $query->selectRaw(JobworkRawStock::reportWeight('net_weight').' as net, '.JobworkRawStock::reportWeight('fine_weight').' as fine')->first();
        $this->assertEquals(400, $row->net);
        $this->assertEquals(374, $row->fine);
    }

    public function test_posted_return_protects_issue_from_destructive_edit(): void
    {
        $this->sync(); $this->receive(50);
        $this->expectException(ValidationException::class);
        (new JobworkRawStock)->guardIssueEdit(JobworkIssue::findOrFail(1));
    }

    public function test_web_and_api_stock_reports_show_remaining_receipt_weight(): void
    {
        DB::statement('ALTER TABLE companies ADD COLUMN name text');
        DB::statement('CREATE TABLE category_people (id integer primary key, company_id integer, category_name text)');
        DB::statement('CREATE TABLE customer_advance_ledgers (id integer primary key, customer_id integer, company_id integer, cash_in real, cash_out real, metal_type text, metal_in real, metal_out real)');
        DB::statement('CREATE TABLE customers (id integer primary key, company_id integer, name text)');
        DB::statement('ALTER TABLE customers ADD COLUMN category_person_id integer');
        DB::statement('ALTER TABLE customer_advance_vouchers ADD COLUMN voucher_no text');
        DB::table('customers')->insert(['id'=>9,'company_id'=>1,'name'=>'Source party']);
        DB::statement('CREATE TABLE item_sets (id integer primary key, item_id integer, company_id integer, is_final integer, is_sold integer, supplier_person text, gross_weight real, other real, net_weight real, sale_labour_amount real, sale_other real, created_at text, image_path text, qr_code text, barcode text, HUID text, size text)');
        DB::statement('CREATE TABLE casting_sorting_items (id integer primary key, item_id integer, company_id integer, stock_type text, quantity real, weight real, created_at text)');
        foreach (['item_name text','labour_amount real','other_amount real','created_at text'] as $column) {
            DB::statement('ALTER TABLE customer_advance_voucher_items ADD COLUMN '.$column);
        }
        $this->sync(); $this->receive(50);
        $request = new \Illuminate\Http\Request(['customer_id'=>9]);
        $company = \App\Models\Company::findOrFail(1);
        $company->slug = 'main';
        $this->actingAs((new \App\Models\User)->forceFill(['id'=>1, 'company_id'=>1]));
        request()->setUserResolver(fn () => auth()->user());
        foreach ([\App\Http\Controllers\Company\ReportController::class, \App\Http\Controllers\Api\ReportApiController::class] as $class) {
            $controller = app($class);
            $method = new \ReflectionMethod($class, 'stockPositionBaseQuery');
            $query = str_contains($class, '\\Api\\') ? $method->invoke($controller, $request, 1) : $method->invoke($controller, $company, $request);
            $row = $query->first();
            $this->assertEquals(450, $row->net_weight);
            $this->assertEquals(450, $row->gross_weight);
            $this->assertEquals(9, $row->customer_id);
        }

        foreach ([1=>'photo.webp',2=>null,3=>''] as $id=>$path) {
            DB::table('item_sets')->insert(['id'=>$id,'company_id'=>1,'item_id'=>1,'supplier_person'=>'Source party','is_final'=>1,'is_sold'=>0,'image_path'=>$path,'net_weight'=>10,'gross_weight'=>10]);
        }
        $controller = app(\App\Http\Controllers\Company\ReportController::class);
        $base = new \ReflectionMethod($controller, 'stockPositionBaseQuery');
        $label = $base->invoke($controller, $company, $request)->where('stock_type','finished_item')->first();
        $this->assertEquals(3, $label->qty_pcs);
        $this->assertEquals(1, $label->with_image);
        DB::table('casting_sorting_items')->insert(['id'=>1,'company_id'=>1,'item_id'=>1,'stock_type'=>'raw_material','quantity'=>2,'weight'=>25]);
        foreach (['raw_material'=>2, 'finished_item'=>3] as $type=>$quantity) {
            $filtered = new \Illuminate\Http\Request(['stock_type'=>$type]);
            $result = $base->invoke($controller, $company, $filtered)->get();
            $this->assertCount(1, $result);
            $this->assertSame($type, $result->first()->stock_type);
            $this->assertEquals($quantity, $result->sum('qty_pcs'));
            $filtered->setUserResolver(fn () => (object)['company_id'=>1]);
            $api = app(\App\Http\Controllers\Api\ReportApiController::class)->stockPosition($filtered)->getData(true);
            $this->assertCount(1, $api['data']);
            $this->assertSame($type, $api['data'][0]['stock_type']);
            $this->assertEquals($quantity, $api['summary']['qty_pcs']);
            $this->assertEquals($type === 'finished_item' ? 1 : 0, $api['summary']['with_image']);
        }
        $detail = new \ReflectionMethod($controller, 'stockPositionDetailRows');
        foreach (['label_code text','huid text','remarks text'] as $column) {
            DB::statement('ALTER TABLE customer_advance_voucher_items ADD COLUMN '.$column);
        }
        DB::statement('ALTER TABLE casting_sorting_items ADD COLUMN vacuum_voucher_id integer');
        DB::statement('ALTER TABLE casting_sorting_items ADD COLUMN sorted_at text');
        DB::statement('CREATE TABLE vacuum_vouchers (id integer primary key, voucher_no text)');
        $allRequest = new \Illuminate\Http\Request(['item_id'=>1,'stock_type'=>'all']);
        $allRows = $detail->invoke($controller, $company, $allRequest);
        $this->assertCount(5, $allRows);
        $this->assertEquals(505, $allRows->sum('net_weight'));
        $this->assertCount(3, $allRows->pluck('stock_type_name')->unique());
        $allRequest->merge(['customer_id'=>9]);
        $this->assertCount(4, $detail->invoke($controller, $company, $allRequest));
        foreach (['all'=>3,'with'=>1,'without'=>2] as $filter=>$expected) {
            $request->merge(['item_id'=>1,'stock_type'=>'finished_item','image_status'=>$filter]);
            $rows = $detail->invoke($controller, $company, $request);
            $this->assertCount($expected, $rows);
            $this->assertSame(['with_image'=>1,'without_image'=>2], $request->attributes->get('stock_image_counts'));
        }
    }

    public function test_api_stock_endpoint_returns_grams_for_authenticated_company(): void
    {
        $this->sync();
        $request = new \Illuminate\Http\Request;
        $user = new \App\Models\User;
        $user->forceFill(['company_id'=>1]);
        $user->setRelation('company', \App\Models\Company::findOrFail(1));
        $request->setUserResolver(fn() => $user);
        $response = app(\App\Http\Controllers\Api\JobworkIssueApiController::class)->rawStock($request)->getData(true);
        $this->assertSame(1, $response['data'][0]['item_id']);
        $this->assertEquals(400, $response['data'][0]['available_net_wt']);
        $this->assertSame('g', $response['data'][0]['unit']);
    }

    public function test_historical_backfill_dry_run_and_repeat_are_safe(): void
    {
        $this->receive(50);
        $this->artisan('jobwork:backfill-raw-stock', ['company_id'=>1, '--dry-run'=>true])->assertSuccessful();
        $this->assertEquals(0, DB::table('jobwork_raw_stock_allocations')->count());
        $this->artisan('jobwork:backfill-raw-stock', ['company_id'=>1])->assertSuccessful();
        $this->artisan('jobwork:backfill-raw-stock', ['company_id'=>1])->assertSuccessful();
        $this->assertEquals(1, DB::table('jobwork_raw_stock_allocations')->count());
        $this->assertEquals(450, (new JobworkRawStock)->available(1)[1]);
    }
}
