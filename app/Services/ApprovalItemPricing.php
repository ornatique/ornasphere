<?php

namespace App\Services;

use App\Models\ApprovalItem;

/** Resolves the per-piece fixed price and total for an approval line. */
class ApprovalItemPricing
{
    public static function unitSalePrice(ApprovalItem $item): float
    {
        $itemSet = $item->itemSet ?? $item->legacyItemSet;
        $labelPrice = (float) ($itemSet?->billing_sale_price ?? 0);

        // Older API partial returns incorrectly split this per-piece value.
        // The linked label is the source of truth for those legacy approval rows.
        return $labelPrice > 0 ? $labelPrice : (float) ($item->sale_price ?? 0);
    }

    public static function totalAmount(ApprovalItem $item, ?int $quantity = null): float
    {
        $qty = max(0, $quantity ?? (int) ($item->qty ?? 1));
        $unitPrice = self::unitSalePrice($item);
        if ($unitPrice > 0) {
            return round($unitPrice * $qty, 2);
        }

        $originalQty = max(1, (int) ($item->qty ?? 1));
        return round(ApprovalItemAvailability::remainingValue(
            $item->total_amount ?? 0,
            $originalQty,
            $qty
        ), 2);
    }
}
