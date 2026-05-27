<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #444; padding: 6px; }
        th { background: #efefef; }
        .text-right { text-align: right; }
        .title { font-size: 16px; font-weight: 700; margin-bottom: 8px; }
    </style>
</head>
<body>
    <div class="title">Outstanding Amount Report - {{ $company->name }}</div>
    @php
        $showDate = ($visible['default'] ?? false) || ($visible['date'] ?? false);
        $showParty = ($visible['default'] ?? false) || ($visible['party'] ?? false);
        $showCity = ($visible['default'] ?? false) || ($visible['city'] ?? false);
        $showMode = ($visible['default'] ?? false) || ($visible['mode'] ?? false);
        $showWeight = ($visible['default'] ?? false) || ($visible['weight'] ?? false);
        $showAmount = ($visible['default'] ?? false) || ($visible['amount'] ?? false);
        $labelCols = 2 + ($showDate ? 1 : 0) + ($showParty ? 1 : 0) + ($showCity ? 1 : 0) + ($showMode ? 1 : 0);
    @endphp
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Voucher</th>
                @if($showDate)<th>Date</th>@endif
                @if($showParty)<th>Party</th>@endif
                @if($showCity)<th>City</th>@endif
                @if($showMode)<th>Mode</th>@endif
                @if($showWeight)
                    <th>Gross Wt</th>
                    <th>Net Wt</th>
                @endif
                @if($showAmount)
                    <th>Total</th>
                    <th>In</th>
                    <th>Out</th>
                    <th>Pending</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $i => $r)
                @php
                    $received = (float) ($r->received_amount ?? 0);
                    $out = (float) ($r->paid_amount ?? 0);
                    $pending = max(0, (float) ($r->net_total ?? 0) - ($received - $out));
                @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $r->voucher_no }}</td>
                    @if($showDate)<td>{{ optional($r->sale_date)?->format('d-m-Y') ?? '-' }}</td>@endif
                    @if($showParty)<td>{{ optional($r->customer)->name ?? '-' }}</td>@endif
                    @if($showCity)<td>{{ optional($r->customer)->city ?? '-' }}</td>@endif
                    @if($showMode)<td>{{ $r->payment_mode ?? '-' }}</td>@endif
                    @if($showWeight)
                        <td class="text-right">{{ number_format((float) ($r->sum_gross_weight ?? 0), 3, '.', '') }}</td>
                        <td class="text-right">{{ number_format((float) ($r->sum_net_weight ?? 0), 3, '.', '') }}</td>
                    @endif
                    @if($showAmount)
                        <td class="text-right">{{ number_format((float) ($r->net_total ?? 0), 2, '.', '') }}</td>
                        <td class="text-right">{{ number_format($received, 2, '.', '') }}</td>
                        <td class="text-right">{{ number_format($out, 2, '.', '') }}</td>
                        <td class="text-right">{{ number_format($pending, 2, '.', '') }}</td>
                    @endif
                </tr>
            @endforeach
            <tr>
                <td colspan="{{ $labelCols }}" style="font-weight:700;">TOTAL</td>
                @if($showWeight)
                    <td class="text-right" style="font-weight:700;">{{ number_format((float) ($summary['gross_weight'] ?? 0), 3, '.', '') }}</td>
                    <td class="text-right" style="font-weight:700;">{{ number_format((float) ($summary['net_weight'] ?? 0), 3, '.', '') }}</td>
                @endif
                @if($showAmount)
                    <td class="text-right" style="font-weight:700;">{{ number_format((float) ($summary['total_amount'] ?? 0), 2, '.', '') }}</td>
                    <td class="text-right" style="font-weight:700;">{{ number_format((float) ($summary['amount_in'] ?? 0), 2, '.', '') }}</td>
                    <td class="text-right" style="font-weight:700;">{{ number_format((float) ($summary['amount_out'] ?? 0), 2, '.', '') }}</td>
                    <td class="text-right" style="font-weight:700;">{{ number_format((float) ($summary['pending_amount'] ?? 0), 2, '.', '') }}</td>
                @endif
            </tr>
        </tbody>
    </table>
</body>
</html>
