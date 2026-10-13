<?php
namespace Tests\Feature;

use App\Models\{User, CustomerOrder, CustomerOrderLine, CadDesign, RptBatch, OrderProductionFile, CustomerAdvanceVoucher};
use App\Http\Middleware\CompanyRoutePermission;
use App\Support\OrderProduction;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\{Request, UploadedFile};
use Illuminate\Support\Facades\{DB, Schema, Storage, View};
use Tests\TestCase;

class OrderProductionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Schema::create('companies', function (Blueprint $t) { $t->id(); $t->string('slug'); });
        Schema::create('users', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('company_id'); $t->string('name'); });
        Schema::create('category_people', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('company_id'); $t->string('category_name'); $t->boolean('is_system_default')->default(false); $t->timestamps(); });
        Schema::create('customers', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('company_id'); $t->unsignedBigInteger('category_person_id'); $t->boolean('is_active')->default(true); $t->string('name'); $t->string('city')->nullable(); $t->string('mobile_no')->nullable(); });
        Schema::create('items', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('company_id'); $t->string('item_name'); $t->string('item_code'); $t->string('metal'); $t->decimal('outward_purity', 7, 3); });
        Schema::create('item_sets', function (Blueprint $t) { $t->id(); });
        foreach (['2026_05_30_120000_create_customer_advance_ledgers_table.php', '2026_08_14_000001_create_customer_advance_vouchers_tables.php', '2026_10_02_000001_create_order_production_tables.php'] as $migration) (require database_path('migrations/'.$migration))->up();
        DB::table('companies')->insert([['id'=>1,'slug'=>'one'],['id'=>2,'slug'=>'two']]);
        DB::table('users')->insert([['id'=>1,'company_id'=>1,'name'=>'Designer'], ['id'=>2,'company_id'=>2,'name'=>'Other company']]);
        DB::table('category_people')->insert([['id'=>1,'company_id'=>1,'category_name'=>'Customer'], ['id'=>2,'company_id'=>2,'category_name'=>'Customer']]);
        DB::table('customers')->insert([['id'=>1,'company_id'=>1,'category_person_id'=>1,'name'=>'Customer One'], ['id'=>2,'company_id'=>2,'category_person_id'=>2,'name'=>'Customer Two']]);
        DB::table('items')->insert([['id'=>1,'company_id'=>1,'item_name'=>'Ring','item_code'=>'R','metal'=>'silver','outward_purity'=>92.5], ['id'=>2,'company_id'=>2,'item_name'=>'Private ring','item_code'=>'P','metal'=>'gold','outward_purity'=>91.6]]);
        $this->withoutMiddleware([
            \App\Http\Middleware\EnsureSingleActiveWebSession::class,
            \App\Http\Middleware\EnsureCompanyIsActive::class,
            \App\Http\Middleware\Company2FA::class,
            CompanyRoutePermission::class,
            \App\Http\Middleware\MarkCompanyNotificationModuleRead::class,
        ]);
        $this->actingAs(User::findOrFail(1));
        Storage::fake('local');
        // Render feature templates without unrelated dashboard/sidebar database fixtures.
        View::getFinder()->prependLocation(base_path('tests/Fixtures/order_views'));
    }
    private function payload(): array
    {
        return ['customer_id'=>1, 'order_date'=>'2026-10-02', 'delivery_date'=>'2026-10-20', 'lines'=>[
            ['item_id'=>1, 'metal'=>'silver', 'purity'=>92.5, 'pieces'=>10, 'expected_weight'=>50, 'size'=>'12', 'instructions'=>'Polished']]];
    }
    private function order(): CustomerOrder
    {
        $this->post('/company/one/orders', $this->payload())->assertSessionHasNoErrors()->assertRedirect();
        return CustomerOrder::latest('id')->firstOrFail();
    }
    private function design(CustomerOrder $order): CadDesign
    {
        $this->put('/company/one/orders/'.$order->id.'/status', ['status'=>'confirmed'])->assertSessionHasNoErrors();
        $this->post('/company/one/order-lines/'.$order->lines->first()->id.'/cad', ['designer_id'=>1])->assertSessionHasNoErrors()->assertRedirect();
        return CadDesign::latest('id')->firstOrFail();
    }
    private function approve(CadDesign $design): void
    {
        $this->put('/company/one/cad-designs/'.$design->id, ['status'=>'designing', 'weight_per_piece'=>5])->assertSessionHasNoErrors();
        $this->post('/company/one/orders/'.$design->line->customer_order_id.'/cad-files', ['kind'=>'print','cad_id'=>$design->id,'file'=>UploadedFile::fake()->create('ring.stl', 2, 'application/octet-stream')])->assertSessionHasNoErrors();
        $this->put('/company/one/cad-designs/'.$design->id, ['status'=>'submitted', 'weight_per_piece'=>5])->assertSessionHasNoErrors();
        $this->put('/company/one/cad-designs/'.$design->id, ['status'=>'approved','approved_by'=>'Customer One','approval_remarks'=>'Approved sample'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $design->fresh()->status);
    }
    private function batch(CadDesign $design, int $pieces): RptBatch
    {
        $this->post('/company/one/cad-designs/'.$design->id.'/rpt', ['operator_id'=>1,'printer'=>'Printer A','material'=>'Resin','planned_pieces'=>$pieces])->assertSessionHasNoErrors()->assertRedirect();
        return RptBatch::latest('id')->firstOrFail();
    }
    private function finish(RptBatch $batch, int $printed, int $accepted, int $rejected): void
    {
        foreach (['printing', 'printed', 'quality_check', 'ready'] as $status) {
            $this->put('/company/one/rpt-batches/'.$batch->id, ['status'=>$status,'printed_pieces'=>$printed,'accepted_pieces'=>$accepted,
                'rejected_pieces'=>$rejected,'pattern_weight'=>2.3,'started_on'=>'2026-10-03','completed_on'=>'2026-10-03', 'rejection_reason'=>$rejected ? 'Print failure' : null])->assertSessionHasNoErrors();
        }
    }
    public function test_full_partial_print_reprint_flow_and_rendered_screens(): void
    {
        $order = $this->order(); $design = $this->design($order); $this->approve($design);
        $batch = $this->batch($design, 6); $this->finish($batch, 6, 5, 1);
        $this->assertSame('in_rpt', $order->fresh()->progress);
        $second = $this->batch($design, 5); $this->finish($second, 5, 5, 0);
        $this->assertSame('ready_for_casting', $order->fresh()->progress);
        $this->assertSame(10, $order->fresh()->lines->first()->accepted);
        $this->assertEquals(50, $order->fresh()->lines->first()->expected_weight);
        $this->assertSame(0, DB::table('customer_advance_ledgers')->count());
        foreach (['orders','orders/create','orders/'.$order->id,'cad-designs','cad-designs/'.$design->id,'rpt-batches','rpt-batches/'.$batch->id] as $path) $this->get('/company/one/'.$path)->assertOk();
    }
    public function test_cross_company_ids_and_injected_attributes_are_rejected_or_ignored(): void
    {
        $payload = $this->payload(); $payload['customer_id']=2;
        $this->post('/company/one/orders', $payload)->assertSessionHasErrors('customer_id');
        $payload = $this->payload(); $payload['lines'][0]['item_id']=2;
        $this->post('/company/one/orders', $payload)->assertSessionHasErrors('lines.0.item_id');
        $payload = $this->payload(); $payload['company_id']=2; $payload['status']='confirmed'; $payload['lines'][0]['customer_order_id']=999;
        $this->post('/company/one/orders', $payload)->assertSessionHasNoErrors();
        $order = CustomerOrder::firstOrFail();
        $this->assertEquals(1, $order->company_id); $this->assertSame('draft', $order->status);
        $this->assertEquals($order->id, $order->lines->first()->customer_order_id);
        $this->get('/company/two/orders/'.$order->id)->assertForbidden();
        $this->actingAs(User::findOrFail(2));
        $this->get('/company/two/orders/'.$order->id)->assertNotFound();
    }
    public function test_draft_edit_preserves_ids_and_confirmed_order_is_locked(): void
    {
        $order = $this->order(); $payload = $this->payload();
        $payload['lines'][0]['id'] = $order->lines->first()->id; $payload['lines'][0]['pieces']=12;
        $this->put('/company/one/orders/'.$order->id, $payload)->assertSessionHasNoErrors();
        $this->assertEquals(12, $order->fresh()->lines->first()->pieces);
        $this->get('/company/one/orders/'.$order->id.'/edit')->assertOk()->assertSee('Edit '.$order->number);
        $this->put('/company/one/orders/'.$order->id.'/status', ['status'=>'confirmed'])->assertSessionHasNoErrors();
        $this->put('/company/one/orders/'.$order->id, $payload)->assertSessionHasErrors('production');
    }
    public function test_cad_requires_confirmation_print_file_and_immutable_approval(): void
    {
        $order = $this->order();
        $this->post('/company/one/order-lines/'.$order->lines->first()->id.'/cad', ['designer_id'=>1])->assertSessionHasErrors('production');
        $design = $this->design($order);
        $this->put('/company/one/cad-designs/'.$design->id, ['status'=>'designing'])->assertSessionHasNoErrors();
        $this->put('/company/one/cad-designs/'.$design->id, ['status'=>'submitted','weight_per_piece'=>5])->assertSessionHasErrors('production');
        $this->approve($design);
        $this->put('/company/one/cad-designs/'.$design->id, ['status'=>'designing','weight_per_piece'=>9])->assertSessionHasErrors('production');
        $this->post('/company/one/orders/'.$order->id.'/cad-files', ['kind'=>'print','cad_id'=>$design->id,'file'=>UploadedFile::fake()->create('replacement.stl',1)])->assertSessionHasErrors('production');
        $this->assertEquals(5, $design->fresh()->weight_per_piece);
        $this->assertSame(1, OrderProductionFile::count());
    }
    public function test_new_revision_does_not_change_old_batch_and_unapproved_revision_cannot_print(): void
    {
        $order=$this->order(); $design=$this->design($order); $this->approve($design); $batch=$this->batch($design, 6);
        $this->post('/company/one/order-lines/'.$design->customer_order_line_id.'/cad', ['designer_id'=>1])->assertSessionHasNoErrors();
        $new=CadDesign::latest('id')->firstOrFail(); $this->assertEquals(2, $new->revision);
        foreach ([$design,$new] as $record) $this->post('/company/one/cad-designs/'.$record->id.'/rpt', ['operator_id'=>1,'printer'=>'P','material'=>'Wax','planned_pieces'=>1])->assertSessionHasErrors('production');
        $this->assertEquals($design->id, $batch->fresh()->cad_design_id);
        $this->finish($batch,6,6,0);
    }
    public function test_reservations_quantity_checks_hold_and_completed_inspection_lock(): void
    {
        $order=$this->order(); $design=$this->design($order); $this->approve($design); $batch=$this->batch($design, 6);
        $this->post('/company/one/cad-designs/'.$design->id.'/rpt', ['operator_id'=>1,'printer'=>'P','material'=>'Wax','planned_pieces'=>5])->assertSessionHasErrors('production');
        $bad=['status'=>'printing','printed_pieces'=>7,'accepted_pieces'=>0,'rejected_pieces'=>0,'pattern_weight'=>1,'started_on'=>'2026-10-03'];
        $this->put('/company/one/rpt-batches/'.$batch->id,$bad)->assertSessionHasErrors('production');
        $this->put('/company/one/orders/'.$order->id.'/status',['status'=>'on_hold'])->assertSessionHasNoErrors();
        $bad['printed_pieces']=6;
        $this->put('/company/one/rpt-batches/'.$batch->id,$bad)->assertSessionHasErrors('production');
        $this->put('/company/one/orders/'.$order->id.'/status',['status'=>'confirmed'])->assertSessionHasNoErrors();
        $this->finish($batch,6,5,1);
        $this->put('/company/one/rpt-batches/'.$batch->id,$bad)->assertSessionHasErrors('production');
        $this->put('/company/one/orders/'.$order->id.'/status',['status'=>'cancelled'])->assertSessionHasErrors('production');
    }
    public function test_files_are_company_scoped_and_wrong_upload_module_is_blocked(): void
    {
        $order=$this->order(); $design=$this->design($order); $this->approve($design);
        $file=OrderProductionFile::firstOrFail();
        $this->get('/company/one/order-files/'.$file->id)->assertOk()->assertDownload('ring.stl');
        $this->post('/company/one/orders/'.$order->id.'/files',['kind'=>'print','cad_id'=>$design->id,'file'=>UploadedFile::fake()->create('x.stl',1)])->assertForbidden();
        $this->actingAs(User::findOrFail(2));
        $this->get('/company/two/order-files/'.$file->id)->assertNotFound();
        $this->get('/company/two/cad-designs/'.$design->id)->assertNotFound();
    }
    public function test_advance_cash_and_metal_post_once_and_link_same_customer_order(): void
    {
        $order=$this->order();
        foreach ([['entry_type'=>'receive_amount','amount'=>1000], ['entry_type'=>'receive_metal','metal_type'=>'silver','fine_weight'=>5]] as $entry) {
            $this->post('/company/one/sales/advance-ledger', $entry + ['entry_date'=>'2026-10-02','customer_id'=>1,'order_id'=>$order->id])->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->assertSame(2, DB::table('customer_advance_ledgers')->count());
        $this->assertSame(2, $order->fresh()->vouchers->count());
        $this->assertEquals(1000, DB::table('customer_advance_ledgers')->sum('cash_in'));
        $this->assertEquals(5, DB::table('customer_advance_ledgers')->sum('metal_in'));
        $voucher=CustomerAdvanceVoucher::firstOrFail();
        OrderProduction::linkVoucher($order->id,$voucher,1);
        $this->assertSame(2, DB::table('customer_order_vouchers')->count());
        $other=$this->order(); $other->update(['customer_id'=>2]);
        $this->post('/company/one/sales/advance-ledger',['entry_type'=>'receive_amount','amount'=>50,'entry_date'=>'2026-10-02','customer_id'=>1,'order_id'=>$other->id])->assertSessionHasErrors('production');
        $this->assertSame(2, DB::table('customer_advance_ledgers')->count());
        $this->assertSame(2, CustomerAdvanceVoucher::count());
    }
    public function test_view_permission_does_not_allow_rpt_writes(): void
    {
        $request=Request::create('/company/one/rpt-batches/1','PUT');
        $route=app('router')->getRoutes()->match($request); $request->setRouteResolver(fn()=>$route);
        $user=\Mockery::mock(User::class)->makePartial(); $user->forceFill(['company_id'=>1]);
        $user->shouldReceive('hasRole')->with('company_admin')->andReturn(false);
        $user->shouldReceive('hasAnyPermission')->withArgs(fn($permissions)=>in_array('rpt-production-edit',$permissions,true))->andReturn(false);
        $request->setUserResolver(fn()=>$user);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new CompanyRoutePermission())->handle($request,fn()=>response('Unexpected access'));
    }
    public function test_existing_unlinked_receipt_can_be_attached_without_posting_again(): void
    {
        $order = $this->order();
        $this->post('/company/one/sales/advance-ledger', ['entry_type'=>'receive_amount', 'amount'=>50000,
            'entry_date'=>'2026-10-03', 'customer_id'=>1])->assertSessionHasNoErrors();
        $voucher = CustomerAdvanceVoucher::firstOrFail();
        $this->assertCount(0, $order->fresh()->vouchers);
        $this->get('/company/one/orders/'.$order->id)->assertOk()->assertSee('Link Existing Voucher')->assertSee($voucher->voucher_no);
        for ($i=0; $i<2; $i++) {
            $this->post('/company/one/orders/'.$order->id.'/vouchers', ['voucher_id'=>$voucher->id])->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->assertCount(1, $order->fresh()->vouchers);
        $this->assertEquals(50000, DB::table('customer_advance_ledgers')->sum('cash_in'));
        $this->assertSame(1, DB::table('customer_advance_ledgers')->count());
        $this->assertSame(1, CustomerAdvanceVoucher::count());
        $second = $this->order();
        $this->post('/company/one/orders/'.$second->id.'/vouchers', ['voucher_id'=>$voucher->id])->assertSessionHasErrors('production');
        $this->assertCount(0, $second->fresh()->vouchers);
        $this->actingAs(User::findOrFail(2));
        $this->post('/company/two/orders/'.$order->id.'/vouchers', ['voucher_id'=>$voucher->id])->assertNotFound();
    }
    public function test_voucher_form_offers_order_selection_and_preserves_order_context(): void
    {
        $order = $this->order();
        $this->get('/company/one/sales/advance-ledger/create')->assertOk()->assertSee('Customer Order (optional)')->assertSee($order->number);
        $this->get('/company/one/sales/advance-ledger/create?order_id='.$order->id)->assertOk()->assertSee('Linked order:');
    }
    public function test_guided_order_and_cad_screens_offer_only_the_current_stage(): void
    {
        $order = $this->order();
        $this->get('/company/one/orders/'.$order->id)->assertOk()
            ->assertSee('Confirm Order &amp; Start CAD', false)->assertDontSee('Create CAD Revision')->assertDontSee('<strong>Confirmed</strong>', false);
        $design = $this->design($order);
        $this->get('/company/one/cad-designs/'.$design->id)->assertOk()->assertSee('Start Designing')->assertDontSee('Send for Approval');
        $this->put('/company/one/cad-designs/'.$design->id, ['status'=>'designing'])->assertSessionHasNoErrors();
        $this->get('/company/one/orders/'.$order->id)->assertOk()->assertSee('Continue CAD Design')->assertDontSee('name="designer_id"', false);
        $this->get('/company/one/cad-designs/'.$design->id)->assertOk()->assertSee('Upload Print File')
            ->assertSee('Save Work')->assertSee('Send for Approval becomes available')->assertDontSee('Next status');
        $this->approve($design);
        $this->get('/company/one/cad-designs/'.$design->id)->assertOk()->assertSee('Step 4')->assertSee('Create RPT Batch')->assertDontSee('Send for Approval');
        $this->post('/company/one/order-lines/'.$design->customer_order_line_id.'/cad', ['designer_id'=>1])->assertSessionHasNoErrors();
        $this->get('/company/one/cad-designs/'.$design->id)->assertOk()->assertDontSee('Create RPT Batch');
    }
}
