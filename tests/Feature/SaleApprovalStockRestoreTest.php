<?php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use App\Models\{ApprovalItem, SaleItem};
use App\Services\SaleApprovalStockRestore;

class SaleApprovalStockRestoreTest extends TestCase
{
    public function test_removed_approval_item_returns_to_stock_and_rollback_restores_it(): void
    {
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        DB::statement('CREATE TABLE approval_headers (id integer primary key, company_id integer)');
        DB::statement('CREATE TABLE approval_items (id integer primary key, approval_id integer, itemset_id integer, status text, updated_at text)');
        DB::statement('CREATE TABLE item_sets (id integer primary key, company_id integer, is_sold integer, updated_at text)');
        DB::table('approval_headers')->insert(['id'=>1,'company_id'=>1]);
        DB::table('approval_items')->insert(['id'=>1,'approval_id'=>1,'itemset_id'=>9,'status'=>'sold']);
        DB::table('item_sets')->insert(['id'=>9,'company_id'=>1,'is_sold'=>1]);
        $saleItem = new SaleItem(['itemset_id'=>9,'approval_item_id'=>1]);
        DB::beginTransaction();
        (new SaleApprovalStockRestore)->restore(ApprovalItem::findOrFail(1), $saleItem, 1);
        $this->assertEquals('returned', ApprovalItem::find(1)->status);
        $this->assertEquals(0, DB::table('item_sets')->value('is_sold'));
        DB::rollBack();
        $this->assertEquals('sold', ApprovalItem::find(1)->status);
        $this->assertEquals(1, DB::table('item_sets')->value('is_sold'));
    }
}
