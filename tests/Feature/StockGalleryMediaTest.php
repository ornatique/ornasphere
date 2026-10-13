<?php

namespace Tests\Feature;

use App\Http\Controllers\Company\StockGalleryMediaController;
use App\Http\Middleware\CompanyRoutePermission;
use App\Jobs\ProcessStockGalleryImage;
use App\Models\ItemSet;
use App\Models\StockGalleryMedia;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StockGalleryMediaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Never run fixture DDL against the configured application database.
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Schema::create('companies', function (Blueprint $t) { $t->id(); $t->string('slug'); });
        Schema::create('items', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('company_id'); $t->string('item_name'); });
        Schema::create('item_sets', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('company_id'); $t->unsignedBigInteger('item_id');
            $t->boolean('is_final')->default(true); $t->boolean('is_sold')->default(false);
            $t->string('qr_code'); $t->string('image_path')->nullable(); $t->string('image_disk')->nullable();
            $t->string('image_mime')->nullable(); $t->integer('image_size')->nullable(); $t->timestamp('image_uploaded_at')->nullable();
            $t->decimal('gross_weight', 12, 3); $t->decimal('net_weight', 12, 3); $t->timestamps();
        });
        (require database_path('migrations/2026_10_01_000001_create_stock_gallery_media_table.php'))->up();
        DB::table('companies')->insert([['id'=>1, 'slug'=>'one'], ['id'=>2, 'slug'=>'two']]);
        DB::table('items')->insert(['id'=>1,'company_id'=>1,'item_name'=>'Ring','try_on_category'=>'ring']);
        DB::table('item_sets')->insert(['id'=>1,'company_id'=>1,'item_id'=>1,'qr_code'=>'R1','gross_weight'=>10,'net_weight'=>9]);
        $this->withoutMiddleware();
        $user = new User(); $user->forceFill(['id'=>1,'company_id'=>1]);
        $this->actingAs($user);
        Storage::fake('local'); Storage::fake('public'); Queue::fake();
        config(['filesystems.item_image_disk'=>'public']);
    }

    private function url(string $slug = 'one'): string
    {
        return '/company/'.$slug.'/stock-gallery/'.Crypt::encryptString('1').'/media';
    }

    private function ready(): StockGalleryMedia
    {
        DB::table('item_sets')->where('id',1)->update(['image_disk'=>'public','image_path'=>'original.png']);
        Storage::disk('public')->put('original.png', 'original');
        Storage::disk('local')->put('cutout.png', 'cutout');
        return StockGalleryMedia::create(['company_id'=>1,'item_set_id'=>1,'source_disk'=>'public','source_path'=>'original.png',
            'cutout_path'=>'cutout.png','status'=>'ready','revision'=>'revision-one']);
    }

    private function settings(): array
    {
        return array_merge(StockGalleryMedia::defaults(), ['approved'=>true,'enabled'=>true,'revision'=>'revision-one']);
    }

    public function test_other_company_cannot_read_or_mutate_media(): void
    {
        $this->getJson($this->url('two'))->assertForbidden();
        $this->postJson($this->url('two').'/process')->assertForbidden();
        $this->putJson($this->url('two'),$this->settings())->assertForbidden();
    }

    public function test_upload_queues_processing_and_preserves_stock_values(): void
    {
        $this->post($this->url().'/upload', ['image'=>UploadedFile::fake()->image('ring.png',100,100)], ['Accept'=>'application/json'])
            ->assertOk()->assertJsonPath('status','queued')->assertJsonPath('enabled',false);
        Queue::assertPushed(ProcessStockGalleryImage::class);
        $item = ItemSet::findOrFail(1);
        $this->assertEquals(10,$item->gross_weight); $this->assertEquals(9,$item->net_weight);
        $this->assertEquals(0,$item->is_sold); $this->assertEquals('R1',$item->qr_code);
        Storage::disk('public')->assertExists($item->image_path);
        Storage::disk('local')->assertExists(StockGalleryMedia::first()->original_path);
    }

    public function test_approval_requires_ready_current_cutout_and_category(): void
    {
        $media = $this->ready();
        $this->putJson($this->url(),$this->settings())->assertOk()->assertJsonPath('enabled',true);
        DB::table('items')->where('id',1)->update(['try_on_category'=>'none']);
        $this->putJson($this->url(),$this->settings())->assertUnprocessable();
        $media->update(['status'=>'processing']);
        $this->putJson($this->url(),$this->settings())->assertUnprocessable();
    }

    public function test_old_cutout_is_not_available_after_existing_image_editor_replaces_photo(): void
    {
        $media = $this->ready(); $media->update(['approved'=>true,'enabled'=>true]);
        DB::table('item_sets')->where('id',1)->update(['image_path'=>'replacement.jpg']);
        $this->getJson($this->url())->assertOk()->assertJsonPath('enabled',false)->assertJsonPath('cutout_url',null);
        $this->getJson($this->url().'/cutout')->assertNotFound();
        $this->putJson($this->url(),$this->settings())->assertUnprocessable();
    }

    public function test_retry_disables_approval_and_stale_worker_cannot_overwrite_new_revision(): void
    {
        $media=$this->ready(); $media->update(['approved'=>true,'enabled'=>true]);
        $this->postJson($this->url().'/process')->assertOk()->assertJsonPath('approved',false);
        (new ProcessStockGalleryImage($media->id,'revision-one'))->failed(new \RuntimeException());
        $this->assertSame('queued',$media->fresh()->status);
    }

    public function test_invalid_image_and_settings_are_rejected(): void
    {
        $this->post($this->url().'/upload',['image'=>UploadedFile::fake()->create('bad.svg',1,'image/svg+xml')],['Accept'=>'application/json'])->assertUnprocessable();
        $this->ready();
        $this->putJson($this->url(), array_merge($this->settings(),['scale'=>900]))->assertUnprocessable();
    }

    public function test_view_only_user_cannot_manage_media(): void
    {
        $request = Request::create($this->url().'/process','POST');
        $route = app('router')->getRoutes()->match($request);
        $request->setRouteResolver(fn()=>$route);
        $user = \Mockery::mock(User::class)->makePartial(); $user->forceFill(['company_id'=>1]);
        $user->shouldReceive('hasRole')->with('company_admin')->andReturn(false);
        $user->shouldReceive('hasAnyPermission')->withArgs(fn($permissions)=>in_array('stock-gallery-edit',$permissions,true))->andReturn(false);
        $request->setUserResolver(fn()=>$user);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new CompanyRoutePermission())->handle($request,fn()=>response('Unexpected access'));
    }
}
