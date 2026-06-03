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
        .metric-value { text-align: right; white-space: nowrap; width: 42%; }
        .metric-status { margin-top: 3px; font-weight: 700; white-space: nowrap; }
        .voucher-grid { width: 100%; border-collapse: collapse; table-layout: auto; }
        .voucher-grid th, .voucher-grid td { border: 1px solid #000; padding: 3px 4px; vertical-align: top; word-break: normal; }
        .voucher-grid th { white-space: nowrap; background: #efefef; font-weight: 700; text-align: center; font-size: 9px; }
        .voucher-grid td { white-space: nowrap; font-size: 9px; }
        .voucher-grid td:nth-child(2) { white-space: normal; }
        .payment-grid { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 4px; }
        .payment-grid th, .payment-grid td { border: 1px solid #000; padding: 4px 6px; }
        .payment-grid th { background: #efefef; font-weight: 700; text-align: left; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .grand-total { text-align: right; font-weight: 700; padding: 6px 8px 8px; }
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
    $sumQty = 0; $sumGross = 0; $sumLess = 0; $sumNet = 0; $sumFine = 0; $sumMetalRate = 0; $sumLabourRate = 0; $sumOther = 0; $sumTotal = 0;
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
            </td>
            <td style="width:44%;">
                <div><strong>Estimate No</strong> : {{ $sale->voucher_no }}</div>
                <div style="margin-top:4px;"><strong>Date</strong> : {{ \Carbon\Carbon::parse($sale->sale_date)->format('d-m-Y') }}</div>
                <div style="margin-top:4px;"><strong>Contact No</strong> : {{ $contact }}</div>
            </td>
        </tr>
    </table>
    @php
        $effectiveReceived = (float)($sale->received_amount ?? 0) - (float)($sale->paid_amount ?? 0);
        $pendingRaw = (float)($sale->net_total ?? 0) - $effectiveReceived;
        $pendingType = $pendingRaw >= 0 ? 'Debit' : 'Credit';
        $pendingAmount = abs($pendingRaw);
        $advCashRaw = (float)($advanceSummary['cash'] ?? 0);
        $advCashType = $advCashRaw >= 0 ? 'Credit' : 'Debit';
        $advCashAbs = abs($advCashRaw);
        $silverUsed = (float)($saleAdvanceUsage['silver_used'] ?? 0);
        $silverDebit = (float)($saleAdvanceUsage['silver_debit'] ?? 0);
        $silverCredit = (float)($saleAdvanceUsage['silver_credit'] ?? 0);
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
                <th style="width:22%; text-align:left;">Item</th>
                <th style="width:8%;">Purity</th>
                <th style="width:4%;">Qty</th>
                <th style="width:8%;">Gross Wt</th>
                <th style="width:8%;">Less Wt</th>
                <th style="width:8%;">Net Wt</th>
                <th style="width:7%;">Waste %</th>
                <th style="width:8%;">Fine Wt</th>
                <th style="width:8%;">Metal Rt</th>
                <th style="width:8%;">Labour Rt</th>
                <th style="width:6%;">Other</th>
                <th style="width:7%;">Total Amt</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->saleItems as $index => $row)
                @php
                    $itemSet = $row->itemset;
                    $item = optional($itemSet)->item;
                    $labelCode = optional($itemSet)->qr_code ?? '';
                    $itemName = optional($item)->item_name ?? '-';
                    $itemDisplay = trim(($labelCode ? ($labelCode . ' - ') : '') . $itemName);
                    $metalName = (string) (optional($item)->metal ?? optional($item)->metal_type ?? '-');
                    $qty = 1;
                    $gross = (float) ($row->gross_weight ?? 0);
                    $less = (float) ($row->other_weight ?? 0);
                    $net = (float) ($row->net_weight ?? ($gross - $less));
                    $wastePercent = (float) ($row->waste_percent ?? 0);
                    $fine = (float) ($row->fine_weight ?? 0);
                    $rate = (float) ($row->metal_rate ?? 0);
                    $labourRate = (float) ($row->labour_rate ?? 0);
                    $other = (float) ($row->other_amount ?? 0);
                    $total = (float) ($row->total_amount ?? 0);
                    $carat = (float) (optional($item)->outward_carat ?? 0);

                    $sumQty += $qty; $sumGross += $gross; $sumLess += $less; $sumNet += $net; $sumFine += $fine; $sumMetalRate += $rate; $sumLabourRate += $labourRate; $sumOther += $other; $sumTotal += $total;
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $itemDisplay }}</td>
                    <td class="text-center">{{ rtrim(rtrim(number_format($carat, 2), '0'), '.') }}%</td>
                    <td class="text-center">{{ $qty }}</td>
                    <td class="text-right">{{ number_format($gross, 3) }}</td>
                    <td class="text-right">{{ number_format($less, 3) }}</td>
                    <td class="text-right">{{ number_format($net, 3) }}</td>
                    <td class="text-right">{{ number_format($wastePercent, 2) }}%</td>
                    <td class="text-right">{{ number_format($fine, 3) }}</td>
                    <td class="text-right">{{ number_format($rate, 2) }}</td>
                    <td class="text-right">{{ number_format($labourRate, 2) }}</td>
                    <td class="text-right">{{ number_format($other, 2) }}</td>
                    <td class="text-right">Rs {{ number_format($total, 2) }}</td>
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
                <th class="text-right">{{ number_format($sumFine, 3) }} <span class="unit-circle">F</span></th>
                <th class="text-right">{{ number_format($sumMetalRate, 2) }}</th>
                <th class="text-right">{{ number_format($sumLabourRate, 2) }}</th>
                <th class="text-right">Rs {{ number_format($sumOther, 2) }}</th>
                <th class="text-right">Rs {{ number_format($sumTotal, 2) }}</th>
            </tr>
        </tfoot>
    </table>

    <div class="grand-total">Grand Total : Rs {{ number_format((float)($sale->net_total ?? $sumTotal), 2) }}</div>
    @php
        $showGoldBox = $goldAbs > 0.000001;
        $showSilverBox = $silverBalanceAbs > 0.000001;
        $showOtherBox = $otherAbs > 0.000001;
        $metalSummaryBoxes = [];
        if ($showGoldBox) {
            $metalSummaryBoxes[] = ['label' => 'Adv Gold(Fine)', 'value' => number_format($goldAbs, 3), 'status' => $goldType];
        }
        if ($showSilverBox) {
            $metalSummaryBoxes[] = ['label' => 'Adv Silver(Fine)', 'value' => number_format($silverBalanceAbs, 3), 'status' => $silverBalanceType];
        }
        $metalSummaryBoxes[] = ['label' => 'Silver Debit / Credit', 'value' => number_format($silverDebit, 3) . '/' . number_format($silverCredit, 3), 'status' => ''];
        if ($showOtherBox) {
            $metalSummaryBoxes[] = ['label' => 'Adv Other(Fine)', 'value' => number_format($otherAbs, 3), 'status' => $otherType];
        }
        while (count($metalSummaryBoxes) < 4) {
            $metalSummaryBoxes[] = null;
        }
    @endphp
    <table class="summary-grid">
        <tr>
            <td>
                <div class="metric-card">
                    <table class="metric-top"><tr><td class="metric-label">Received</td><td class="metric-value">Rs {{ number_format($receivedAmount, 2) }}</td></tr></table>
                </div>
            </td>
            <td>
                <div class="metric-card">
                    <table class="metric-top"><tr><td class="metric-label">Refund Paid</td><td class="metric-value">Rs {{ number_format((float)($sale->paid_amount ?? 0), 2) }}</td></tr></table>
                </div>
            </td>
            <td>
                <div class="metric-card">
                    <table class="metric-top"><tr><td class="metric-label">Pending {{ $pendingType }}</td><td class="metric-value">Rs {{ number_format($pendingAmount, 2) }}</td></tr></table>
                </div>
            </td>
            <td>
                <div class="metric-card">
                    <table class="metric-top"><tr><td class="metric-label">Adv Cash</td><td class="metric-value">Rs {{ number_format($advCashAbs, 2) }}</td></tr></table>
                    <div class="metric-status">{{ $advCashType }}</div>
                </div>
            </td>
        </tr>
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
    </table>
    @if($paymentRows->isNotEmpty())
        <table class="payment-grid">
            <thead>
                <tr>
                    <th style="width:25%;">Credit Date</th>
                    <th style="width:20%;">Payment Mode</th>
                    <th style="width:35%;">Reference No</th>
                    <th style="width:20%;" class="text-right">Credit Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($paymentRows as $paymentRow)
                    <tr>
                        <td>{{ $paymentRow['date'] }}</td>
                        <td>{{ $paymentRow['mode'] !== '' ? ucfirst($paymentRow['mode']) : '-' }}</td>
                        <td>{{ $paymentRow['reference'] !== '' ? $paymentRow['reference'] : '-' }}</td>
                        <td class="text-right">Rs {{ number_format($paymentRow['amount'], 2) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="3" class="text-right"><strong>Credit Total</strong></td>
                    <td class="text-right"><strong>Rs {{ number_format($paymentTotal, 2) }}</strong></td>
                </tr>
                <tr>
                    <td colspan="3" class="text-right"><strong>Pending Amount</strong></td>
                    <td class="text-right">
                        <strong>Rs {{ number_format(max(0, (float)($sale->net_total ?? $sumTotal) - $paymentTotal), 2) }}</strong>
                    </td>
                </tr>
            </tbody>
        </table>
    @endif
</div>

</body>
</html>
