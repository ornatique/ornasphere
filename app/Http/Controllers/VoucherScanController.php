<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\VoucherScan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class VoucherScanController extends Controller
{
    public function scan(Request $request, string $type, int $id)
    {
        abort_unless(isset(VoucherScan::TYPES[$type]), 404);
        $model = VoucherScan::TYPES[$type][0];
        $record = $model::findOrFail($id);
        $company = Company::findOrFail($record->company_id);
        if ($type === 'casting') {
            $destination = route('company.voucher-history.scan-actions', [$company->slug, $id]);
            if ($request->user()) VoucherScan::resolve($request->user(), $type, $id);
            if (!$request->user() || !$request->session()->get('company_2fa_verified')) {
                $request->session()->put('approval_qr_destination', $destination);
            }
            return $request->user() ? redirect($destination) : redirect()->route('company.login', $company->slug);
        }
        if ($type === 'casting-metal-issue') {
            $destination = route('company.casting-metal-issue.pdf', [$company->slug, Crypt::encryptString((string) $id)]);
            if ($request->user()) VoucherScan::resolve($request->user(), $type, $id);
            if (!$request->user() || !$request->session()->get('company_2fa_verified')) {
                $request->session()->put('approval_qr_destination', $destination);
            }
            return $request->user() ? redirect($destination) : redirect()->route('company.login', $company->slug);
        }
        if ($type === 'tree-cutting-receive') {
            $destination = route('company.tree-cutting-receive.pdf', [$company->slug, Crypt::encryptString((string) $id)]);
            if ($request->user()) VoucherScan::resolve($request->user(), $type, $id);
            if (!$request->user() || !$request->session()->get('company_2fa_verified')) {
                $request->session()->put('approval_qr_destination', $destination);
            }
            return $request->user() ? redirect($destination) : redirect()->route('company.login', $company->slug);
        }
        if ($type === 'tree-cutting-issue') {
            $destination = route('company.tree-cutting-issue.scan-actions', [$company->slug, $id]);
            if ($request->user()) VoucherScan::resolve($request->user(), $type, $id);
            if (!$request->user() || !$request->session()->get('company_2fa_verified')) {
                $request->session()->put('approval_qr_destination', $destination);
            }
            return $request->user() ? redirect($destination) : redirect()->route('company.login', $company->slug);
        }
        $destination = $type === 'jobwork-issue'
            ? route('company.jobwork-issue.index', [$company->slug, 'voucher_id'=>$id])
            : route($type === 'sale' ? 'company.sales.pdf' : 'company.returns.pdf', [$company->slug, Crypt::encryptString((string)$id)]);
        if ($type === 'jobwork-issue') {
            $destination = route('company.jobwork-issue.scan-actions', [$company->slug, Crypt::encryptString((string)$id)]);
        }
        if ($type === 'jobwork-receive') {
            $destination = route('company.jobwork-receive.scan-actions', [$company->slug, Crypt::encryptString((string)$id)]);
        }
        if ($type === 'sale') {
            $destination = route('company.sales.scan-actions', [$company->slug, Crypt::encryptString((string)$id)]);
        }
        if ($type === 'sale-return' && in_array($record->source_type, ['approval', 'mixed'], true)) {
            $destination = route('company.returns.scan-actions', [$company->slug, Crypt::encryptString((string)$id)]);
        }
        if (!$request->user() || !$request->session()->get('company_2fa_verified')) {
            $request->session()->put('approval_qr_destination', $destination);
        }
        return $request->user() ? redirect($destination) : redirect()->route('company.login', $company->slug);
    }
}
