<?php

namespace App\Http\Controllers;

use App\Models\ApprovalHeader;
use App\Services\VoucherQr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class ApprovalQrController extends Controller
{
    public function scan(Request $request, int $id)
    {
        $approval = ApprovalHeader::with('company')->findOrFail($id);
        abort_unless($approval->company, 404);
        // The destination runs normal company, module, branch, role and 2FA checks.
        $destination = route('company.approval.view', [
            $approval->company->slug, Crypt::encryptString((string) $approval->id),
        ]);
        if (!$request->user()) {
            $request->session()->put('approval_qr_destination', $destination);
            return redirect()->route('company.login', $approval->company->slug);
        }
        return redirect()->to($destination);
    }

    public function image(int $id)
    {
        abort_unless(ApprovalHeader::whereKey($id)->exists(), 404);
        return response(VoucherQr::approvalPng($id), 200, ['Content-Type' => 'image/png']);
    }
}
