<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Support\Facades\{DB, Schema, Crypt};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use App\Http\Controllers\Company\SaleReturnController;
class ApprovalReturnEditTest extends TestCase
{
    public function test_removing_a_row_restores_approval_and_recalculates_total(): void
    {
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        foreach (['companies'=>['slug'], 'sale_returns'=>['company_id','source_type','return_date','remarks','return_total'], 'sale_return_items'=>['sale_return_id','approval_item_id','itemset_id','return_amount'], 'approval_headers'=>['company_id','status'], 'approval_items'=>['approval_id','status','itemset_id','item_id']] as $name=>$fields) {
            Schema::create($name, function(Blueprint $table) use($fields) { $table->id(); $table->timestamps(); foreach($fields as $field) $table->string($field)->nullable(); });
        }
        DB::table('companies')->insert(['id'=>1,'slug'=>'test']);
        DB::table('sale_returns')->insert(['id'=>1,'company_id'=>1,'source_type'=>'approval','return_total'=>300]);
        DB::table('approval_headers')->insert(['id'=>1,'company_id'=>1,'status'=>'closed']);
        foreach([1,2] as $id) {
            DB::table('approval_items')->insert(['id'=>$id,'approval_id'=>1,'status'=>'returned']);
            DB::table('sale_return_items')->insert(['id'=>$id,'sale_return_id'=>1,'approval_item_id'=>$id,'return_amount'=>$id*100]);
        }
        $request = Request::create('/', 'PUT', ['return_date'=>'2026-10-05','remove_items'=>[1],'removal_reason'=>'Selected by mistake']);
        (new SaleReturnController)->update($request, 'test', Crypt::encryptString('1'));
        $this->assertSame('pending', DB::table('approval_items')->where('id',1)->value('status'));
        $this->assertSame('returned', DB::table('approval_items')->where('id',2)->value('status'));
        $this->assertEquals(200, DB::table('sale_returns')->value('return_total'));
        $this->assertSame(1, DB::table('sale_return_items')->count());
        $this->assertStringContainsString('Selected by mistake', DB::table('sale_returns')->value('remarks'));
        $this->assertSame('partial', DB::table('approval_headers')->value('status'));
        Schema::table('sale_returns', function (Blueprint $table) { $table->integer('source_id')->nullable(); });
        Schema::table('approval_items', function (Blueprint $table) { $table->decimal('total_amount', 12, 2)->default(200); $table->decimal('other_amount', 12, 2)->default(20); });
        $request = Request::create('/', 'PUT', ['return_date'=>'2026-10-05', 'approval_item_ids'=>[2],
            'row_payloads'=>[json_encode(['id'=>2, 'other_amount'=>30, 'total_amount'=>999999])]]);
        (new SaleReturnController)->update($request, 'test', Crypt::encryptString('1'));
        $this->assertEquals(210, DB::table('sale_returns')->value('return_total'));
        $this->assertEquals(1, DB::table('sale_returns')->value('source_id'));
    }
}
