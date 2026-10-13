<?php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\{Schema, DB};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Validation\ValidationException;
use App\Services\CustomerSalesReturn;

class CustomerSalesReturnTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:',config('database.connections.sqlite.database'));
        $tables = [
            'customers'=>['company_id'], 'items'=>['metal'],
            'item_sets'=>['company_id','item_id','is_sold'],
            'sales'=>['company_id','customer_id','voucher_no','net_total','received_amount','paid_amount'],
            'sale_items'=>['sale_id','itemset_id','product_id','qty','fine_weight','metal_amount','labour_amount','other_amount','total_amount'],
            'sale_returns'=>['company_id','created_by','source_type','sale_id','return_voucher_no','return_date','remarks','return_total'],
            'sale_return_items'=>['sale_return_id','sale_item_id','itemset_id','return_amount','remarks'],
            'customer_advance_ledgers'=>['company_id','customer_id','entry_date','entry_type','cash_in','cash_out','metal_type','metal_in','metal_out','reference_type','reference_id','created_by','remarks']
        ];
        foreach ($tables as $name=>$fields) Schema::create($name,function(Blueprint $t) use($fields){$t->id(); $t->timestamps(); foreach($fields as $f) $t->string($f)->nullable();});
        Schema::create('companies',function(Blueprint $t){$t->id();$t->string('name');$t->string('voucher_prefix')->nullable();});
        Schema::create('company_voucher_counters',function(Blueprint $t){$t->id();$t->unsignedBigInteger('company_id');$t->string('module');$t->unsignedBigInteger('last_number');$t->unique(['company_id','module']);});
        DB::table('companies')->insert(['id'=>1,'name'=>'Madina Jwellary']);
        DB::table('customers')->insert(['id'=>1,'company_id'=>1]);
        DB::table('customers')->insert(['id'=>2,'company_id'=>1]);
        DB::table('items')->insert(['id'=>1,'metal'=>'Silver']);
        DB::table('sales')->insert(['id'=>1,'company_id'=>1,'customer_id'=>1,'voucher_no'=>'SL1','net_total'=>200,'received_amount'=>150,'paid_amount'=>0]);
        foreach([1,2] as $id) {
            DB::table('item_sets')->insert(['id'=>$id,'company_id'=>1,'item_id'=>1,'is_sold'=>1]);
            DB::table('sale_items')->insert(['id'=>$id,'sale_id'=>1,'itemset_id'=>$id,'qty'=>1,'fine_weight'=>10,'metal_amount'=>0,'labour_amount'=>90,'other_amount'=>10,'total_amount'=>100]);
        }
        DB::table('customer_advance_ledgers')->insert(['company_id'=>1,'customer_id'=>1,'entry_type'=>'purchase_adjust_metal','metal_type'=>'silver','metal_out'=>20,'reference_type'=>'sale','reference_id'=>1]);
    }

    public function test_partial_returns_restore_stock_and_credit_only_overpayment_and_metal(): void
    {
        $service = new CustomerSalesReturn;
        $first = $service->save(1,1,[1],'2026-10-05','Wrong size',null);
        $this->assertEquals(100,$first->return_total);
        $this->assertEquals(1,$first->items()->first()->itemset_id);
        $this->assertEquals(100,DB::table('sales')->value('net_total'));
        $this->assertEquals(0,DB::table('item_sets')->where('id',1)->value('is_sold'));
        $this->assertEquals(1,DB::table('item_sets')->where('id',2)->value('is_sold'));
        $credits = DB::table('customer_advance_ledgers')->where('reference_type','sale_return');
        $this->assertEquals(50,(clone $credits)->sum('cash_in'));
        $this->assertEquals(10,(clone $credits)->sum('metal_in'));
        $service->save(1,1,[2],'2026-10-05','Wrong size',null);
        $this->assertEquals(0,DB::table('sales')->value('net_total'));
        $this->assertEquals(150,(clone $credits)->sum('cash_in'));
        $this->assertEquals(20,(clone $credits)->sum('metal_in'));
        $this->assertEquals(2,DB::table('sale_items')->count());
        try { $service->save(1,1,[1],'2026-10-05','Duplicate',null); $this->fail('Duplicate accepted'); } catch(ValidationException $e) {}
        $this->assertEquals(2,DB::table('sale_returns')->count());
    }

    public function test_wrong_customer_rejected_and_multiple_items_can_be_returned_together(): void
    {
        $service = new CustomerSalesReturn;
        try { $service->save(1,2,[1],'2026-10-05','Wrong customer',null); $this->fail('Wrong customer accepted'); } catch(ValidationException $e) {}
        $this->assertEquals(0,DB::table('sale_returns')->count());
        $return = $service->save(1,1,[1,2],'2026-10-05','Both items',null);
        $this->assertEquals(200,$return->return_total);
        $this->assertEquals(2,$return->items()->count());
        $this->assertEquals(0,DB::table('item_sets')->sum('is_sold'));
    }

    public function test_legacy_table_without_itemset_id_can_save_return(): void
    {
        Schema::table('sale_return_items', fn (Blueprint $table) => $table->dropColumn('itemset_id'));
        $return = (new CustomerSalesReturn)->save(1,1,[1,2],'2026-10-06','Legacy schema return',null);
        $this->assertEquals(200,$return->return_total);
        $this->assertEquals(2,$return->items()->count());
        $this->assertEquals(1,$return->items()->orderBy('id')->first()->saleItem->itemset->id);
        $this->assertEquals(0,DB::table('item_sets')->sum('is_sold'));
        $this->assertEquals(0,DB::table('sales')->value('net_total'));
        $credits = DB::table('customer_advance_ledgers')->where('reference_type','sale_return');
        $this->assertEquals(150,(clone $credits)->sum('cash_in'));
        $this->assertEquals(20,(clone $credits)->sum('metal_in'));
    }

    public function test_multiple_bills_remain_linked_to_one_return(): void
    {
        DB::table('sales')->insert(['id'=>2,'company_id'=>1,'customer_id'=>1,'voucher_no'=>'SL2','net_total'=>100,'received_amount'=>0,'paid_amount'=>0]);
        DB::table('sales')->where('id',1)->update(['net_total'=>100,'received_amount'=>0]);
        DB::table('sale_items')->where('id',2)->update(['sale_id'=>2]);
        DB::table('customer_advance_ledgers')->delete();
        $return = (new CustomerSalesReturn)->save(1,1,[1,2],'2026-10-05','Two bills',null);
        $this->assertNull($return->sale_id);
        $this->assertEquals(200,$return->return_total);
        $this->assertEquals(0,DB::table('sales')->sum('net_total'));
        $this->assertEquals(0,DB::table('customer_advance_ledgers')->count());
        $this->assertEquals([1,2],$return->items()->orderBy('sale_item_id')->pluck('sale_item_id')->map(fn($id)=>(int)$id)->all());
    }
}
