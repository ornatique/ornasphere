<?php
namespace Tests\Feature;

use App\Http\Middleware\CompanyRoutePermission;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BullionPermissionTest extends TestCase
{
    public function test_module_permissions_allow_only_the_requested_action(): void
    {
        foreach ([['GET','bullion','bullion-view',true], ['POST','bullion','bullion-create',true],
            ['POST','bullion','bullion-view',false], ['GET','bullion','person-view',false],
            ['POST','gst-search','gst-search-view',true], ['GET','gst-search','person-create',false]] as [$method,$path,$permission,$allowed]) {
            $request = Request::create('/company/example/'.$path, $method);
            $route = app('router')->getRoutes()->match($request);
            $request->setRouteResolver(fn () => $route);
            $user = \Mockery::mock(User::class)->makePartial();
            $user->forceFill(['company_id'=>1]);
            $user->shouldReceive('hasRole')->with('company_admin')->andReturn(false);
            $user->shouldReceive('hasAnyPermission')->andReturnUsing(fn ($candidates) => in_array($permission,$candidates,true));
            $request->setUserResolver(fn () => $user);
            try {
                $response = (new CompanyRoutePermission)->handle($request,fn () => response('allowed'));
                $this->assertTrue($allowed);
                $this->assertSame(200,$response->getStatusCode());
            } catch (HttpException $e) {
                $this->assertFalse($allowed);
                $this->assertSame(403,$e->getStatusCode());
            }
        }
    }
}
