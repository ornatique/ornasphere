<?php

namespace App\Services;

use App\Models\{ApprovalItem, SaleItem, ItemSet};

class SaleApprovalStockRestore
{
    /** Caller owns the sale update transaction. Removed approval goods return to the shop. */
    public function restore(ApprovalItem $approval, SaleItem $saleItem, int $companyId): void
    {
        abort_unless((int) $approval->approval->company_id === $companyId, 404);
        $approval->update(['status' => 'returned']);
        $itemSetId = $saleItem->itemset_id ?: $approval->itemset_id;
        if ($itemSetId) {
            $itemSet = ItemSet::where('company_id', $companyId)->whereKey($itemSetId)->lockForUpdate()->first();
            if ($itemSet) {
                BatchInventory::restore($itemSet, max(1, (int) ($saleItem->qty ?? $approval->qty ?? 1)));
            }
        }
    }
}
