<?php
namespace Tests\Feature;

use Tests\TestCase;
use App\Services\ApprovalReturnRegister;
use Illuminate\Support\Facades\DB;

class ApprovalReturnRegisterTest extends TestCase
{
    public function test_register_uses_transaction_dates_and_actual_source_of_each_return_item(): void
    {
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        foreach ([
            'sales'=>'id integer primary key, company_id integer, customer_id integer, voucher_no text, sale_date text',
            'sale_items'=>'id integer primary key, sale_id integer, approval_item_id integer, gross_weight real, other_weight real, net_weight real, fine_weight real, total_amount real',
            'customers'=>'id integer primary key, company_id integer, name text',
            'approval_headers'=>'id integer primary key, company_id integer, customer_id integer, approval_no text, approval_date text',
            'approval_items'=>'id integer primary key, approval_id integer, gross_weight real, other_weight real, net_weight real, total_fine_weight real, total_amount real',
            'sale_returns'=>'id integer primary key, company_id integer, source_type text, source_id integer, return_voucher_no text, return_date text',
            'sale_return_items'=>'id integer primary key, sale_return_id integer, approval_item_id integer, return_amount real',
        ] as $table => $columns) DB::statement("CREATE TABLE $table ($columns)");
        DB::table('customers')->insert(['id'=>1,'company_id'=>1,'name'=>'Customer']);
        foreach ([1,2,3] as $id) {
            DB::table('approval_headers')->insert(['id'=>$id,'company_id'=>$id===3?2:1,'customer_id'=>1,'approval_no'=>'APP-'.$id,'approval_date'=>'2026-10-01']);
            DB::table('approval_items')->insert(['id'=>$id,'approval_id'=>$id,'gross_weight'=>10,'other_weight'=>1,'net_weight'=>9,'total_fine_weight'=>8.37,'total_amount'=>100]);
        }
        DB::table('sale_returns')->insert(['id'=>1,'company_id'=>1,'source_type'=>'approval','source_id'=>1,'return_voucher_no'=>'RET-1','return_date'=>'2026-10-13']);
        foreach ([1,2,3] as $id) DB::table('sale_return_items')->insert(['id'=>$id,'sale_return_id'=>1,'approval_item_id'=>$id,'return_amount'=>90]);
        $rows = ApprovalReturnRegister::query(1)->get();
        $this->assertCount(4, $rows);
        $returns = ApprovalReturnRegister::query(1,['from_date'=>'2026-10-12'])->get();
        $this->assertCount(2, $returns);
        $this->assertEquals(18, $returns->sum('net'));
        $this->assertEquals(180, $returns->sum('amount'));
        $this->assertEquals(['APP-1','APP-2'], $returns->pluck('approval_no')->all());
        $this->assertCount(2, ApprovalReturnRegister::query(1,['to_date'=>'2026-10-02'])->get());
        $this->assertCount(1, ApprovalReturnRegister::query(1,['entry_type'=>'Approval Return','voucher'=>'APP-2'])->get());
        $this->assertCount(0, ApprovalReturnRegister::query(1,['customer_id'=>99])->get());
        DB::table('sales')->insert(['id'=>1,'company_id'=>1,'customer_id'=>1,'voucher_no'=>'SALE-1','sale_date'=>'2026-10-14']);
        foreach ([1,2,3,null] as $approvalId) DB::table('sale_items')->insert(['sale_id'=>1,'approval_item_id'=>$approvalId,'gross_weight'=>12,'other_weight'=>2,'net_weight'=>10,'fine_weight'=>9.35,'total_amount'=>150]);
        $sales = ApprovalReturnRegister::query(1,['entry_type'=>'Sale from Approval'])->get();
        $this->assertCount(2, $sales);
        $this->assertCount(2, ApprovalReturnRegister::query([1,2], ['entry_type'=>'Sale from Approval'])->get());
        $combined = ApprovalReturnRegister::query([1,2], ['entry_type'=>'Approval'])->get();
        $this->assertCount(3, $combined);
        $this->assertEquals([1,2], $combined->pluck('company_id')->unique()->sort()->values()->all());
        $this->assertCount(2, ApprovalReturnRegister::query([1,2], ['entry_type'=>'Approval Return'])->get());
        $this->assertEquals(300, $sales->sum('amount'));
        $this->assertEquals(20, $sales->sum('net'));
        $this->assertEquals('SALE-1', $sales->first()->voucher_no);
        $this->assertCount(2, ApprovalReturnRegister::query(1,['from_date'=>'2026-10-14'])->get());
        DB::table('sale_items')->where('approval_item_id', 2)->delete();
        $this->assertCount(1, ApprovalReturnRegister::query(1,['entry_type'=>'Sale from Approval'])->get());
        DB::statement('ALTER TABLE approval_items ADD COLUMN status text');
        DB::statement('ALTER TABLE approval_items ADD COLUMN item_id integer');
        DB::statement('ALTER TABLE approval_items ADD COLUMN qr_code text');
        DB::statement('ALTER TABLE approval_items ADD COLUMN huid text');
        DB::statement('CREATE TABLE items (id integer primary key, item_name text)');
        DB::table('approval_items')->where('id', 1)->update(['status'=>'returned']);
        DB::table('approval_items')->where('id', 2)->update(['status'=>'pending', 'qr_code'=>'PENDING-2']);
        $pending = ApprovalReturnRegister::pendingItems(1)->get();
        $this->assertCount(1, $pending);
        $this->assertSame('PENDING-2', $pending->first()->qr_code);
        $this->assertEquals(9, $pending->sum('net'));
        $this->assertCount(0, ApprovalReturnRegister::pendingItems(1, ['voucher'=>'APP-1'])->get());
        $this->assertCount(0, ApprovalReturnRegister::pendingItems(1, ['from_date'=>'2026-10-12'])->get());
        $this->assertCount(2, ApprovalReturnRegister::pendingItems([1,2])->get());
        DB::table('approval_items')->where('id', 2)->update(['status'=>'sold']);
        $this->assertCount(0, ApprovalReturnRegister::pendingItems(1)->get());
    }
}
