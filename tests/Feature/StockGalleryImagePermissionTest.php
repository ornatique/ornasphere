<?php

namespace Tests\Feature;

use App\Http\Controllers\Company\ItemSetController;
use App\Http\Middleware\CompanyRoutePermission;
use App\Models\User;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class StockGalleryImagePermissionTest extends TestCase
{
    public static function imageActions(): array
    {
        return [
            ['GET', 'view', 'showImage'],
            ['POST', 'edit', 'updateImage'],
            ['DELETE', 'delete', 'removeImage'],
        ];
    }

    #[DataProvider('imageActions')]
    public function test_gallery_image_actions_use_gallery_permissions(string $method, string $action, string $handler): void
    {
        $request = $this->imageRequest($method, ['stock-gallery-'.$action]);
        $this->assertSame(ItemSetController::class.'@'.$handler, $request->route()->getActionName());
        $response = (new CompanyRoutePermission())->handle($request, fn () => response('allowed'));
        $this->assertSame(200, $response->getStatusCode());
    }

    #[DataProvider('imageActions')]
    public function test_unrelated_permissions_do_not_allow_gallery_image_actions(string $method, string $action): void
    {
        $request = $this->imageRequest($method, ['item-set-'.$action]);
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('You do not have permission to access this module.');
        (new CompanyRoutePermission())->handle($request, fn () => response('unexpected'));
    }

    public function test_view_only_gallery_user_cannot_upload_or_remove_images(): void
    {
        foreach (['POST', 'DELETE'] as $method) {
            try {
                (new CompanyRoutePermission())->handle($this->imageRequest($method, ['stock-gallery-view']), fn () => response('unexpected'));
                $this->fail('View-only user was allowed to change an image.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
    }

    private function imageRequest(string $method, array $permissions): Request
    {
        $request = Request::create('/company/example/stock-gallery/encrypted-id/image', $method);
        $route = app('router')->getRoutes()->match($request);
        $request->setRouteResolver(fn () => $route);
        $user = \Mockery::mock(User::class)->makePartial();
        $user->forceFill(['company_id' => 1]);
        $user->shouldReceive('hasRole')->with('company_admin')->andReturn(false);
        $user->shouldReceive('hasAnyPermission')->andReturnUsing(fn ($candidates) => count(array_intersect($permissions, $candidates)) > 0);
        $request->setUserResolver(fn () => $user);

        return $request;
    }
}
