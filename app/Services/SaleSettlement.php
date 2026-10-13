<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerAdvanceLedger;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

class SaleSettlement
{
    public function adjustmentSummary(Sale $sale): array
    {
        $summary = ['cash'=>0.0, 'gold'=>0.0, 'silver'=>0.0, 'other'=>0.0];
        if (!$sale->id || !$sale->company_id) return $summary;
        foreach (CustomerAdvanceLedger::where('company_id',$sale->company_id)->where('customer_id',$sale->customer_id)
            ->where('reference_type','sale')->where('reference_id',$sale->id)->get() as $entry) {
            if ($entry->entry_type === 'sale_adjust_cash') $summary['cash'] += (float)$entry->cash_out;
            // Metal out includes unpaid weight. Only credit actually consumed is an adjustment.
            if ($sale->use_silver_balance && $entry->entry_type === 'purchase_adjust_metal'
                && in_array($entry->metal_type, ['gold','silver','other'], true)
                && preg_match('/credit adjusted: ([0-9.]+) g/', (string)$entry->remarks, $match)) {
                $summary[$entry->metal_type] += min((float)$entry->metal_out, max(0, (float)$match[1]));
            }
        }
        return $summary;
    }

    public function calculate(iterable $items, array $balances, bool $adjust, float $received = 0): array
    {
        $remaining = $balances;
        $out = ['gold'=>0.0, 'silver'=>0.0, 'other'=>0.0];
        $adjusted = $out;
        $cash = 0.0;
        $lines = [];
        foreach ($items as $row) {
            $metal = strtolower(trim((string) ($row['metal'] ?? 'silver')));
            $metal = str_contains($metal, 'gold') ? 'gold' : (str_contains($metal, 'silver') || $metal === '' ? 'silver' : 'other');
            $fine = max(0, (float) ($row['fine_weight'] ?? 0));
            $amount = (float) ($row['metal_rate'] ?? 0) > 0 ? max(0, (float) ($row['metal_amount'] ?? 0)) : 0;
            $covered = $adjust ? min($fine, max(0, (float) ($remaining[$metal] ?? 0))) : 0;
            $adjusted[$metal] += $covered;
            // Unpriced metal remains payable in grams, never converted into cash.
            $debit = $amount > 0 ? $covered : $fine;
            $remaining[$metal] = (float) ($remaining[$metal] ?? 0) - $debit;
            $out[$metal] += $debit;
            $lineCash = (float) ($row['labour_amount'] ?? 0) + (float) ($row['other_amount'] ?? 0);
            $lineCash += $fine > 0 ? $amount * (1 - $covered / $fine) : $amount;
            $lineCash = round($lineCash, 2);
            $cash += $lineCash;
            $lines[] = $lineCash;
        }
        $cash = round($cash, 2);
        $used = $adjust ? round(min(max(0, (float) ($balances['cash'] ?? 0)), max(0, $cash - $received)), 2) : 0;
        return ['gross_cash'=>$cash, 'cash_used'=>$used, 'net_cash'=>round($cash-$used, 2), 'metal_out'=>array_map(fn($v)=>round($v,3),$out), 'metal_adjusted'=>array_map(fn($v)=>round($v,3),$adjusted), 'line_cash'=>$lines];
    }

    private function balancesBeforeSale(Sale $sale): array
    {
        $base = CustomerAdvanceLedger::where('company_id', $sale->company_id)->where('customer_id', $sale->customer_id)
            ->whereDate('entry_date', '<=', $sale->sale_date)
            ->where(fn($q)=>$q->whereNull('reference_type')->orWhere('reference_type','!=','sale')->orWhereNull('reference_id')->orWhere('reference_id','!=',$sale->id));
        $balances = ['cash'=>(float) (clone $base)->selectRaw('COALESCE(SUM(cash_in-cash_out),0) as bal')->value('bal')];
        foreach ((clone $base)->whereNotNull('metal_type')->selectRaw('metal_type, SUM(metal_in-metal_out) as bal')->groupBy('metal_type')->get() as $metal) {
            $balances[strtolower($metal->metal_type)] = (float) $metal->bal;
        }
        return $balances;
    }

    public function preview(Sale $sale): array
    {
        $balances = $this->balancesBeforeSale($sale);
        $rows = $sale->saleItems()->with(['itemset.item','product'])->orderBy('id')->get()->map(function ($row) {
            $item = optional($row->itemset)->item ?: $row->product;
            return array_merge($row->getAttributes(), ['metal'=>optional($item)->metal ?? 'silver']);
        });
        return $this->calculate($rows, $balances, (bool) $sale->use_silver_balance, (float)$sale->received_amount-(float)$sale->paid_amount);
    }

    public function sync(Sale $sale, ?int $userId): void
    {
        DB::transaction(function () use ($sale, $userId) {
            Customer::where('company_id',$sale->company_id)->whereKey($sale->customer_id)->lockForUpdate()->firstOrFail();
            $balances = $this->balancesBeforeSale($sale);
            $hasFineCredit = collect(['gold', 'silver', 'other'])->contains(fn($metal) => ($balances[$metal] ?? 0) > 0);
            if ((float) $sale->received_amount <= 0 && !$hasFineCredit && $sale->use_silver_balance) {
                $sale->update(['use_silver_balance'=>false]);
            }
            $result = $this->preview($sale);
            $base = CustomerAdvanceLedger::where('company_id',$sale->company_id)->where('reference_type','sale')->where('reference_id',$sale->id);
            $base->whereIn('entry_type',['purchase_adjust_metal','sale_adjust_cash'])->delete();
            $common = ['company_id'=>$sale->company_id,'customer_id'=>$sale->customer_id,'entry_date'=>$sale->sale_date,
                'reference_type'=>'sale','reference_id'=>$sale->id,'created_by'=>$userId,'cash_in'=>0,'cash_out'=>0,'metal_in'=>0,'metal_out'=>0,'rate'=>0];
            foreach ($result['metal_out'] as $metal=>$weight) {
                if ($weight > 0) CustomerAdvanceLedger::create(array_merge($common,['entry_type'=>'purchase_adjust_metal','metal_type'=>$metal,'metal_out'=>$weight,'remarks'=>'Sale metal settlement (credit adjusted: '.number_format($result['metal_adjusted'][$metal], 3, '.', '').' g)']));
            }
            // Keep a zero entry as the settlement snapshot marker for unchanged PDFs.
            CustomerAdvanceLedger::create(array_merge($common,['entry_type'=>'sale_adjust_cash','cash_out'=>$result['cash_used'],'remarks'=>'Cash advance applied to sale']));
            $sale->update(['net_total'=>$result['net_cash']]);
            foreach ($sale->saleItems()->orderBy('id')->get() as $index=>$line) {
                $line->update(['total_amount'=>$result['line_cash'][$index]]);
            }
            $sale->unsetRelation('saleItems');
        });
    }
}
