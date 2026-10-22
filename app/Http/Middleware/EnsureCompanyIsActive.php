<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->company_id) {
            return $next($request);
        }

        $company = $user->company;
        $targetIds = array_map('intval', (array) $request->input('management_branches', []));
        if ($request->filled('company_id')) $targetIds[] = (int) $request->input('company_id');
        if ($request->route('slug') && $company && $request->route('slug') !== $company->slug) {
            $targetIds[] = (int) \App\Models\Company::where('slug',$request->route('slug'))->value('id');
        }
        if (collect($targetIds)->contains(fn ($id) => !\App\Services\BranchReadOnly::canWrite($user,$id))
            && !\App\Services\BranchReadOnly::isReadRequest($request)) {
            return \App\Services\BranchReadOnly::denied();
        }

        if ($request->route('slug')) {
            $targetSlug = (string) $request->route('slug');
            if (!$company || $company->slug !== $targetSlug) {
                $controller = explode('@', $request->route()->getActionName())[0];
                $allowed = [
                    \App\Http\Controllers\Company\BullionController::class,
                    \App\Http\Controllers\Company\QrMakingController::class,
                    \App\Http\Controllers\Company\StockGalleryController::class,
                    \App\Http\Controllers\Company\StockGalleryMediaController::class,

                    \App\Http\Controllers\Company\CompanyUserController::class,
                    \App\Http\Controllers\Company\CustomerController::class,
                    \App\Http\Controllers\Company\CompanyRoleController::class,
                    \App\Http\Controllers\Company\LabourFormulaController::class,
                    \App\Http\Controllers\Company\ProductionCostController::class,
                    \App\Http\Controllers\Company\ProductionStepController::class,
                    \App\Http\Controllers\Company\ItemController::class,
                    \App\Http\Controllers\Company\LabelConfigController::class,
                    \App\Http\Controllers\Company\ItemSetController::class,
                    \App\Http\Controllers\Company\OtherChargeController::class,
                    \App\Http\Controllers\Company\SaleController::class,
                    \App\Http\Controllers\Company\ApprovalController::class,
                    \App\Http\Controllers\Company\SaleReturnController::class,
                    \App\Http\Controllers\Company\CustomerAdvanceController::class,
                    \App\Http\Controllers\Company\JobworkIssueController::class,
                    \App\Http\Controllers\Company\JobworkReceiveController::class,

                    \App\Http\Controllers\Company\VacuumBuchController::class,
                    \App\Http\Controllers\Company\VacuumProcessController::class,
                    \App\Http\Controllers\Company\VacuumVoucherController::class,
                    \App\Http\Controllers\Company\CastingHeatingController::class,
                    \App\Http\Controllers\Company\CastingMetalIssueController::class,
                    \App\Http\Controllers\Company\CastingReleaseController::class,
                    \App\Http\Controllers\Company\TreeCuttingOfficeController::class,
                    \App\Http\Controllers\Company\TreeCuttingIssueController::class,
                    \App\Http\Controllers\Company\TreeCuttingReceiveController::class,
                    \App\Http\Controllers\Company\CastingSortingController::class,
                    \App\Http\Controllers\Company\VoucherHistoryController::class,

                ];
                if ($request->routeIs('company.reports.visiting-cards.*')) $allowed[] = \App\Http\Controllers\Company\ReportController::class;
                abort_unless(in_array($controller, $allowed, true)
                    && \App\Services\CompanyBranches::canManage($user), 403);
                $target = \App\Models\Company::where('slug', $targetSlug)
                    ->where('parent_company_id', $company->id)->first();
                abort_unless($target && (int) $target->status === 1, 403);
            }
        }
        $parentInactive = false;
        if ($company && $company->parent_company_id) {
            $parent = \App\Models\Company::find($company->parent_company_id);
            $parentInactive = !$parent || (int) $parent->status !== 1;
        }

        if ((int) $user->is_active !== 1) {
            if ($request->expectsJson() || $request->is('api/*')) {
                if ($user->currentAccessToken()) {
                    \App\Services\InactiveTokenResponse::revoke($user, $company && ((int) $company->status !== 1 || $parentInactive) ? 'COMPANY_INACTIVE' : 'USER_INACTIVE');
                }

                return response()->json([
                    'success' => false,
                    'code' => 'USER_INACTIVE',
                    'message' => 'Your account has been deactivated. Please contact your company admin.',
                ], 403);
            }

            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($company && $company->slug) {
                return redirect()
                    ->route('company.login', $company->slug)
                    ->withErrors(['email' => 'Your account has been deactivated. Please contact your company admin.']);
            }

            return redirect('/')
                ->withErrors(['email' => 'Your account has been deactivated. Please contact your company admin.']);
        }

        if (!$company || (int) $company->status !== 1 || $parentInactive) {
            // Revoke current API token if present.
            if ($request->expectsJson() || $request->is('api/*')) {
                if ($user->currentAccessToken()) {
                    \App\Services\InactiveTokenResponse::revoke($user);
                }

                return response()->json([
                    'success' => false,
                    'code' => 'COMPANY_INACTIVE',
                    'message' => 'Company inactive. Contact administrator.',
                ], 403);
            }

            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($company && $company->slug) {
                return redirect()
                    ->route('company.login', $company->slug)
                    ->withErrors(['email' => 'Company is inactive. Please contact super admin.']);
            }

            return redirect('/')
                ->withErrors(['email' => 'Company is inactive. Please contact super admin.']);
        }

        $plan = \App\Services\CompanyPlanService::status($company);
        if ($plan['expired']) {
            if ($request->expectsJson() || $request->is('api/*')) return response()->json(['success'=>false,'code'=>$plan['code'],'message'=>$plan['alert']['message'],'plan_alert'=>$plan['alert']],403);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('company.login', $company->slug)->withErrors(['email'=>$plan['alert']['message']]);
        }
        $response = $next($request);
        if ($response instanceof \Illuminate\Http\JsonResponse && $response->getStatusCode() === 200) {
            $payload = $response->getData(true);
            if (is_array($payload)) { $payload['plan_alert'] = $plan['alert']; $payload['plan_days_remaining'] = $plan['days_remaining']; $response->setData(\App\Services\BranchReadOnly::protectRows($payload,$user)); }
        }
        return $response;
    }
}
