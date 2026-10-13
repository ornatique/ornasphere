<?php

namespace App\Services;

use App\Models\{Customer, Sale, SaleItem, SaleReturn, SaleReturnItem, ItemSet, CustomerAdvanceLedger};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CustomerSalesReturn
{
    public function save(int $companyId, int $customerId, array $ids, string $date, string $reason, ?int $userId): SaleReturn
    {
        return DB::transaction(function () use ($companyId, $customerId, $ids, $date, $reason, $userId) {
            $hasItemsetId = Schema::hasColumn('sale_return_items', 'itemset_id');
            Customer::where('company_id', $companyId)->lockForUpdate()->findOrFail($customerId);
            $ids = collect($ids)->map(fn ($id) => (int)$id)->unique()->sort()->values();
            $items = SaleItem::whereIn('id', $ids)->whereHas('sale', fn ($q) => $q->where('company_id', $companyId)->where('customer_id', $customerId))->orderBy('id')->lockForUpdate()->get();
            if ($ids->isEmpty() || $items->count() !== $ids->count()) $this->fail('Select sold items belonging to this customer.');
            if (SaleReturnItem::whereIn('sale_item_id', $ids)->exists()) $this->fail('An item has already been returned. Reload the sold items.');
            $return = SaleReturn::create(['company_id'=>$companyId, 'created_by'=>$userId, 'source_type'=>'sale',
                'sale_id'=>$items->pluck('sale_id')->unique()->count() === 1 ? $items->first()->sale_id : null,
                'return_voucher_no'=>VoucherSeries::next($companyId, 'SR'), 'return_date'=>$date, 'remarks'=>$reason, 'return_total'=>0]);
            $total = 0;
            foreach ($items->groupBy('sale_id') as $saleId => $group) {
                $sale = Sale::where('company_id', $companyId)->lockForUpdate()->findOrFail($saleId);
                $all = $sale->saleItems()->get();
                $alreadyReturned = SaleReturnItem::whereIn('sale_item_id', $all->pluck('id'))->get();
                $remaining = $all->whereNotIn('id', $alreadyReturned->pluck('sale_item_id'));
                // Allocate the saved outstanding sale value; never reconstruct it from today's metal balance.
                $base = (float)$remaining->sum(fn ($i) => (float)$i->labour_amount + (float)$i->other_amount);
                $metal = (float)$remaining->sum('metal_amount');
                $payable = (float)$sale->net_total;
                $metalPayable = max(0, $payable - $base);
                if ($payable < $base - .02 || $metalPayable > $metal + .02) $this->fail('Sale totals require reconciliation before returning these items.');
                $cash = 0;
                foreach ($group as $item) {
                    $set = ItemSet::where('company_id', $companyId)->whereKey($item->itemset_id)->lockForUpdate()->first();
                    if (!$set || !$set->is_sold || (int)($item->qty ?? 1) !== 1) $this->fail('Only available sold labels with quantity one can be returned.');
                    if (SaleItem::where('itemset_id', $set->id)->where('id', '>', $item->id)->exists()) $this->fail('This label belongs to a newer sale. Reload the list.');
                    $value = round((float)$item->labour_amount + (float)$item->other_amount + ($metal > 0 ? $metalPayable * (float)$item->metal_amount / $metal : 0), 2);
                    $returnItem = ['sale_return_id'=>$return->id, 'sale_item_id'=>$item->id,
                        'return_amount'=>$value, 'remarks'=>'Original bill '.$sale->voucher_no];
                    // Older installations resolve the label through sale_item_id instead.
                    if ($hasItemsetId) $returnItem['itemset_id'] = $set->id;
                    SaleReturnItem::create($returnItem);
                    $set->update(['is_sold'=>0]);
                    $cash += $value;
                }
                if ($group->count() === $remaining->count()) {
                    $difference = round($payable - $cash, 2);
                    $last = $return->items()->where('sale_item_id', $group->last()->id)->firstOrFail();
                    $last->update(['return_amount'=>round($last->return_amount + $difference, 2)]);
                    $cash = $payable;
                }
                $paid = (float)$sale->received_amount - (float)$sale->paid_amount;
                $credit = max(0, $paid - ($payable - $cash)) - max(0, $paid - $payable);
                $sale->update(['net_total'=>max(0, round($payable - $cash, 2))]);
                if ($credit > 0) $this->ledger($return, $customerId, $date, $userId, ['cash_in'=>round($credit, 2), 'remarks'=>'Cash credit from '.$sale->voucher_no.'; no cash refund paid']);
                $adjustments = CustomerAdvanceLedger::where('company_id',$companyId)->where('customer_id',$customerId)
                    ->where('reference_type','sale')->where('reference_id',$saleId)->where('entry_type','purchase_adjust_metal')->get();
                foreach ($adjustments->groupBy('metal_type') as $type => $entries) {
                    $eligible = $all->filter(fn ($i) => $this->metal($i) === $type);
                    $fine = (float)$eligible->sum('fine_weight');
                    $returnedFine = (float)$group->filter(fn ($i) => $this->metal($i) === $type)->sum('fine_weight');
                    $creditFine = $fine > 0 ? round(min(1, (float)$entries->sum('metal_out') / $fine) * $returnedFine, 3) : 0;
                    if ($creditFine > 0) $this->ledger($return, $customerId, $date, $userId, ['metal_type'=>$type, 'metal_in'=>$creditFine, 'remarks'=>'Fine metal reversal for '.$sale->voucher_no]);
                }
                $total += $cash;
            }
            $return->update(['return_total'=>round($total, 2)]);
            return $return;
        });
    }

    private function metal(SaleItem $item): string
    {
        $metal = strtolower((string)($item->itemset?->item?->metal ?? $item->product?->metal));
        return str_contains($metal, 'gold') ? 'gold' : (str_contains($metal, 'silver') ? 'silver' : 'other');
    }

    private function ledger(SaleReturn $return, int $customerId, string $date, ?int $userId, array $values): void
    {
        CustomerAdvanceLedger::create(array_merge(['company_id'=>$return->company_id, 'customer_id'=>$customerId,
            'entry_date'=>$date, 'entry_type'=>'sales_return', 'cash_in'=>0, 'cash_out'=>0, 'metal_in'=>0, 'metal_out'=>0,
            'reference_type'=>'sale_return', 'reference_id'=>$return->id, 'created_by'=>$userId], $values));
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['sale_item_ids'=>$message]);
    }
}
