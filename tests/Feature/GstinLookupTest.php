<?php

namespace Tests\Feature;

use App\Services\GstinLookup;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class GstinLookupTest extends TestCase
{
    public function test_search_requires_gst_search_permission_for_both_methods(): void
    {
        foreach (['GET', 'POST'] as $method) {
          foreach (['/company/example/customers/gst-search', '/company/example/gst-search'] as $url) {
            $request = \Illuminate\Http\Request::create($url, $method);
            $route = app('router')->getRoutes()->match($request);
            $request->setRouteResolver(fn () => $route);
            $user = \Mockery::mock(\App\Models\User::class)->makePartial();
            $user->forceFill(['company_id'=>1]);
            $user->shouldReceive('hasRole')->with('company_admin')->andReturn(false);
            $user->shouldReceive('hasAnyPermission')->withArgs(fn ($permissions) => in_array('gst-search-view', $permissions, true))->andReturn(false);
            $request->setUserResolver(fn () => $user);
            try {
                (new \App\Http\Middleware\CompanyRoutePermission)->handle($request, fn () => response('unexpected'));
                $this->fail('Search allowed without create permission');
            } catch (HttpException $exception) { $this->assertSame(403, $exception->getStatusCode()); }
          }
        }
    }

    public function test_lookup_normalizes_and_maps_details_without_guessing_city(): void
    {
        config(['services.gstin.key' => 'test-secret']);
        Http::preventStrayRequests();
        Http::fake(['www.gstinapi.in/*' => Http::response(['success'=>true, 'data'=>[
            'gstin'=>'24GDTPS5199L1ZB', 'legal_name'=>'Legal Name', 'trade_name'=>'Ornatique Silver',
            'address'=>'Gujarat', 'city'=>'Locality, Metro', 'pincode'=>'380004',
            'address_details'=>['locality'=>'Locality'], 'nature_of_business'=>['Manufacturing'],
        ]])]);
        $data = (new GstinLookup)->lookup('24gdtps5199l1zb');
        $this->assertSame('Ornatique Silver', $data['name']);
        $this->assertSame('GDTPS5199L', $data['pan_no']);
        $this->assertSame('', $data['city']);
        $this->assertSame('380004', $data['pincode']);
        Http::assertSent(fn ($request) => $request->hasHeader('x-api-key', 'test-secret') && $request['include'] === 'profile');
    }

    public function test_invalid_input_never_calls_provider(): void
    {
        Http::fake();
        try { (new GstinLookup)->lookup('invalid'); $this->fail('Invalid input accepted'); }
        catch (ValidationException $e) { Http::assertNothingSent(); }
    }

    public function test_missing_key_does_not_call_provider(): void
    {
        config(['services.gstin.key'=>null]); Http::fake();
        try { (new GstinLookup)->lookup('24GDTPS5199L1ZB'); $this->fail('Missing key accepted'); }
        catch (HttpException $e) { $this->assertSame(503, $e->getStatusCode()); Http::assertNothingSent(); }
    }

    public function test_exhausted_credits_are_not_reported_as_invalid_gstin(): void
    {
        config(['services.gstin.key'=>'test']);
        Http::fake(['*'=>Http::response(['success'=>false], 402)]);
        $this->expectExceptionMessage('GST lookup credits have run out.');
        (new GstinLookup)->lookup('24GDTPS5199L1ZB');
    }

    public function test_mismatched_result_is_rejected(): void
    {
        config(['services.gstin.key'=>'test']);
        Http::fake(['*'=>Http::response(['success'=>true,'data'=>['gstin'=>'OTHER','legal_name'=>'Wrong business']])]);
        $this->expectExceptionMessage('The GST provider returned incomplete details.');
        (new GstinLookup)->lookup('24GDTPS5199L1ZB');
    }
}
