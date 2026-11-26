<?php

namespace App\Services;

use App\Models\ApprovalItem;

/** Resolves the per-piece fixed price and total for an approval line. */
class ApprovalItemPricing
{
    /** ItemSet sale_price is stored as a batch total; approvals use its per-piece sale price. */
    public static function itemSetSalePrice(?\App\Models\ItemSet $itemSet): float
    {
        if (!$itemSet) return 0.0;
        $batchPrice = (float) ($itemSet->sale_price ?? 0);
        return $batchPrice > 0
            ? round($batchPrice / max(1, (int) ($itemSet->quantity ?? 1)), 2)
            : 0.0;
    }

    public static function unitSalePrice(ApprovalItem $item): float
    {
        $itemSet = $item->itemSet ?? $item->legacyItemSet;
        if ($itemSet) {
            return self::itemSetSalePrice($itemSet);
        }

        return (float) ($item->sale_price ?? 0);
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
