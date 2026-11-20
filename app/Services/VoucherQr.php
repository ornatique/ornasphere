<?php

namespace App\Services;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\URL;

class VoucherQr
{
    public static function url(string $type, int $id): string
    {
        if ($type === 'approval') return self::approvalUrl($id);
        abort_unless(isset(VoucherScan::TYPES[$type]), 422);
        return URL::signedRoute('voucher.scan', ['type'=>$type, 'id'=>$id]);
    }

    public static function dataUri(string $type, int $id): string
    {
        $png = (new PngWriter)->write(new QrCode(data: self::url($type, $id), size: 300, margin: 12))->getString();
        return 'data:image/png;base64,'.base64_encode($png);
    }
    // Permanent signed links also work for existing vouchers; no backfill needed.
    public static function approvalUrl(int $id): string
    {
        return URL::signedRoute('voucher.approval.scan', ['id' => $id]);
    }

    public static function approvalImageUrl(int $id): string
    {
        return URL::signedRoute('voucher.approval.image', ['id' => $id]);
    }

    public static function approvalPng(int $id): string
    {
        return (new PngWriter)->write(new QrCode(data: self::approvalUrl($id), size: 300, margin: 12))->getString();
    }

    public static function approvalDataUri(int $id): string
    {
        return 'data:image/png;base64,'.base64_encode(self::approvalPng($id));
    }
}
