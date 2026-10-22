<?php
namespace Tests\Feature;

use App\Models\CastingReleaseItem;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CastingReceiveHistoryTest extends TestCase
{
    public function test_custom_receive_rows_are_included_only_for_the_requested_company_and_voucher(): void
    {
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        DB::statement('CREATE TABLE casting_release_items (id integer primary key, company_id integer, vacuum_voucher_id integer, vacuum_voucher_item_id integer, is_custom integer, custom_buch_no text, release_tree_wt real, release_tree_bhuko real)');
        foreach ([[1,1,10,100,0,5,0], [2,1,10,null,1,14.5,0], [3,1,10,null,1,0,12.81], [4,2,10,null,1,99,99], [5,1,11,null,1,99,99], [6,1,10,999,0,99,99]] as [$id,$company,$voucher,$item,$custom,$weight,$bhuko]) {
            DB::table('casting_release_items')->insert(['id'=>$id,'company_id'=>$company,'vacuum_voucher_id'=>$voucher,'vacuum_voucher_item_id'=>$item,'is_custom'=>$custom,'custom_buch_no'=>'custom-'.$id,'release_tree_wt'=>$weight,'release_tree_bhuko'=>$bhuko]);
        }
        $rows = CastingReleaseItem::forVoucherHistory(1,10,[100])->orderBy('id')->get();
        $this->assertSame([1,2,3], $rows->pluck('id')->all());
        $this->assertEquals(19.5, $rows->sum('release_tree_wt'));
        $this->assertEquals(12.81, $rows->sum('release_tree_bhuko'));
        foreach ([\App\Http\Controllers\Company\VoucherHistoryController::class, \App\Http\Controllers\Api\VoucherHistoryApiController::class] as $class) {
            $method = new \ReflectionMethod($class, 'buchNo');
            $this->assertSame('custom-3', $method->invoke(new $class, $rows->last()));
        }
    }
}
