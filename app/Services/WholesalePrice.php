<?php

namespace App\Services;

use App\Models\Company;
use App\Models\ItemSet;

class WholesalePrice
{
    public static function enabled(?Company $company): bool
    {
        if (!$company) return false;
        if ($company->parent_company_id) $company = $company->moduleParent;
        return (bool) ($company?->wholesale_price_enabled ?? false);
    }

    /** Sale and wholesale prices on a label are batch totals; voucher prices are per piece. */
    public static function price(?ItemSet $item)
    {
        if (!$item) return null;
        $batchTotal = (float) $item->wholesale_price > 0 && self::enabled($item->pricingCompany)
            ? $item->wholesale_price
            : $item->sale_price;
        if ($batchTotal === null || $batchTotal === '') return null;
        return round((float) $batchTotal / max(1, (int) ($item->quantity ?? 1)), 2);
    }

    /** Stable display padding; never used for billing or the scannable identifier. */
    public static function sticker($item): string
    {
        // Keep the sticker price source consistent with the company's configured
        // pricing mode. In wholesale mode, don't silently show retail sale price
        // when the wholesale value is missing.
        $useWholesalePrice = self::enabled($item->pricingCompany);
        $stickerPrice = $useWholesalePrice ? $item->wholesale_price : $item->sale_price;
        if ($stickerPrice === null || $stickerPrice === '') return '-';
        $price = number_format((float) $stickerPrice, 0, '.', '');
        if (empty($item->id)) return $price;
        $priceType = $useWholesalePrice ? 'wholesale' : 'sale';
        $hash = hash_hmac('sha256', $priceType.'-sticker:'.$item->company_id.':'.$item->id, (string) config('app.key'));
        return (string) (10 + hexdec(substr($hash, 0, 4)) % 90).$price.(string) (10 + hexdec(substr($hash, 4, 4)) % 90);
    }
}
