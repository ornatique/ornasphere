<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Outstanding Amount Ledger</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #111; }
        .title { font-size: 18px; font-weight: 700; text-align: center; margin-bottom: 4px; }
        .subtitle { text-align: center; margin-bottom: 6px; }
        .filter-line { text-align: center; font-weight: 700; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #222; padding: 4px 3px; }
        th { background: #efefef; text-align: left; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    @php
        $selectedPartyName = optional($selectedCustomer ?? null)->name;
        $selectedPartyCity = optional($selectedCustomer ?? null)->city;
        $showPartyColumn = blank($selectedPartyName);
        $showCityColumn = blank($selectedPartyName);
        $totalLabelColspan = 4 + ($showPartyColumn ? 1 : 0) + ($showCityColumn ? 1 : 0);
        $emptyColspan = 14 + ($showPartyColumn ? 1 : 0) + ($showCityColumn ? 1 : 0);
    @endphp
    <div class="title">{{ $company->company_name ?? $company->name ?? 'Company' }}</div>
    <div class="subtitle">Outstanding Amount Ledger Report</div>
    @if(filled($selectedPartyName))
        <div class="filter-line">
            Party : {{ $selectedPartyName }}
            @if(filled($selectedPartyCity))
                &nbsp;&nbsp; City : {{ $selectedPartyCity }}
            @endif
        </div>
    @endif

    <table>
        <thead>
            <tr>
                <th style="width: 3%;">#</th>
                <th style="width: 8%;">Voucher No</th>
                <th style="width: 6%;">Date</th>
                @if($showPartyColumn)
                    <th style="width: 10%;">Party</th>
                @endif
                @if($showCityColumn)
                    <th style="width: 6%;">City</th>
                @endif
                <th style="width: 5%;">Mode</th>
                <th class="text-right" style="width: 8%;">Total Amount</th>
                <th class="text-right" style="width: 5%;">Amount In</th>
                <th class="text-right" style="width: 5%;">Amount Out</th>
                <th class="text-right" style="width: 6%;">Pending</th>
                <th class="text-right" style="width: 6%;">Net Wt</th>
                <th class="text-right" style="width: 6%;">Gross Wt</th>
                <th class="text-right" style="width: 6%;">Fine Wt</th>
                <th class="text-right" style="width: 7%;">Metal Amt</th>
                <th class="text-right" style="width: 7%;">Labour Amt</th>
                <th class="text-right" style="width: 6%;">Metal Dr</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $i => $row)
                @php
                    $in = (float) ($row->received_amount ?? 0);
                    $out = (float) ($row->paid_amount ?? 0);
                    $pending = max(0, (float) ($row->net_total ?? 0) - ($in - $out));
                    $metalDebit = (!(bool) ($row->use_silver_balance ?? false) && (float) ($row->sum_metal_amount ?? 0) <= 0.000001)
                        ? (float) ($row->sum_fine_weight ?? 0)
                        : 0;
                @endphp
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td>{{ $row->voucher_no ?? '-' }}</td>
                    <td>{{ optional($row->sale_date)?->format('d-m-Y') ?? '-' }}</td>
                    @if($showPartyColumn)
                        <td>{{ optional($row->customer)->name ?? '-' }}</td>
                    @endif
                    @if($showCityColumn)
                        <td>{{ optional($row->customer)->city ?? '-' }}</td>
                    @endif
                    <td>{{ ucfirst((string) ($row->payment_mode ?? '-')) }}</td>
                    <td class="text-right">{{ number_format((float) ($row->net_total ?? 0), 2) }}</td>
                    <td class="text-right">{{ number_format($in, 2) }}</td>
                    <td class="text-right">{{ number_format($out, 2) }}</td>
                    <td class="text-right">{{ number_format($pending, 2) }}</td>
                    <td class="text-right">{{ number_format((float) ($row->sum_net_weight ?? 0), 3) }}</td>
                    <td class="text-right">{{ number_format((float) ($row->sum_gross_weight ?? 0), 3) }}</td>
                    <td class="text-right">{{ number_format((float) ($row->sum_fine_weight ?? 0), 3) }}</td>
                    <td class="text-right">{{ number_format((float) ($row->sum_metal_amount ?? 0), 2) }}</td>
                    <td class="text-right">{{ number_format((float) ($row->sum_labour_amount ?? 0), 2) }}</td>
                    <td class="text-right">{{ number_format($metalDebit, 3) }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ $emptyColspan }}" class="text-center">No records found</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <th colspan="{{ $totalLabelColspan }}" class="text-right">TOTAL</th>
                <th class="text-right">{{ number_format((float) ($summary['total_amount'] ?? 0), 2) }}</th>
                <th class="text-right">{{ number_format((float) ($summary['amount_in'] ?? 0), 2) }}</th>
                <th class="text-right">{{ number_format((float) ($summary['amount_out'] ?? 0), 2) }}</th>
                <th class="text-right">{{ number_format((float) ($summary['pending_amount'] ?? 0), 2) }}</th>
                <th class="text-right">{{ number_format((float) ($summary['net_weight'] ?? 0), 3) }}</th>
                <th class="text-right">{{ number_format((float) ($summary['gross_weight'] ?? 0), 3) }}</th>
                <th class="text-right">{{ number_format((float) ($summary['fine_weight'] ?? 0), 3) }}</th>
                <th class="text-right">{{ number_format((float) ($summary['metal_amount'] ?? 0), 2) }}</th>
                <th class="text-right">{{ number_format((float) ($summary['labour_amount'] ?? 0), 2) }}</th>
                <th class="text-right">{{ number_format((float) ($summary['metal_debit'] ?? 0), 3) }}</th>
            </tr>
        </tfoot>
    </table>
</body>
</html>
