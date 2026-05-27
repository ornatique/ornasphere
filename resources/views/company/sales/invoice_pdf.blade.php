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
        .voucher-grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .voucher-grid th, .voucher-grid td { border: 1px solid #000; padding: 3px 4px; vertical-align: top; word-wrap: break-word; }
        .voucher-grid th { background: #efefef; font-weight: 700; text-align: center; }
        .payment-grid { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 4px; }
        .payment-grid th, .payment-grid td { border: 1px solid #000; padding: 4px 6px; }
        .payment-grid th { background: #efefef; font-weight: 700; text-align: left; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .grand-total { text-align: right; font-weight: 700; padding: 6px 8px 8px; }
    </style>
</head>
<body>
@php
    $name = optional($sale->customer)->name ?? '-';
    $city = optional($sale->customer)->city ?? '-';
    $contact = optional($sale->customer)->mobile ?? optional($sale->customer)->phone ?? '-';
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
    $sumGross = 0; $sumLess = 0; $sumNet = 0; $sumOther = 0; $sumTotal = 0;
@endphp

<div class="sheet">
    <div class="company-head">
        <div class="company-name">{{ $companyName }}</div>
        @if($companyAddress !== '')
            <div class="company-meta">{{ $companyAddress }}</div>
        @endif
        @if($companyEmail !== '')
            <div class="company-meta"><strong>Email:</strong> {{ $companyEmail }}</div>
        @endif
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
                <div style="margin-top:4px;"><strong>Received</strong> : {{ number_format($receivedAmount, 2) }}</div>
                <div style="margin-top:4px;"><strong>Refund Paid</strong> : {{ number_format((float)($sale->paid_amount ?? 0), 2) }}</div>
                @php
                    $effectiveReceived = (float)($sale->received_amount ?? 0) - (float)($sale->paid_amount ?? 0);
                    $pendingAmount = max(0, (float)($sale->net_total ?? 0) - $effectiveReceived);
                @endphp
                <div style="margin-top:4px;"><strong>Pending</strong> : {{ number_format($pendingAmount, 2) }}</div>
            </td>
        </tr>
    </table>

    <table class="voucher-grid">
        <thead>
            <tr>
                <th style="width:4%;">Sr</th>
                <th style="width:30%; text-align:left;">Item</th>
                <th style="width:6%;">Carat</th>
                <th style="width:5%;">Qty</th>
                <th style="width:9%;">Gross Wt</th>
                <th style="width:9%;">Less Wt</th>
                <th style="width:9%;">Net Wt</th>
                <th style="width:8%;">Rate</th>
                <th style="width:10%;">Labour Rate</th>
                <th style="width:10%;">Other</th>
                <th style="width:10%;">Total Amt</th>
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
                    $carat = (float) (optional($item)->outward_carat ?? 0);
                    $qty = 1;
                    $gross = (float) ($row->gross_weight ?? 0);
                    $less = (float) ($row->other_weight ?? 0);
                    $net = (float) ($row->net_weight ?? ($gross - $less));
                    $rate = (float) ($row->metal_rate ?? 0);
                    $labourRate = (float) ($row->labour_rate ?? 0);
                    $other = (float) ($row->other_amount ?? 0);
                    $total = (float) ($row->total_amount ?? 0);

                    $sumGross += $gross; $sumLess += $less; $sumNet += $net; $sumOther += $other; $sumTotal += $total;
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $itemDisplay }}</td>
                    <td class="text-right">{{ number_format($carat, 0) }}</td>
                    <td class="text-center">{{ $qty }}</td>
                    <td class="text-right">{{ number_format($gross, 3) }}</td>
                    <td class="text-right">{{ number_format($less, 3) }}</td>
                    <td class="text-right">{{ number_format($net, 3) }}</td>
                    <td class="text-right">{{ number_format($rate, 2) }}</td>
                    <td class="text-right">{{ number_format($labourRate, 2) }}</td>
                    <td class="text-right">{{ number_format($other, 2) }}</td>
                    <td class="text-right">{{ number_format($total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th colspan="4" class="text-right">Total</th>
                <th class="text-right">{{ number_format($sumGross, 3) }}</th>
                <th class="text-right">{{ number_format($sumLess, 3) }}</th>
                <th class="text-right">{{ number_format($sumNet, 3) }}</th>
                <th></th>
                <th></th>
                <th class="text-right">{{ number_format($sumOther, 2) }}</th>
                <th class="text-right">{{ number_format($sumTotal, 2) }}</th>
            </tr>
        </tfoot>
    </table>

    <div class="grand-total">Grand Total : {{ number_format((float)($sale->net_total ?? $sumTotal), 2) }}</div>
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
                        <td class="text-right">{{ number_format($paymentRow['amount'], 2) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="3" class="text-right"><strong>Credit Total</strong></td>
                    <td class="text-right"><strong>{{ number_format($paymentTotal, 2) }}</strong></td>
                </tr>
                <tr>
                    <td colspan="3" class="text-right"><strong>Pending Amount</strong></td>
                    <td class="text-right">
                        <strong>{{ number_format(max(0, (float)($sale->net_total ?? $sumTotal) - $paymentTotal), 2) }}</strong>
                    </td>
                </tr>
            </tbody>
        </table>
    @endif
</div>

</body>
</html>
