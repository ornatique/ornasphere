<?php

namespace App\Services;

use App\Models\ApprovalItem;
use App\Models\ItemSet;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class FixedSalePrice
{
    /** An explicit API price or charge breakdown takes precedence over a source price. */
    public static function saleRequestPrice(array $row): array
    {
        if (array_key_exists('sale_price', $row)) {
            $price = $row['sale_price'];
            if ($price === null || $price === '' || (is_numeric($price) && (float) $price === 0.0)) {
                return ['sale_price' => null];
            }
            return ['sale_price' => self::amount($price)];
        }

        foreach (['metal_amount', 'metal_amt', 'labour_amount', 'labour_amt', 'sale_labour_amount', 'other_amount', 'other_amt'] as $key) {
            if (isset($row[$key]) && is_numeric($row[$key]) && (float) $row[$key] > 0) {
                return ['sale_price' => null];
            }
        }

        return [];
    }

    public static function amount($price, int $quantity = 1): float
    {
        if (!is_numeric($price) || !is_finite((float) $price) || (float) $price <= 0 || (float) $price > 9999999999999.99) {
            throw ValidationException::withMessages(['sale_price' => 'Enter a positive Sale Price.']);
        }

        return round((float) $price * max(1, $quantity), 2);
    }

    /** Capture the label price once; status changes must never reprice a voucher. */
    public static function saveSnapshot(Model $line): void
    {
        if (!$line->exists && !array_key_exists('sale_price', $line->getAttributes())) {
            $source = null;
            if ($line instanceof SaleItem && $line->approval_item_id) {
                $source = ApprovalItem::find($line->approval_item_id);
            } elseif ($line->itemset_id) {
                $source = ItemSet::find($line->itemset_id);
            }
            if ($source && (float) ($source instanceof ItemSet ? $source->billing_sale_price : $source->sale_price) > 0) {
                $line->sale_price = $source instanceof ItemSet ? $source->billing_sale_price : $source->sale_price;
            }
        }

        if ((float) $line->sale_price <= 0) {
            return;
        }

        $quantity = ($line instanceof SaleItem || $line instanceof ApprovalItem)
            ? (int) $line->qty
            : 1;
        $line->total_amount = self::amount($line->sale_price, $quantity);
        foreach (['metal_rate', 'metal_amount', 'labour_rate', 'labour_amount', 'other_amount'] as $column) {
            $line->{$column} = 0;
        }
        $line->{$line instanceof SaleItem ? 'fine_weight' : 'total_fine_weight'} = 0;
        $line->other_charge_details = null;
    }
}
