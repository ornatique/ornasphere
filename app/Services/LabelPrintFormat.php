<?php
namespace App\Services;

use Illuminate\Validation\ValidationException;

class LabelPrintFormat
{
    public static function resolve($value): string
    {
        if (!is_string($value)) {
            throw ValidationException::withMessages(['label_format'=>'Send compact, double_barcode, double_barcode_price, or double_details.']);
        }
        $key = strtolower(trim($value));
        $key = preg_replace('/[\s%_+\/\-–—]+/u', '', $key);
        $formats = [
            'compact'=>'compact', 'default'=>'compact', 'compact(default)'=>'compact',
            'doublebarcode'=>'double_barcode', 'twobarcodenamegross'=>'double_barcode',
            'doublebarcodeprice'=>'double_barcode_price', 'twobarcodenamesaleprice'=>'double_barcode_price',
            'doubledetails'=>'double_details', '5050'=>'double_details', '5050qrdetails'=>'double_details',
        ];
        if (!isset($formats[$key])) {
            throw ValidationException::withMessages(['label_format'=>'Unknown label format. Use double_details for 50%-50% QR + Details.']);
        }
        return $formats[$key];
    }
}
