<?php

namespace App\Services;

use App\Models\ApprovalItem;
use Illuminate\Support\Collection;

/** Read-only outstanding quantities and values shared by web and API reports. */
class ApprovalOutstanding
{
    public static function items(Collection $items): Collection
    {
        return $items->map(function (ApprovalItem $item) {
            $remaining = ApprovalItemAvailability::availableQuantity($item);
            if ($remaining <= 0) {
                return null;
            }

            // Stored approval weights/amounts are row totals, not per-piece values.
            // Never change the original model or persist this report projection.
            $row = clone $item;
            $quantity = (int) ($item->qty ?? 1);
            foreach (['gross_weight', 'other_weight', 'net_weight', 'total_fine_weight',
                'metal_amount', 'labour_amount', 'other_amount', 'total_amount'] as $field) {
                $row->{$field} = ApprovalItemAvailability::remainingValue($item->{$field} ?? 0, $quantity, $remaining);
            }
            $row->qty = $remaining;
            $row->status = 'pending';

            return $row;
        })->filter()->values();
    }

    public static function headers(Collection $headers): Collection
    {
        return $headers->map(function ($header) {
            $items = self::items($header->items);
            $row = clone $header;
            // Keep the existing response field name for API/DataTables compatibility.
            $row->pending_items_count = (int) $items->sum('qty');
            foreach (['gross_weight', 'other_weight', 'net_weight', 'metal_amount',
                'labour_amount', 'other_amount', 'total_amount'] as $field) {
                $row->{'pending_'.$field} = (float) $items->sum($field);
            }
            $row->pending_fine_weight = (float) $items->sum('total_fine_weight');
            foreach (['purity', 'waste_percent', 'metal_rate', 'labour_rate'] as $field) {
                $row->{'pending_'.$field} = $row->pending_net_weight > 0
                    ? $items->sum(fn ($item) => (float) $item->net_weight * (float) $item->{$field}) / $row->pending_net_weight
                    : 0.0;
            }

            return $row;
        })->filter(fn ($row) => $row->pending_items_count > 0)->values();
    }

    public static function totals(Collection $headers): array
    {
        $totals = ['qty_pcs' => (int) $headers->sum('pending_items_count')];
        foreach (['gross_weight', 'other_weight', 'net_weight', 'fine_weight',
            'metal_amount', 'labour_amount', 'other_amount'] as $field) {
            $totals[$field] = (float) $headers->sum('pending_'.$field);
        }
        $totals['net_total'] = (float) $headers->sum('pending_total_amount');

        return $totals;
    }
}
