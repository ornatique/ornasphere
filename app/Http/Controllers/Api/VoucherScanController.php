<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VoucherScan;
use Illuminate\Http\Request;

class VoucherScanController extends Controller
{
    public function qr(Request $request, string $type, int $id)
    {
        $destination = VoucherScan::resolve($request->user(), $type, $id);
        return response()->json(['success'=>true, 'data'=>array_merge($destination, [
            'qr_url'=>\App\Services\VoucherQr::url($type, $id),
            'qr_image_data_uri'=>\App\Services\VoucherQr::dataUri($type, $id),
        ])]);
    }
    public function resolve(Request $request)
    {
        $data = $request->validate(['qr' => 'required|string|max:4096|url', 'action_menu'=>'sometimes|boolean']);
        [$type, $id] = VoucherScan::decode($data['qr']);
        $destination = VoucherScan::resolve($request->user(), $type, $id);
        if ($type === 'casting' && $request->boolean('action_menu')) {
            $destination = \App\Services\CastingScanActions::menu($request->user(), $destination);
        }
        if ($type === 'casting-metal-issue') {
            $destination = \App\Services\CastingMetalIssueScanActions::menu($request->user(), $destination);
        }
        if ($type === 'tree-cutting-issue' && $request->boolean('action_menu')) {
            $destination = \App\Services\TreeCuttingIssueScanActions::menu($request->user(), $destination);
        }
        if ($type === 'approval' && $request->boolean('action_menu')) {
            $destination = \App\Services\ApprovalScanActions::menu($request->user(), $id, $destination);
        }
        if ($type === 'sale-return' && $request->boolean('action_menu')) {
            $destination = \App\Services\ReturnScanActions::menu($id, $destination);
        }
        if ($type === 'sale' && $request->boolean('action_menu')) {
            $destination = \App\Services\SaleScanActions::menu($destination);
        }
        if ($type === 'jobwork-issue' && $request->boolean('action_menu')) {
            $destination = \App\Services\JobworkIssueScanActions::menu($request->user(), $destination);
        }
        if ($type === 'jobwork-receive' && $request->boolean('action_menu')) {
            $destination = \App\Services\JobworkReceiveScanActions::menu($request->user(), $destination);
        }
        return response()->json(['success'=>true, 'data'=>$destination]);
    }

    public function returnOptions(Request $request, int $id)
    {
        VoucherScan::resolve($request->user(), 'approval', $id);
        $approval = \App\Models\ApprovalHeader::findOrFail($id);
        $reason = \App\Services\ApprovalScanActions::returnReason($request->user(), $approval);
        abort_if($reason !== null, 403, $reason ?? 'Return unavailable.');
        return response()->json(['success'=>true, 'data'=>[
            'approval_id'=>$id, 'customer_id'=>$approval->customer_id, 'company_id'=>$approval->company_id,
            'items'=>\App\Services\ApprovalScanActions::pending($approval)->get(),
            'submit_url'=>url('/api/approvals/return'), 'submit_method'=>'POST',
            'requires_confirmation'=>true,
        ]]);
    }
}
