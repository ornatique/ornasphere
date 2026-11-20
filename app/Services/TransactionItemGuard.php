<?php

namespace App\Services;

use App\Models\ApprovalItem;
use App\Models\ItemSet;
use App\Models\SaleItem;
use App\Models\SaleReturnItem;
use Illuminate\Support\Facades\Schema;

class TransactionItemGuard
{
    public static function lockItemSetForUse(int $companyId, int $itemSetId, bool $allowAlreadyUsed = false): ItemSet
    {
        $itemSet = ItemSet::where('company_id', $companyId)
            ->where('id', $itemSetId)
            ->lockForUpdate()
            ->first();

        if (!$itemSet) {
            throw new \Exception("Item not found: {$itemSetId}");
        }

        $availableQuantity = max(0, (int) ($itemSet->available_quantity ?? ($itemSet->is_sold ? 0 : 1)));
        if (!$allowAlreadyUsed && $availableQuantity === 0) {
            throw new \Exception('This item is already used in another voucher: ' . self::itemLabel($itemSet));
        }

        return $itemSet;
    }

    public static function lockApprovalItemForSale(int $companyId, int $approvalItemId, ?int $customerId = null): ApprovalItem
    {
        $approvalItem = ApprovalItem::where('id', $approvalItemId)
            ->whereHas('approval', function ($query) use ($companyId, $customerId) {
                $query->where('company_id', $companyId);

                if ($customerId !== null) {
                    $query->where('customer_id', $customerId);
                }
            })
            ->lockForUpdate()
            ->first();

        if (!$approvalItem) {
            throw new \Exception('Approval item is already sold/returned or not available.');
        }

        if (ApprovalItemAvailability::availableQuantity($approvalItem) <= 0) {
            throw new \Exception('Approval item is already sold/returned or has no remaining quantity.');
        }

        return $approvalItem;
    }

    public static function lockSaleItemForReturn(int $companyId, int $saleItemId, ?int $requestedQuantity = null): SaleItem
    {
        $saleItem = SaleItem::where('id', $saleItemId)
            ->whereHas('sale', fn($query) => $query->where('company_id', $companyId))
            ->lockForUpdate()
            ->first();

        if (!$saleItem) {
            throw new \Exception("Sale item not found: {$saleItemId}");
        }

        $returnedQuantity = Schema::hasColumn('sale_return_items', 'qty')
            ? (int) SaleReturnItem::where('sale_item_id', $saleItem->id)->sum('qty')
            : (SaleReturnItem::where('sale_item_id', $saleItem->id)->exists() ? max(1, (int) ($saleItem->qty ?? 1)) : 0);
        $soldQuantity = max(1, (int) ($saleItem->qty ?? 1));
        $requestedQuantity = max(1, (int) ($requestedQuantity ?? $soldQuantity));
        if ($returnedQuantity + $requestedQuantity > $soldQuantity) {
            throw new \Exception('Return quantity exceeds the remaining pieces for this sale item.');
        }

        return $saleItem;
    }

    public static function lockApprovalItemForReturn(int $companyId, int $approvalItemId, ?int $requestedQuantity = null): ApprovalItem
    {
        $approvalItem = ApprovalItem::where('id', $approvalItemId)
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhereRaw('LOWER(TRIM(status)) = ?', ['pending']);
            })
            ->whereHas('approval', fn($query) => $query->where('company_id', $companyId))
            ->lockForUpdate()
            ->first();

        if (!$approvalItem) {
            throw new \Exception('Approval item is already sold/returned or not available for return.');
        }

        $approvedQuantity = max(1, (int) ($approvalItem->qty ?? 1));
        $requestedQuantity = max(1, (int) ($requestedQuantity ?? $approvedQuantity));
        $returnedQuery = SaleReturnItem::query();
        if (Schema::hasColumn('sale_return_items', 'approval_item_id')) {
            $returnedQuery->where('approval_item_id', $approvalItem->id);
        } elseif (Schema::hasColumn('sale_return_items', 'itemset_id')) {
            $returnedQuery->where('itemset_id', $approvalItem->itemset_id);
        } else {
            $returnedQuery->whereRaw('1 = 0');
        }
        $returnedQuantity = Schema::hasColumn('sale_return_items', 'qty')
            ? (int) $returnedQuery->sum('qty')
            : ($returnedQuery->exists() ? $approvedQuantity : 0);

        if ($returnedQuantity + $requestedQuantity > $approvedQuantity) {
            throw new \Exception('Return quantity exceeds the remaining pieces for this approval item.');
        }

        return $approvalItem;
    }

    private static function itemLabel(ItemSet $itemSet): string
    {
        return (string) ($itemSet->qr_code ?: $itemSet->barcode ?: $itemSet->HUID ?: $itemSet->id);
    }
}
