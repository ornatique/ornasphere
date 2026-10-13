<?php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Database\Schema\Blueprint;
use App\Services\ApprovalSummaryReport;

class ApprovalSummaryReportTest extends TestCase
{
    public function test_summary_includes_closed_vouchers_and_filters_without_cross_company_data(): void
    {
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Schema::create('customers', function (Blueprint $t) { $t->id(); $t->integer('company_id'); $t->string('name'); });
        Schema::create('approval_headers', function (Blueprint $t) { $t->id(); $t->integer('company_id'); $t->integer('customer_id'); $t->string('approval_no'); $t->date('approval_date'); $t->string('status'); });
        Schema::create('approval_items', function (Blueprint $t) { $t->id(); $t->integer('approval_id'); $t->string('status')->nullable(); foreach (['gross_weight','other_weight','net_weight','total_fine_weight'] as $c) $t->decimal($c, 12, 3); });
        DB::table('customers')->insert(['id'=>1,'company_id'=>1,'name'=>'Test']);
        foreach ([1,2,3] as $id) DB::table('approval_headers')->insert(['id'=>$id,'company_id'=>$id===3?2:1,'customer_id'=>1,'approval_no'=>'APP-'.$id,'approval_date'=>$id===2?'2026-10-01':'2026-10-13','status'=>'closed']);
        foreach (['returned','sold','pending',null] as $status) DB::table('approval_items')->insert(['approval_id'=>1,'status'=>$status,'gross_weight'=>10,'other_weight'=>1,'net_weight'=>9,'total_fine_weight'=>8.37]);
        $rows = ApprovalSummaryReport::query(1)->get();
        $this->assertCount(2, $rows);
        $row = $rows->first();
        $this->assertEquals(4, $row->issued_qty);
        $this->assertEquals(1, $row->returned_qty);
        $this->assertEquals(1, $row->sold_qty);
        $this->assertEquals(2, $row->pending_qty);
        $this->assertEquals(36, $row->issued_net);
        $this->assertEquals(8.37, $row->returned_fine);
        $this->assertEquals(0, $rows->last()->issued_qty);
        $this->assertCount(1, ApprovalSummaryReport::query(1,['from_date'=>'2026-10-12'])->get());
        $this->assertCount(1, ApprovalSummaryReport::query(1,['to_date'=>'2026-10-12'])->get());
        $this->assertCount(1, ApprovalSummaryReport::query(1,['voucher'=>'APP-2','customer_id'=>1])->get());
        $this->assertCount(0, ApprovalSummaryReport::query(1,['customer_id'=>2])->get());
    }
}
