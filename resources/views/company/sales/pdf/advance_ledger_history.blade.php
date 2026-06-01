<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Advance Ledger History</title>
    <style>
        @page { size: A4 landscape; margin: 7mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; color: #111; margin: 0; }
        .sheet { width: 100%; border: 1px solid #000; }
        .company-head { border-bottom: 1px solid #000; text-align: center; padding: 9px 8px 7px; }
        .company-name { font-size: 20px; font-weight: 700; line-height: 1.1; }
        .company-meta { margin-top: 4px; font-size: 9px; }
        .sheet-title { border-bottom: 1px solid #000; padding: 6px 8px; font-weight: 700; font-size: 13px; }
        .meta-box { width: 100%; border-collapse: collapse; border-bottom: 1px solid #000; }
        .meta-box td { border-right: 1px solid #000; padding: 5px 7px; vertical-align: top; }
        .meta-box td:last-child { border-right: none; }
        .summary-grid { width: 100%; border-collapse: collapse; border-bottom: 1px solid #000; }
        .summary-grid td { border-right: 1px solid #000; padding: 5px 7px; vertical-align: top; width: 25%; }
        .summary-grid td:last-child { border-right: none; }
        .metric { font-weight: 700; }
        .metric-val { float: right; font-weight: 400; }
        .table-wrap { padding: 0; }
        table { width: 100%; border-collapse: collapse; table-layout: auto; }
        th, td { border: 1px solid #000; padding: 4px; vertical-align: top; }
        th { background: #efefef; font-weight: 700; text-align: center; white-space: nowrap; }
        td { white-space: nowrap; }
        td.remark { white-space: normal; }
        .num { text-align: right; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    @php
        $slugName = '';
        if (!empty($company->slug)) {
            $slugBase = explode('-', (string) $company->slug)[0] ?? '';
            $slugName = ucwords(str_replace(['-', '_'], ' ', $slugBase));
        }
        $companyDisplayName =
            $company->company_name
            ?? $company->name
            ?? ($company->trade_name ?? null)
            ?? ($company->legal_name ?? null)
            ?? ($slugName !== '' ? $slugName : 'Company');
        $companyAddress = collect([
            $company->address_1 ?? null,
            $company->address_2 ?? null,
            $company->city ?? null,
            $company->state ?? null,
            $company->postcode ?? null,
        ])->filter()->implode(', ');
        $companyEmail = $company->email ?? '';
    @endphp
    <div class="sheet">
        <div class="company-head">
            <div class="company-name">{{ $companyDisplayName }}</div>
        </div>
        <div class="sheet-title">Advance Ledger History</div>

        <table class="meta-box">
            <tr>
                <td style="width:50%;">
                    <div><strong>Customer:</strong> {{ $customer->name ?? '-' }}</div>
                    <div style="margin-top:3px;"><strong>City:</strong> {{ $customer->city ?? '-' }}</div>
                </td>
                <td style="width:50%;">
                    <div><strong>Report Date:</strong> {{ now()->format('d-m-Y h:i A') }}</div>
                    <div style="margin-top:3px;"><strong>Total Entries:</strong> {{ $rows->count() }}</div>
                </td>
            </tr>
        </table>

        <table class="summary-grid">
            <tr>
                <td><span class="metric">Cash Balance</span><span class="metric-val">Rs {{ number_format(abs((float)($balance['cash_balance'] ?? 0)), 2) }} ({{ (float)($balance['cash_balance'] ?? 0) >= 0 ? 'Credit' : 'Debit' }})</span></td>
                <td><span class="metric">Gold(Fine)</span><span class="metric-val">{{ number_format(abs((float)data_get($balance, 'metal_balance.gold', 0)), 3) }} ({{ (float)data_get($balance, 'metal_balance.gold', 0) >= 0 ? 'Credit' : 'Debit' }})</span></td>
                <td><span class="metric">Silver(Fine)</span><span class="metric-val">{{ number_format(abs((float)data_get($balance, 'metal_balance.silver', 0)), 3) }} ({{ (float)data_get($balance, 'metal_balance.silver', 0) >= 0 ? 'Credit' : 'Debit' }})</span></td>
                <td><span class="metric">Other(Fine)</span><span class="metric-val">{{ number_format(abs((float)data_get($balance, 'metal_balance.other', 0)), 3) }} ({{ (float)data_get($balance, 'metal_balance.other', 0) >= 0 ? 'Credit' : 'Debit' }})</span></td>
            </tr>
        </table>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width:3%;">#</th>
                        <th style="width:11%;">Date & Time</th>
                        <th style="width:10%;">Party</th>
                        <th style="width:15%;">Entry Type</th>
                        <th style="width:7%;">Mode</th>
                        <th style="width:8%;">Cash In (CR)</th>
                        <th style="width:8%;">Cash Out (DB)</th>
                        <th style="width:6%;">Rate</th>
                        <th style="width:8%;">Metal Type</th>
                        <th style="width:8%;">Metal In</th>
                        <th style="width:8%;">Metal Out</th>
                        <th style="width:18%;">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $i => $row)
                        <tr>
                            <td class="text-center">{{ $i + 1 }}</td>
                            <td>
                                {{ optional($row->entry_date)->format('d-m-Y') }}
                                @if(!empty($row->created_at))
                                    {{ optional($row->created_at)->format('h:i A') }}
                                @endif
                            </td>
                            <td>{{ optional($row->customer)->name ?? '-' }}</td>
                            <td>
                                @if($row->entry_type === 'convert_to_metal')
                                    Rupees Convert To Metal
                                @elseif($row->entry_type === 'convert_to_rupees')
                                    Metal Convert To Rupees
                                @else
                                    {{ ucwords(str_replace('_', ' ', $row->entry_type)) }}
                                @endif
                            </td>
                            <td>{{ $row->payment_mode ? ucfirst($row->payment_mode) : '-' }}</td>
                            <td class="num">Rs {{ number_format((float)$row->cash_in, 2) }}</td>
                            <td class="num">Rs {{ number_format((float)$row->cash_out, 2) }}</td>
                            <td class="num">{{ number_format((float)$row->rate, 2) }}</td>
                            <td>{{ $row->metal_type ? ucfirst($row->metal_type) : '-' }}</td>
                            <td class="num">{{ number_format((float)$row->metal_in, 3) }}</td>
                            <td class="num">{{ number_format((float)$row->metal_out, 3) }}</td>
                            <td class="remark">{{ $row->remarks ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="12" class="text-center">No entries found</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
