<?php

namespace App\Services;

use App\Models\ApprovalItem;
use App\Models\SaleItem;
use App\Models\SaleReturnItem;
use Illuminate\Support\Facades\Schema;

/** Computes the unsold portion of an approval item from its linked sale rows. */
class ApprovalItemAvailability
{
    public static function soldQuantity(ApprovalItem $item): int
    {
        if (!Schema::hasColumn('sale_items', 'qty')) {
            return 0;
        }

        // Prefer the explicit approval link. Combining this with itemset_id
        // counts unrelated/legacy sale lines for the same label and can make
        // still-pending approval quantity disappear from the sale picker.
        if (Schema::hasColumn('sale_items', 'approval_item_id')) {
            $linkedQty = (int) SaleItem::where('approval_item_id', $item->id)->sum('qty');
            if ($linkedQty > 0 || empty($item->itemset_id) || !Schema::hasColumn('sale_items', 'itemset_id')) {
                return max(0, $linkedQty);
            }

            // A pending approval row is the quantity still with the customer.
            // Do not subtract ordinary/unlinked sales for its itemset: older
            // sales may refer to the same label without consuming this approval.
            // Keep the legacy fallback only for rows explicitly marked sold,
            // where old installations may not have saved approval_item_id.
            if (strtolower(trim((string) $item->status)) !== 'sold') {
                return 0;
            }

            return max(0, (int) SaleItem::whereNull('approval_item_id')
                ->where('itemset_id', $item->itemset_id)
                ->sum('qty'));
        }

        if (!Schema::hasColumn('sale_items', 'itemset_id') || empty($item->itemset_id)) {
            return 0;
        }

        return max(0, (int) SaleItem::where('itemset_id', $item->itemset_id)->sum('qty'));
    }

    public static function availableQuantity(ApprovalItem $item): int
    {
        $sold = self::soldQuantity($item);
        $returned = self::returnedQuantity($item);
        $status = strtolower(trim((string) $item->status));

        // Respect legacy/manual full-sale status rows that have no linked sale detail.
        if ($status === 'sold' && $sold === 0 && $returned === 0) {
            return 0;
        }

        // Older partial-return flows marked the whole approval row "returned".
        // Use the recorded return quantity so any unreturned pieces stay saleable.
        if ($status === 'returned' && $returned === 0) {
            return 0;
        }

        // Earlier API partial returns kept the original quantity on the returned
        // row while also creating a separate row for the remaining pieces. The
        // returned row must not appear in sale or estimate approval pickers.
        if ($status === 'returned' && $returned > 0 && $returned < (int) ($item->qty ?? 1)
            && $item->itemset_id && ApprovalItem::query()
                ->where('approval_id', $item->approval_id)
                ->where('itemset_id', $item->itemset_id)
                ->where('id', '>', $item->id)
                ->where('qty', '<=', (int) $item->qty - $returned)
                ->exists()) {
            return 0;
        }

        return max(0, max(0, (int) ($item->qty ?? 1)) - $sold - $returned);
    }

    public static function returnedQuantity(ApprovalItem $item): int
    {
        if (!Schema::hasTable('sale_return_items')) {
            return 0;
        }

        $query = SaleReturnItem::query();
        $hasApprovalItemId = Schema::hasColumn('sale_return_items', 'approval_item_id');
        $hasItemsetId = Schema::hasColumn('sale_return_items', 'itemset_id') && !empty($item->itemset_id);
        if ($hasApprovalItemId && $hasItemsetId) {
            $includeLegacyItemsetReturn = true;
            if (strtolower(trim((string) $item->status)) !== 'returned') {
                $hasReturnedSibling = ApprovalItem::query()
                    ->where('itemset_id', $item->itemset_id)
                    ->where('id', '<>', $item->id)
                    ->whereRaw('LOWER(TRIM(status)) = ?', ['returned'])
                    ->exists();
                // Partial returns create a returned source row plus a pending
                // remainder with the same itemset. Legacy return rows that lack
                // approval_item_id belong to the source, so don't subtract them
                // a second time from the pending remainder.
                $includeLegacyItemsetReturn = !$hasReturnedSibling;
            }

            $query->where(function ($nested) use ($item, $includeLegacyItemsetReturn) {
                $nested->where('approval_item_id', $item->id);
                if ($includeLegacyItemsetReturn) {
                    $nested->orWhere(function ($legacy) use ($item) {
                        $legacy->whereNull('approval_item_id')
                            ->where('itemset_id', $item->itemset_id);
                    });
                }
            });
        } elseif ($hasApprovalItemId) {
            $query->where('approval_item_id', $item->id);
        } elseif ($hasItemsetId) {
            $query->where('itemset_id', $item->itemset_id);
        } else {
            return 0;
        }

        // A return linked to a sale item returns stock; it does not consume the
        // customer's remaining approval quantity.
        if (Schema::hasColumn('sale_return_items', 'sale_item_id')) {
            $query->whereNull('sale_item_id');
        }

        if (Schema::hasColumn('sale_return_items', 'qty')) {
            return max(0, (int) $query->sum('qty'));
        }

        return $query->exists() ? max(0, (int) ($item->qty ?? 1)) : 0;
    }

    public static function remainingValue($total, int $originalQty, int $availableQty): float
    {
        if ($originalQty <= 0 || $availableQty <= 0) {
            return 0.0;
        }

        return (float) $total * $availableQty / $originalQty;
    }

    /** Recompute a pending/sold status after a linked sale line is created or removed. */
    public static function syncStatus(ApprovalItem $item): int
    {
        $available = self::availableQuantity($item);
        $status = $available > 0 ? 'pending' : 'sold';
        if (strtolower(trim((string) $item->status)) !== $status) {
            $item->update(['status' => $status]);
        }

        return $available;
    }

    /** Recompute voucher status from quantities, including partial sales of one row. */
    public static function syncHeaderStatus(int $approvalId): void
    {
        $items = ApprovalItem::where('approval_id', $approvalId)->get();
        if ($items->isEmpty()) {
            return;
        }

        $total = $items->sum(fn (ApprovalItem $item) => max(0, (int) ($item->qty ?? 1)));
        $remaining = $items->sum(fn (ApprovalItem $item) => self::availableQuantity($item));
        $status = $remaining <= 0
            ? 'closed'
            : ($remaining < $total ? 'partial' : 'open');

        $items->first()->approval()->update(['status' => $status]);
    }
}
