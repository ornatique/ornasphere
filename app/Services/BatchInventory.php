<?php

namespace App\Services;

use App\Models\ItemSet;

/** Quantity-safe stock movements for a batch represented by one QR label. */
class BatchInventory
{
    public static function consume(ItemSet $itemSet, int $quantity): int
    {
        $quantity = max(1, $quantity);
        $available = max(0, (int) ($itemSet->available_quantity ?? ($itemSet->is_sold ? 0 : 1)));
        if ($quantity > $available) {
            throw new \RuntimeException("Only {$available} piece(s) remain for this QR label.");
        }

        $remaining = $available - $quantity;
        $itemSet->available_quantity = $remaining;
        $itemSet->is_sold = $remaining === 0 ? 1 : 0;
        $itemSet->save();

        return $remaining;
    }

    public static function restore(ItemSet $itemSet, int $quantity): int
    {
        $quantity = max(1, $quantity);
        $batchQuantity = max(1, (int) ($itemSet->quantity ?? 1));
        $available = max(0, (int) ($itemSet->available_quantity ?? ($itemSet->is_sold ? 0 : 1)));
        $remaining = min($batchQuantity, $available + $quantity);
        $itemSet->available_quantity = $remaining;
        $itemSet->is_sold = $remaining === 0 ? 1 : 0;
        $itemSet->save();

        return $remaining;
    }
}
