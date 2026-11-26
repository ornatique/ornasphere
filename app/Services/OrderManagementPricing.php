<?php
namespace App\Services;

class OrderManagementPricing
{
    public const INPUTS = ['gross_weight', 'other_weight', 'purity', 'waste_percent', 'metal_rate', 'labour_rate', 'other_amount', 'remarks'];
    public const SNAPSHOT = ['gross_weight', 'other_weight', 'net_weight', 'purity', 'waste_percent', 'metal_rate', 'labour_rate', 'other_amount', 'sale_price', 'remarks'];

    public static function selectedPrice(array $defaults, string $mode): float
    {
        $sale = (float) ($defaults['sale_unit_price'] ?? $defaults['sale_price'] ?? 0);
        $wholesale = (float) ($defaults['wholesale_unit_price'] ?? $defaults['wholesale_price'] ?? 0);
        $chosen = $mode === 'wholesale' ? $wholesale : $sale;
        $fallback = $mode === 'wholesale' ? $sale : $wholesale;
        return round($chosen > 0 ? $chosen : max(0, $fallback), 2);
    }

    public static function calculate(array $line, array $defaults, ?string $priceMode = null): array
    {
        $value = static fn ($key, $precision = 3) => round((float) ($line[$key] ?? $defaults[$key] ?? 0), $precision);
        $gross = $value('gross_weight');
        $other = $value('other_weight');
        $net = round(max(0, $gross - $other), 3);
        $purity = $value('purity'); $waste = $value('waste_percent');
        $metalRate = $value('metal_rate', 2); $labourRate = $value('labour_rate', 2);
        $otherAmount = $value('other_amount', 2);
        // Fixed sale prices come from the stored label, never from the submitted browser payload.
        $salePrice = $priceMode === null ? round((float) ($defaults['billing_sale_price'] ?? $defaults['sale_price'] ?? 0), 2) : self::selectedPrice($defaults, $priceMode);
        $fine = $net * ($purity + $waste) / 100;
        $metal = $net * $metalRate; $labour = $net * $labourRate;
        $total = ($metal + $labour + $otherAmount) * $line['quantity'];
        if ($salePrice > 0) { $metal = $labour = $otherAmount = $fine = 0; $total = $salePrice * $line['quantity']; }
        return ['gross_weight'=>$gross, 'other_weight'=>$other, 'net_weight'=>$net,
            'purity'=>$purity, 'waste_percent'=>$waste, 'net_purity'=>round($purity + $waste, 3), 'total_fine_weight'=>round($fine, 3),
            'metal_rate'=>$metalRate, 'metal_amount'=>round($metal, 2), 'labour_rate'=>$labourRate, 'labour_amount'=>round($labour, 2),
            'other_amount'=>$otherAmount, 'sale_price'=>$salePrice, 'total_amount'=>round($total, 2), 'remarks'=>$line['remarks'] ?? null];
    }
}
