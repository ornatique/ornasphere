<?php

namespace App\Services;

use App\Models\ApprovalItem;
use App\Models\ItemSet;
use App\Models\SaleItem;
use App\Models\SaleReturnItem;

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

        if (!$allowAlreadyUsed && (int) $itemSet->is_sold === 1) {
            throw new \Exception('This item is already used in another voucher: ' . self::itemLabel($itemSet));
        }

        return $itemSet;
    }

    public static function lockApprovalItemForSale(int $companyId, int $approvalItemId, ?int $customerId = null): ApprovalItem
    {
        $approvalItem = ApprovalItem::where('id', $approvalItemId)
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhereRaw('LOWER(TRIM(status)) = ?', ['pending']);
            })
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

        return $approvalItem;
    }

    public static function lockSaleItemForReturn(int $companyId, int $saleItemId): SaleItem
    {
        $saleItem = SaleItem::where('id', $saleItemId)
            ->whereHas('sale', fn($query) => $query->where('company_id', $companyId))
            ->lockForUpdate()
            ->first();

        if (!$saleItem) {
            throw new \Exception("Sale item not found: {$saleItemId}");
        }

        if (SaleReturnItem::where('sale_item_id', $saleItem->id)->exists()) {
            throw new \Exception('This sale item is already returned.');
        }

        return $saleItem;
    }

    public static function lockApprovalItemForReturn(int $companyId, int $approvalItemId): ApprovalItem
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

        return $approvalItem;
    }

    private static function itemLabel(ItemSet $itemSet): string
    {
        return (string) ($itemSet->qr_code ?: $itemSet->barcode ?: $itemSet->HUID ?: $itemSet->id);
    }
}
