<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 8mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #111; margin: 0; }
        .sheet { width: 100%; border: 1px solid #000; }
        .company-head { border-bottom: 1px solid #000; text-align: center; padding: 10px 8px 8px; }
        .company-name { font-size: 22px; font-weight: 700; line-height: 1.1; }
        .company-meta { margin-top: 6px; font-size: 10px; }
        .sheet-title { border-bottom: 1px solid #000; padding: 6px 8px; font-weight: 700; font-size: 14px; }
        .meta { width: 100%; border-collapse: collapse; border-bottom: 1px solid #000; }
        .meta td { border-right: 1px solid #000; padding: 4px 6px; vertical-align: top; }
        .meta td:last-child { border-right: none; }
        .summary-grid { width: 100%; border-collapse: collapse; border: 1px solid #000; margin-top: 6px; table-layout: fixed; }
        .summary-grid td { border: 1px solid #000; padding: 5px 7px; font-size: 9px; vertical-align: top; width: 25%; height: 34px; }
        .metric-card { width: 100%; }
        .metric-top { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .metric-top td { border: 0; padding: 0; height: auto; vertical-align: top; }
        .metric-label { font-weight: 700; white-space: nowrap; width: 58%; }
        .metric-top td.metric-value { text-align: right; white-space: nowrap; width: 42%; font-size: 11px; font-weight: 700; background: #eeeeee; padding: 2px 3px; }
        .metric-status { margin-top: 3px; font-weight: 700; white-space: nowrap; }
        .voucher-grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .voucher-grid th, .voucher-grid td { border: 1px solid #000; padding: 3px 3px; vertical-align: top; word-break: normal; }
        .voucher-grid th { white-space: nowrap; background: #efefef; font-weight: 700; text-align: center; font-size: 8.4px; }
        .voucher-grid td { white-space: nowrap; font-size: 8.4px; }
        .voucher-grid td:nth-child(2) { white-space: normal; }
        .amount-cell { white-space: nowrap; font-size: 7.6px; padding-left: 1px; padding-right: 1px; }
        .voucher-grid tfoot th { font-size: 7px; font-weight: 700; background: #e2e2e2; padding: 6px 2px; vertical-align: top; line-height: 1.4; }
        .voucher-grid tfoot th.text-right { text-align: right; }
        .voucher-grid tfoot th.amount-cell { font-size: 7px; padding-left: 2px; padding-right: 2px; white-space: nowrap; }
        .total-unit { display:block; font-size:6.5px; font-weight:400; margin-top:2px; }
        .item-remark { margin-top: 2px; font-size: 8px; color: #333; }
        .payment-grid { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 4px; }
        .payment-grid th, .payment-grid td { border: 1px solid #000; padding: 4px 6px; }
        .payment-grid th { background: #efefef; font-weight: 700; text-align: left; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .grand-total { text-align: right; font-weight: 700; padding: 6px 8px 8px; }
        .grand-total-grid { width: 300px; margin-left: auto; border-collapse: collapse; table-layout: fixed; }
        .grand-total-grid td { border: 0; padding: 1px 8px; white-space: nowrap; line-height: 1.15; }
        .grand-total-grid .grand-label { width: 94px; padding-left: 0; text-align: right; vertical-align: middle; }
        .grand-total-grid .grand-head { text-align: center; font-size: 10px; padding-bottom: 2px; border-bottom: 1px solid #000; }
        .grand-total-grid .grand-metal { border-left: 1px solid #000; }
        .grand-total .grand-number { font-size: 11.5px; text-align: center; padding-top: 0; }
        .unit-circle {
            display: inline-block;
            min-width: 14px;
            height: 14px;
            line-height: 14px;
            text-align: center;
            border: 1px solid #000;
            border-radius: 50%;
            font-size: 9px;
            font-weight: 700;
            margin-left: 3px;
            vertical-align: middle;
        }
    </style>
</head>
<body>
@php
    $name = optional($sale->customer)->name ?? '-';
    $city = optional($sale->customer)->city ?? '-';
    $customer = $sale->customer;
    $contact = collect([
        optional($customer)->mobile_no,
        optional($customer)->contact_person1_phone,
        optional($customer)->contact_person2_phone,
        optional($customer)->mobile,
        optional($customer)->phone,
    ])->first(fn ($value) => filled($value)) ?? '-';
    $companyName = $company->name ?? '';
    $companyAddress = collect([
        $company->address_1 ?? null,
        $company->address_2 ?? null,
        $company->city ?? null,
        $company->state ?? null,
        $company->postcode ?? null,
        $company->country ?? null,
    ])->filter()->implode(', ');
    $companyEmail = $company->email ?? '';
    $receivedAmount = (float)($sale->received_amount ?? 0);
    $advanceSummary = $advanceSummary ?? null;
    $saleAdvanceUsage = $saleAdvanceUsage ?? null;
    if (!$advanceSummary && !empty($sale->customer_id) && !empty($sale->company_id)) {
        $advanceBase = \App\Models\CustomerAdvanceLedger::query()
            ->where('company_id', (int) $sale->company_id)
            ->where('customer_id', (int) $sale->customer_id);
        if (!empty($sale->sale_date)) {
            $advanceBase->whereDate('entry_date', '<=', \Carbon\Carbon::parse($sale->sale_date)->toDateString());
        }
        $cashBal = (clone $advanceBase)->selectRaw('COALESCE(SUM(cash_in),0) - COALESCE(SUM(cash_out),0) as bal')->value('bal');
        $metalBal = (clone $advanceBase)->whereNotNull('metal_type')
            ->selectRaw('metal_type, COALESCE(SUM(metal_in),0) - COALESCE(SUM(metal_out),0) as bal')
            ->groupBy('metal_type')
            ->pluck('bal', 'metal_type');
        $advanceSummary = [
            'cash' => (float) $cashBal,
            'gold' => (float) ($metalBal['gold'] ?? 0),
            'silver' => (float) ($metalBal['silver'] ?? 0),
            'other' => (float) ($metalBal['other'] ?? 0),
        ];
    }
    if (!$saleAdvanceUsage && !empty($sale->id) && !empty($sale->company_id)) {
        $usage = \App\Models\CustomerAdvanceLedger::query()
            ->where('company_id', (int) $sale->company_id)
            ->where('reference_type', 'sale')
            ->where('reference_id', (int) $sale->id)
            ->selectRaw('COALESCE(SUM(CASE WHEN metal_type = "silver" THEN metal_out ELSE 0 END),0) as silver_used')
            ->first();
        $saleAdvanceUsage = [
            'silver_used' => (float) ($usage->silver_used ?? 0),
        ];
    }
    $paymentRows = collect($sale->payments ?? [])
        ->filter(fn($p) => (float)($p->amount ?? 0) > 0)
        ->map(function ($p) {
            return [
                'date' => optional($p->paid_on)->format('d-m-Y') ?? \Carbon\Carbon::parse($p->created_at ?? now())->format('d-m-Y'),
                'amount' => (float)($p->amount ?? 0),
                'mode' => (string)($p->payment_mode ?? ''),
                'reference' => (string)($p->payment_reference ?? ''),
            ];
        })
        ->values();
    if ($paymentRows->isEmpty() && $receivedAmount > 0) {
        $paymentRows = collect([[
            'date' => \Carbon\Carbon::parse($sale->sale_date)->format('d-m-Y'),
            'amount' => $receivedAmount,
            'mode' => (string)($sale->payment_mode ?? ''),
            'reference' => (string)($sale->payment_reference ?? ''),
        ]]);
    }
    $paymentTotal = (float) $paymentRows->sum('amount');
    $sumQty = 0; $sumGross = 0; $sumLess = 0; $sumNet = 0; $sumFine = 0; $sumMetalRate = 0; $sumMetalAmount = 0; $sumLabourRate = 0; $sumOther = 0; $sumTotal = 0;
@endphp

<div class="sheet">
    <div class="company-head">
        <div class="company-name">{{ $companyName }}</div>
    </div>
    <div class="sheet-title">Estimate</div>

    <table class="meta">
        <tr>
            <td style="width:56%;">
                <div><strong>Name</strong> : {{ $name }}</div>
                <div style="margin-top:4px;"><strong>City</strong> : {{ $city }}</div>
                @if(trim((string) $sale->remarks) !== '')
                    <div style="margin-top:4px; overflow-wrap:break-word;"><strong>Remarks</strong> : {!! nl2br(e($sale->remarks)) !!}</div>
                @endif
            </td>
            <td style="width:44%;">
                <div><strong>Estimate No</strong> : {{ $sale->voucher_no }}</div>
                <div style="margin-top:4px;"><strong>Date</strong> : {{ \Carbon\Carbon::parse($sale->sale_date)->format('d-m-Y') }}</div>
            </td>
        </tr>
    </table>
    @php
        $cashPayable = (float)($cashPayableTotal ?? ($sale->net_total ?? 0));
        $cashAdvanceApplied = (float) ($saleAdvanceUsage['cash_used'] ?? 0);
        if (!empty($sale->id) && \App\Models\CustomerAdvanceLedger::where('company_id',$sale->company_id)->where('reference_type','sale')->where('reference_id',$sale->id)->where('entry_type','sale_adjust_cash')->exists()) {
            $cashPayable = (float) $sale->net_total + $cashAdvanceApplied;
        }
        $effectiveReceived = $sale->appliedReceivedAmount();
        $pendingRaw = $cashPayable - $effectiveReceived - $cashAdvanceApplied;
        $pendingType = $pendingRaw >= 0 ? 'Debit' : 'Credit';
        $pendingAmount = abs($pendingRaw);
        $advCashRaw = app(\App\Services\PersonOpeningBalance::class)->cashBalance((int) $sale->company_id, (int) $sale->customer_id, !empty($sale->sale_date) ? \Carbon\Carbon::parse($sale->sale_date)->toDateString() : null);
        $advCashType = $advCashRaw >= 0 ? 'Credit' : 'Debit';
        $advCashAbs = abs($advCashRaw);
        $silverUsed = (float)($saleAdvanceUsage['silver_used'] ?? 0);
        $silverDebit = (float)($saleAdvanceUsage['silver_debit'] ?? 0);
        $silverCredit = (float)($saleAdvanceUsage['silver_credit'] ?? 0);
        $fineBalanceAdjustmentEnabled = (bool) ($sale->use_silver_balance ?? false);
        $adjustedGoldFine = (float) data_get($saleAdvanceUsage, 'metal_used.gold', data_get($saleAdvanceUsage, 'gold_used', 0));
        $adjustedSilverFine = (float) data_get($saleAdvanceUsage, 'metal_used.silver', data_get($saleAdvanceUsage, 'silver_used', 0));
        $adjustedOtherFine = (float) data_get($saleAdvanceUsage, 'metal_used.other', data_get($saleAdvanceUsage, 'other_used', 0));
        $adjustedFineTotal = $adjustedGoldFine + $adjustedSilverFine + $adjustedOtherFine;
        $silverBalance = (float)($advanceSummary['silver'] ?? 0);
        $silverBalanceType = $silverBalance < 0 ? 'Debit' : 'Credit';
        $silverBalanceAbs = abs($silverBalance);
        $goldRaw = (float)($advanceSummary['gold'] ?? 0);
        $goldType = $goldRaw >= 0 ? 'Credit' : 'Debit';
        $goldAbs = abs($goldRaw);
        $otherRaw = (float)($advanceSummary['other'] ?? 0);
        $otherType = $otherRaw >= 0 ? 'Credit' : 'Debit';
        $otherAbs = abs($otherRaw);
    @endphp
    <table class="voucher-grid">
        <thead>
            <tr>
                <th style="width:4%;">Sr</th>
                <th style="width:18%; text-align:left;">Item</th>
                <th style="width:6%;">Purity</th>
                <th style="width:4%;">Qty</th>
                <th style="width:7%;">Gross Wt</th>
                <th style="width:6%;">Less Wt</th>
                <th style="width:7%;">Net Wt</th>
                <th style="width:7%;">Waste %</th>
                <th style="width:7%;">Fine Wt</th>
                <th style="width:7%;">Metal Rt</th>
                <th style="width:7%;">Labour Rt</th>
                <th style="width:8%;">Other</th>
                <th style="width:12%;">Total Amt</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->saleItems as $index => $row)
                @php
                    $itemSet = $row->itemset;
                    $item = optional($itemSet)->item ?: $row->product;
                    $labelCode = optional($itemSet)->qr_code ?? '';
                    $itemName = optional($item)->item_name ?? '-';
                    $itemDisplay = trim(($labelCode ? ($labelCode . ' - ') : '') . $itemName);
                    $metalName = (string) (optional($item)->metal ?? optional($item)->metal_type ?? '-');
                    $qty = max(1, (int) ($row->qty ?? 1));
                    $gross = (float) ($row->gross_weight ?? 0);
                    $less = (float) ($row->other_weight ?? 0);
                    $net = (float) ($row->net_weight ?? ($gross - $less));
                    $wastePercent = (float) ($row->waste_percent ?? 0);
                    $fine = (float) ($row->fine_weight ?? 0);
                    $rate = (float) ($row->metal_rate ?? 0);
                    $metalAmount = (float) ($row->metal_amount ?? 0);
                    $labourRate = (float) ($row->labour_rate ?? 0);
                    $other = (float) ($row->other_amount ?? 0);
                    $total = (float) ($row->total_amount ?? 0);
                    $purity = (float) ($row->purity ?? optional($item)->outward_carat ?? 0);

                    $sumQty += $qty; $sumGross += $gross; $sumLess += $less; $sumNet += $net; $sumFine += $fine; $sumMetalRate += $rate; $sumMetalAmount += $metalAmount; $sumLabourRate += $labourRate; $sumOther += $other; $sumTotal += $total;
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>
                        {{ $itemDisplay }}
                        @if(!empty($row->remarks))
                            <div class="item-remark">{{ $row->remarks }}</div>
                        @endif
                    </td>
                    <td class="text-center">{{ rtrim(rtrim(number_format($purity, 3), '0'), '.') }}%</td>
                    <td class="text-center">{{ $qty }}</td>
                    <td class="text-right">{{ number_format($gross, 3) }}</td>
                    <td class="text-right">{{ number_format($less, 3) }}</td>
                    <td class="text-right">{{ number_format($net, 3) }}</td>
                    <td class="text-right">{{ number_format($wastePercent, 2) }}%</td>
                    <td class="text-right">{{ number_format($fine, 3) }}</td>
                    <td class="text-right">{{ number_format($rate, 2) }}</td>
                    <td class="text-right">{{ number_format($labourRate, 2) }}</td>
                    <td class="text-right">{{ number_format($other, 2) }}</td>
                    <td class="text-right amount-cell">Rs&nbsp;{{ number_format($total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th colspan="3" class="text-right">Total</th>
                <th class="text-center">{{ $sumQty }}</th>
                <th class="text-right">{{ number_format($sumGross, 3) }}</th>
                <th class="text-right">{{ number_format($sumLess, 3) }}</th>
                <th class="text-right">{{ number_format($sumNet, 3) }}</th>
                <th></th>
                <th class="text-right">{{ number_format($sumFine, 3) }}<span class="total-unit">Fine (g)</span></th>
                <th></th>
                <th></th>
                <th class="text-right amount-cell">{{ number_format($sumOther, 2) }}<span class="total-unit">Rs</span></th>
                <th class="text-right amount-cell">{{ number_format($sumTotal, 2) }}<span class="total-unit">Rs</span></th>
            </tr>
        </tfoot>
    </table>

    @php
        $grandTotalType = $cashPayable >= 0 ? 'DR' : 'CR';
        $hasMetalRateCharge = $sumMetalRate > 0.000001 || $sumMetalAmount > 0.000001;
        $fineDebit = (float) $sale->saleItems->filter(fn($line)=>(float)($line->metal_rate ?? 0) <= 0)->sum('fine_weight');
        $silverOpening = (float)($saleAdvanceUsage['silver_opening'] ?? ($silverBalance + $silverUsed));
        $hasCustomerAccountActivity =
            abs($advCashRaw) > 0.000001 ||
            abs($goldRaw) > 0.000001 ||
            abs($silverOpening) > 0.000001 ||
            abs($otherRaw) > 0.000001 ||
            abs($silverUsed) > 0.000001 ||
            $receivedAmount > 0.000001 ||
            (float)($sale->paid_amount ?? 0) > 0.000001 ||
            $paymentRows->isNotEmpty();

        $metalSummaryBoxes = [];
        if (abs($goldRaw) > 0.000001) {
            $metalSummaryBoxes[] = ['label' => 'Adv Gold(Fine)', 'value' => number_format($goldAbs, 3), 'status' => $goldType];
        }
        if (abs($otherRaw) > 0.000001) {
            $metalSummaryBoxes[] = ['label' => 'Adv Other(Fine)', 'value' => number_format($otherAbs, 3), 'status' => $otherType];
        }
        $previousPendingSales = ($sale->show_previous_bills ?? false)
            ? collect($previousPendingSales ?? []) : collect();
        $previousQtyTotal = $previousPendingSales->sum('qty');
        $previousAmountTotal = $previousPendingSales->sum('total_amount');
        $previousFineTotal = $previousPendingSales->sum('fine_weight');
        $previousPendingTotal = $previousPendingSales->sum('pending_amount');
        $previousMetalDebitTotal = $previousPendingSales->sum('metal_debit');
        $finalGrandAmount = $cashPayable + $previousPendingTotal;
        $finalGrandAmountType = $finalGrandAmount >= 0 ? 'DR' : 'CR';
        $finalGrandFineDebit = $fineDebit + $previousMetalDebitTotal;
    @endphp
    <div class="grand-total">
        <table class="grand-total-grid">
            <tr>
                <td class="grand-label" rowspan="2">Grand Total :</td>
                <td class="grand-head">Amount</td>
                <td class="grand-head grand-metal">Metal</td>
            </tr>
            <tr>
                <td class="grand-number">{{ $grandTotalType === 'DR' ? 'Dr.' : 'Cr.' }} {{ number_format(abs($cashPayable), 2) }}</td>
                <td class="grand-number grand-metal">
                    @if($fineDebit > 0.000001)
                        Dr. {{ number_format($fineDebit, 3) }} <span class="unit-circle">F</span>
                    @else
                        -
                    @endif
                </td>
            </tr>
        </table>
    </div>
    @php
        $adjustments = $settlementAdjustments ?? app(\App\Services\SaleSettlement::class)->adjustmentSummary($sale);
        $actualFineAdjusted = $adjustments['gold'] + $adjustments['silver'] + $adjustments['other'];
        $showAmountAdjustment = $fineBalanceAdjustmentEnabled && ($receivedAmount > 0.000001 || $adjustments['cash'] > 0.000001 || $actualFineAdjusted <= 0.000001);
    @endphp
    @if($fineBalanceAdjustmentEnabled)
    <table class="summary-grid" style="margin-top:0;">
        @if($showAmountAdjustment)
        <tr>
            <td>Amount Balance Adjustment: Yes</td>
            <td colspan="2"><strong>Adjusted Amount:</strong> <span style="font-size:11px; font-weight:700; background:#eeeeee; padding:2px 3px;">Rs&nbsp;{{ number_format($receivedAmount, 2) }}</span></td>
        </tr>
        @endif
        @if($actualFineAdjusted > 0.000001)
        <tr>
            <td>Metal Balance Adjustment: Yes</td>
            <td><strong>Adjusted Fine Wt</strong></td>
            <td class="text-right">{{ number_format($actualFineAdjusted, 3) }} g</td>
        </tr>
        @endif
    </table>
    @endif
        <table class="summary-grid" style="margin-top:0;">
            <tr>
                <td>
                    <div class="metric-card">
                        <table class="metric-top"><tr><td class="metric-label">Silver Debit / Credit</td><td class="metric-value">{{ number_format($finalGrandFineDebit, 3) }}/0.000</td></tr></table>
                    </div>
                </td>
                <td>
                    <div class="metric-card">
                        <table class="metric-top">
                            <tr><td class="metric-label">Received</td><td class="metric-value">Rs&nbsp;{{ number_format($receivedAmount, 2) }}</td></tr>
                            <tr><td class="metric-label">Total Amount</td><td class="metric-value">Rs&nbsp;{{ number_format($cashPayable, 2) }}</td></tr>
                            <tr><td class="metric-label">{{ $cashPayable - $effectiveReceived < 0 ? 'Credit Balance' : 'Pending Debit' }}</td><td class="metric-value">Rs&nbsp;{{ number_format(abs($cashPayable - $effectiveReceived), 2) }}</td></tr>
                        </table>
                    </div>
                </td>
                @if($advCashAbs > 0.000001)
                <td>
                    <div class="metric-card">
                        <table class="metric-top"><tr><td class="metric-label">Opening Balance</td><td class="metric-value">Rs&nbsp;{{ number_format($advCashAbs, 2) }}</td></tr></table>
                        <div class="metric-status">{{ $advCashType }}</div>
                    </div>
                </td>
                @endif
            </tr>
            @if(count($metalSummaryBoxes))
            <tr>
                @foreach($metalSummaryBoxes as $box)
                    <td>
                        @if($box)
                            <div class="metric-card">
                                <table class="metric-top"><tr><td class="metric-label">{{ $box['label'] }}</td><td class="metric-value">{{ $box['value'] }}</td></tr></table>
                                @if($box['status'] !== '')
                                    <div class="metric-status">{{ $box['status'] }}</div>
                                @endif
                            </div>
                        @endif
                    </td>
                @endforeach
            </tr>
            @endif
        </table>

    @if($previousPendingSales->isNotEmpty())
        <table class="voucher-grid" style="margin-top:8px;">
            <thead>
                <tr>
                    <th colspan="9" style="text-align:left;">Previous Pending Bills</th>
                </tr>
                <tr>
                    <th style="width:5%;">Sr</th>
                    <th style="width:17%; text-align:left;">Voucher No</th>
                    <th style="width:11%;">Date</th>
                    <th style="width:7%;">Items</th>
                    <th style="width:13%;">Total Amt</th>
                    <th style="width:11%;">Fine Wt</th>
                    <th style="width:12%;">Received</th>
                    <th style="width:13%;">Pending</th>
                    <th style="width:11%;">Metal Dr</th>
                </tr>
            </thead>
            <tbody>
                @foreach($previousPendingSales as $index => $pendingSale)
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td>{{ $pendingSale['voucher_no'] }}</td>
                        <td class="text-center">{{ $pendingSale['sale_date'] }}</td>
                        <td class="text-center">{{ number_format((float) ($pendingSale['qty'] ?? 0), 0) }}</td>
                        <td class="text-right amount-cell">Rs&nbsp;{{ number_format((float) $pendingSale['total_amount'], 2) }}</td>
                        <td class="text-right">{{ number_format((float) ($pendingSale['fine_weight'] ?? 0), 3) }}</td>
                        <td class="text-right amount-cell">Rs&nbsp;{{ number_format((float) $pendingSale['received_amount'], 2) }}</td>
                        <td class="text-right amount-cell">Rs&nbsp;{{ number_format((float) $pendingSale['pending_amount'], 2) }}</td>
                        <td class="text-right">
                            @if((float) ($pendingSale['metal_debit'] ?? 0) > 0.000001)
                                Dr. {{ number_format((float) $pendingSale['metal_debit'], 3) }} <span class="unit-circle">F</span>
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3" class="text-right">Previous Pending Total</th>
                    <th class="text-center">{{ number_format((float) $previousQtyTotal, 0) }}</th>
                    <th class="text-right amount-cell">Rs&nbsp;{{ number_format((float) $previousAmountTotal, 2) }}</th>
                    <th class="text-right">{{ number_format((float) $previousFineTotal, 3) }}</th>
                    <th></th>
                    <th class="text-right amount-cell">Rs&nbsp;{{ number_format((float) $previousPendingTotal, 2) }}</th>
                    <th class="text-right">
                        @if((float) $previousMetalDebitTotal > 0.000001)
                            Dr. {{ number_format((float) $previousMetalDebitTotal, 3) }} <span class="unit-circle">F</span>
                        @else
                            -
                        @endif
                    </th>
                </tr>
            </tfoot>
        </table>

        <div class="grand-total" style="margin-top:8px;">
            <table class="grand-total-grid">
                <tr>
                    <td class="grand-label" rowspan="2">Final Grand Total :</td>
                    <td class="grand-head">Amount</td>
                    <td class="grand-head grand-metal">Metal</td>
                </tr>
                <tr>
                    <td class="grand-number">{{ $finalGrandAmountType === 'DR' ? 'Dr.' : 'Cr.' }} {{ number_format(abs($finalGrandAmount), 2) }}</td>
                    <td class="grand-number grand-metal">
                        @if($finalGrandFineDebit > 0.000001)
                            Dr. {{ number_format($finalGrandFineDebit, 3) }} <span class="unit-circle">F</span>
                        @else
                            -
                        @endif
                    </td>
                </tr>
            </table>
        </div>
    @endif
</div>

</body>
</html>
