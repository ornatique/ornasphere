<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\{Company,MarketingQr,User};
use App\Http\Controllers\Company\QrMakingController;
use Illuminate\Support\Facades\{Schema,Storage};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\{Request,UploadedFile};
use Illuminate\Database\Eloquent\ModelNotFoundException;
class QrMakingTest extends TestCase
{
    protected function setUp(): void {
        parent::setUp();
        $this->assertSame(':memory:',config('database.connections.sqlite.database'));
        Schema::create('companies',function(Blueprint $t){$t->id();$t->string('slug');$t->timestamps();});
        Company::forceCreate(['id'=>1,'slug'=>'one']); Company::forceCreate(['id'=>2,'slug'=>'two']);
        (require database_path('migrations/2026_10_06_000001_create_marketing_qrs_table.php'))->up();
        Storage::fake('local');
    }
    private function request(array $data, array $files=[]): Request {
        $r=Request::create('/','POST',$data,[],$files);
        $r->setUserResolver(fn()=> (new User)->forceFill(['id'=>7]));return $r;
    }
    public function test_web_qr_keeps_link_after_edit_and_generates_downloads(): void {
        $c=new QrMakingController;
        $data=['title'=>'Website','type'=>'web','size'=>250,'with_logo'=>'0','web_url'=>'https://example.com/first'];
        $c->store($this->request($data),'one'); $qr=MarketingQr::firstOrFail();$token=$qr->token;
        $this->assertSame('https://example.com/first',$c->open(Request::create('/'),$token)->getTargetUrl());
        $data['web_url']='https://example.com/second';$c->update($this->request($data),'one',$qr->id);
        $this->assertSame($token,$qr->fresh()->token);
        $this->assertSame('https://example.com/second',$c->open(Request::create('/'),$token)->getTargetUrl());
        $this->assertStringContainsString('<svg',$c->svg('one',$qr->id)->getContent());
        $this->assertStringStartsWith('%PDF',$c->pdf('one',$qr->id)->getContent());
        $direct=(new \Endroid\QrCode\Writer\SvgWriter)->write(new \Endroid\QrCode\QrCode(
            data:$data['web_url'],errorCorrectionLevel:\Endroid\QrCode\ErrorCorrectionLevel::High,size:250,margin:20
        ))->getString();
        $this->assertSame($direct,$c->renderQr($qr->fresh())->getString());
        try {$c->edit('two',$qr->id);$this->fail('Cross company access');}catch(ModelNotFoundException $e){}
    }
    public function test_app_redirects_and_logo_removal(): void {
        $c=new QrMakingController;
        $data=['title'=>'App','type'=>'app','size'=>350,'with_logo'=>'1','android_url'=>'https://play.google.com/store/apps/details?id=test','ios_url'=>'https://apps.apple.com/app/id123'];
        $c->store($this->request($data,['logo'=>UploadedFile::fake()->image('logo.png',80,80)]),'one');
        $qr=MarketingQr::firstOrFail();$path=$qr->logo_path;Storage::disk('local')->assertExists($path);
        $this->assertStringContainsString('image/png',$c->svg('one',$qr->id)->getContent());
        $this->assertStringStartsWith('%PDF',$c->pdf('one',$qr->id)->getContent());
        $android=Request::create('/');$android->headers->set('User-Agent','Android');
        $ios=Request::create('/');$ios->headers->set('User-Agent','iPhone');
        $this->assertSame($data['android_url'],$c->open($android,$qr->token)->getTargetUrl());
        $this->assertSame($data['ios_url'],$c->open($ios,$qr->token)->getTargetUrl());
        $this->assertStringContainsString('Choose your app store',$c->open(Request::create('/'),$qr->token)->getContent());
        $data['with_logo']='0';$c->update($this->request($data),'one',$qr->id);
        Storage::disk('local')->assertMissing($path);$this->assertNull($qr->fresh()->logo_path);
    }
    public function test_invalid_url_is_rejected(): void {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        (new QrMakingController)->store($this->request(['title'=>'Bad','type'=>'web','size'=>250,'with_logo'=>'0','web_url'=>'javascript:alert(1)']),'one');
    }
    public function test_api_crud_downloads_and_company_scope(): void {
        Schema::create('users',function(Blueprint $t){$t->id();$t->string('name');});
        $user=\Mockery::mock(\App\Models\User::class)->makePartial();
        $user->forceFill(['id'=>7,'company_id'=>1,'allowed'=>true]);
        $user->shouldReceive('hasRole')->andReturn(false);
        $user->shouldReceive('hasAnyPermission')->andReturnUsing(fn ()=>$user->allowed);
        $call=function($operation,$id=null,$data=[]) use($user) {
            $user->setRelation('company', \App\Models\Company::findOrFail($user->company_id));
            $r=Request::create('/api/qr-making','POST',$data);
            $r->setUserResolver(fn()=>$user);
            $route=new \Illuminate\Routing\Route('POST','api/qr-making',fn()=>null);
            $route->bind($r);$route->setParameter('operation',$operation);$route->setParameter('id',$id);
            $r->setRouteResolver(fn()=>$route);
            return (new \App\Http\Controllers\Api\QrMakingApiController)->handle($r);
        };
        $data=['title'=>'Ornatique Silver','type'=>'web','size'=>250,'with_logo'=>0,'web_url'=>'https://example.com'];
        $saved=$call('store',null,$data);$this->assertSame(201,$saved->getStatusCode());
        $id=$saved->getData(true)['data']['id'];
        $this->assertSame($data['web_url'],$saved->getData(true)['data']['qr_url']);
        $this->assertSame(1,$call('index')->getData(true)['data']['total']);
        $this->assertSame('Ornatique Silver',$call('show',$id)->getData(true)['data']['title']);
        $this->assertStringContainsString('Ornatique Silver.pdf',$call('pdf',$id)->headers->get('Content-Disposition'));
        $this->assertStringContainsString('<svg',$call('svg',$id)->getContent());
        $this->assertArrayHasKey('image',$call('preview',null,$data)->getData(true));
        $data['title']='Updated';$this->assertSame('Updated',$call('update',$id,$data)->getData(true)['data']['title']);
        $user->company_id=2;
        try {$call('show',$id);$this->fail('Cross company access');}catch(ModelNotFoundException $e){}
        $user->company_id=1;$user->allowed=false;
        try {$call('destroy',$id);$this->fail('Missing permission accepted');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(403,$e->getStatusCode());}
        $user->allowed=true;$this->assertTrue($call('destroy',$id)->getData(true)['success']);
        $this->assertSame(0,MarketingQr::count());
    }
    public function test_routes_require_the_matching_qr_permission(): void {
        foreach (['index'=>['GET','view'],'svg'=>['GET','view'],'pdf'=>['GET','view'],'store'=>['POST','create'],'update'=>['PUT','edit'],'destroy'=>['DELETE','delete']] as $suffix=>[$verb,$action]) {
            $r=Request::create('/',$verb);
            $route=(new \Illuminate\Routing\Route($verb,'/',fn()=>null))->name('company.qr-making.'.$suffix);
            $r->setRouteResolver(fn()=>$route);
            $user=new class { public $company_id=1; public $permission=''; public function hasRole($name){return false;} public function hasAnyPermission($names){return in_array($this->permission,$names,true);} };
            $r->setUserResolver(fn()=>$user);
            $middleware=new \App\Http\Middleware\CompanyRoutePermission;
            try { $middleware->handle($r,fn()=>response('ok')); $this->fail('Missing permission accepted'); } catch(\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertEquals(403,$e->getStatusCode()); }
            $user->permission='qr-making-'.$action;
            $this->assertEquals(200,$middleware->handle($r,fn()=>response('ok'))->getStatusCode());
        }
    }
}
