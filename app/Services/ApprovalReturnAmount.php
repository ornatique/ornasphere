<?php

namespace App\Services;

class ApprovalReturnAmount
{
    public static function resolve($item): float
    {
        if (!$item) return 0;
        if ((float) $item->sale_price > 0) return round((float) $item->sale_price, 2);
        if ((float) $item->total_amount > 0) return round((float) $item->total_amount, 2);
        $net = (float) $item->net_weight;
        $fine = $item->total_fine_weight ?? ($net * (float) ($item->net_purity ?? $item->purity) / 100);
        $metal = (float) $item->metal_amount;
        $labour = (float) $item->labour_amount;
        if ($metal <= 0) $metal = (float) $fine * (float) $item->metal_rate;
        if ($labour <= 0) $labour = $net * (float) $item->labour_rate;
        return round($metal + $labour + (float) $item->other_amount, 2);
    }
}
