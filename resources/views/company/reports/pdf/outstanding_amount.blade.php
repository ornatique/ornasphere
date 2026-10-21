<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 landscape; margin: 7mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 7.8px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #444; padding: 4px 3px; word-wrap: break-word; }
        th { background: #efefef; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .amount-cell { white-space: nowrap; font-size: 7.2px; }
        .title { font-size: 16px; font-weight: 700; margin-bottom: 8px; }
        .filter-line { font-size: 10px; font-weight: 700; margin: -3px 0 8px; }
    </style>
</head>
<body>
@include('company.reports.partials.pdf_company_scope')
    @php
        $selectedPartyName = optional($selectedCustomer ?? null)->name;
        $selectedPartyCity = optional($selectedCustomer ?? null)->city;
        $showPartyColumn = blank($selectedPartyName);
        $showCityColumn = blank($selectedPartyName);
        $totalLabelColspan = 4 + ($showPartyColumn ? 1 : 0) + ($showCityColumn ? 1 : 0);
        $emptyColspan = 14 + ($showPartyColumn ? 1 : 0) + ($showCityColumn ? 1 : 0);
    @endphp
    <div class="title">Outstanding Amount Report - {{ \App\Services\ReportBranches::heading($company) }}</div>
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
                <th style="width:3%;">#</th>
                <th style="width:8%;">Voucher No</th>
                <th style="width:6%;">Date</th>
                @if($showPartyColumn)
                    <th style="width:10%;">Party</th>
                @endif
                @if($showCityColumn)
                    <th style="width:6%;">City</th>
                @endif
                <th style="width:5%;">Mode</th>
                <th style="width:6%;">Gross Wt</th>
                <th style="width:6%;">Net Wt</th>
                <th style="width:6%;">Fine Wt</th>
                <th style="width:7%;">Metal Amt</th>
                <th style="width:7%;">Labour Amt</th>
                <th style="width:6%;">Metal Dr</th>
                <th style="width:8%;">Total Amt</th>
                <th style="width:5%;">Amt In</th>
                <th style="width:5%;">Amt Out</th>
                <th style="width:6%;">Pending</th>
            <th>Company / Branch</th></tr>
        </thead>
        <tbody>
            @forelse($rows as $i => $r)
                @php
                    $received = (float) ($r->received_amount ?? 0);
                    $out = (float) ($r->paid_amount ?? 0);
                    $pending = max(0, (float) ($r->net_total ?? 0) - $r->appliedReceivedAmount());
                    $metalDebit = (!(bool) ($r->use_silver_balance ?? false) && (float) ($r->sum_metal_amount ?? 0) <= 0.000001)
                        ? (float) ($r->sum_fine_weight ?? 0)
                        : 0;
                @endphp
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td>{{ $r->voucher_no }}</td>
                    <td>{{ optional($r->sale_date)?->format('d-m-Y') ?? '-' }}</td>
                    @if($showPartyColumn)
                        <td>{{ optional($r->customer)->name ?? '-' }}</td>
                    @endif
                    @if($showCityColumn)
                        <td>{{ optional($r->customer)->city ?? '-' }}</td>
                    @endif
                    <td>{{ $r->payment_mode ? ucfirst((string) $r->payment_mode) : '-' }}</td>
                    <td class="text-right">{{ number_format((float) ($r->sum_gross_weight ?? 0), 3, '.', '') }}</td>
                    <td class="text-right">{{ number_format((float) ($r->sum_net_weight ?? 0), 3, '.', '') }}</td>
                    <td class="text-right">{{ number_format((float) ($r->sum_fine_weight ?? 0), 3, '.', '') }}</td>
                    <td class="text-right amount-cell">{{ number_format((float) ($r->sum_metal_amount ?? 0), 2, '.', '') }}</td>
                    <td class="text-right amount-cell">{{ number_format((float) ($r->sum_labour_amount ?? 0), 2, '.', '') }}</td>
                    <td class="text-right">{{ number_format($metalDebit, 3, '.', '') }}</td>
                    <td class="text-right amount-cell">{{ number_format((float) ($r->net_total ?? 0), 2, '.', '') }}</td>
                    <td class="text-right amount-cell">{{ number_format($received, 2, '.', '') }}</td>
                    <td class="text-right amount-cell">{{ number_format($out, 2, '.', '') }}</td>
                    <td class="text-right amount-cell">{{ number_format($pending, 2, '.', '') }}</td>
                <td>{{ \App\Services\ReportBranches::name(data_get($r, 'company_id')) }}</td></tr>
            @empty
                <tr><td colspan="{{ $emptyColspan }}" class="text-center">No records found</td></tr>
            @endforelse
            <tr>
                <td colspan="{{ $totalLabelColspan }}" style="font-weight:700;">TOTAL</td>
                <td class="text-right" style="font-weight:700;">{{ number_format((float) ($summary['gross_weight'] ?? 0), 3, '.', '') }}</td>
                <td class="text-right" style="font-weight:700;">{{ number_format((float) ($summary['net_weight'] ?? 0), 3, '.', '') }}</td>
                <td class="text-right" style="font-weight:700;">{{ number_format((float) ($summary['fine_weight'] ?? 0), 3, '.', '') }}</td>
                <td class="text-right amount-cell" style="font-weight:700;">{{ number_format((float) ($summary['metal_amount'] ?? 0), 2, '.', '') }}</td>
                <td class="text-right amount-cell" style="font-weight:700;">{{ number_format((float) ($summary['labour_amount'] ?? 0), 2, '.', '') }}</td>
                <td class="text-right" style="font-weight:700;">{{ number_format((float) ($summary['metal_debit'] ?? 0), 3, '.', '') }}</td>
                <td class="text-right amount-cell" style="font-weight:700;">{{ number_format((float) ($summary['total_amount'] ?? 0), 2, '.', '') }}</td>
                <td class="text-right amount-cell" style="font-weight:700;">{{ number_format((float) ($summary['amount_in'] ?? 0), 2, '.', '') }}</td>
                <td class="text-right amount-cell" style="font-weight:700;">{{ number_format((float) ($summary['amount_out'] ?? 0), 2, '.', '') }}</td>
                <td class="text-right amount-cell" style="font-weight:700;">{{ number_format((float) ($summary['pending_amount'] ?? 0), 2, '.', '') }}</td>
            <td></td></tr>
        </tbody>
    </table>
</body>
</html>
