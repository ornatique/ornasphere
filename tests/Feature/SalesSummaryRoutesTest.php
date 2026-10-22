<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\ReportApiController;
use Illuminate\Http\Request;
use Tests\TestCase;

class SalesSummaryRoutesTest extends TestCase
{
    public function test_stock_details_formats_resolve_with_authentication(): void
    {
        foreach (['pdf', 'excel'] as $format) {
            foreach (['', 'export/'] as $prefix) {
                $route = app('router')->getRoutes()->match(Request::create('/api/reports/stock-position/details/'.$prefix.$format));
                $this->assertSame(ReportApiController::class.'@stockPositionDetails', $route->getActionName());
                $this->assertSame($format, $route->parameter('format'));
                $this->assertContains('auth:sanctum', $route->gatherMiddleware());
            }
        }
    }
    public function test_approval_details_export_aliases_keep_authentication_and_binding(): void
    {
        $routes = app('router')->getRoutes();
        foreach (['pdf' => 'approvalOutstandingDetailsPdf', 'excel' => 'approvalOutstandingDetailsExcel'] as $format => $method) {
            $canonical = $routes->match(Request::create('/api/reports/approval-outstanding/42/details/export/'.$format));
            $alias = $routes->match(Request::create('/api/reports/approval-outstanding/42/details/'.$format));
            $this->assertSame(ReportApiController::class.'@'.$method, $alias->getActionName());
            $this->assertSame($canonical->getActionName(), $alias->getActionName());
            $this->assertSame($canonical->gatherMiddleware(), $alias->gatherMiddleware());
            $this->assertContains('auth:sanctum', $alias->gatherMiddleware());
            $this->assertSame('42', $alias->parameter('approval'));
        }
    }

    public function test_details_export_aliases_resolve_to_the_same_protected_handlers(): void
    {
        $routes = app('router')->getRoutes();
        foreach (['pdf' => 'salesSummaryDetailsPdf', 'excel' => 'salesSummaryDetailsExcel'] as $format => $method) {
            $canonical = $routes->match(Request::create('/api/reports/sales-summary/366/details/export/'.$format));
            $alias = $routes->match(Request::create('/api/reports/sales-summary/366/details/'.$format));
            $this->assertSame(ReportApiController::class.'@'.$method, $alias->getActionName());
            $this->assertSame($canonical->getActionName(), $alias->getActionName());
            $this->assertSame($canonical->gatherMiddleware(), $alias->gatherMiddleware());
            $this->assertContains('auth:sanctum', $alias->gatherMiddleware());
            $this->assertSame('366', $alias->parameter('sale'));
        }
    }
}
