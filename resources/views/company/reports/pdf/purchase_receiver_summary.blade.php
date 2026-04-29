<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 6mm 4px 6mm 4px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8.5px; line-height: 1.25; }
        h3 { margin: 0 0 6px; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #333; padding: 2px 3px; word-wrap: break-word; }
        th { background: #f2f2f2; font-size: 8.5px; }
        .text-end { text-align: right; }
    </style>
</head>
<body>
    <h3>Purchase / Receiver Summary Report - {{ $company->name }}</h3>
    <table>
        <thead>
            <tr>
                <th>Voucher No</th>
                <th>Date</th>
                <th>Customer Name</th>
                <th>Source</th>
                <th>Qty</th>
                <th>Gross Wt</th>
                <th>Net Wt</th>
                <th>Fine Wt</th>
                <th>Metal Amt</th>
                <th>Labour Amt</th>
                <th>Other Amt</th>
                <th>Total</th>
                <th>Remarks</th>
                <th>Created By</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $r)
            <tr>
                <td>{{ $r->return_voucher_no }}</td>
                <td>{{ $r->return_date ? \Carbon\Carbon::parse($r->return_date)->format('d-m-Y') : '-' }}</td>
                <td>{{ $r->customer_name ?? '-' }}</td>
                <td>{{ ucfirst((string) ($r->source_type ?: 'sale')) }}</td>
                <td>{{ (int) ($r->total_qty ?? 0) }}</td>
                <td>{{ number_format((float) ($r->total_gross_weight ?? 0), 3, '.', '') }}</td>
                <td>{{ number_format((float) ($r->total_net_weight ?? 0), 3, '.', '') }}</td>
                <td>{{ number_format((float) ($r->total_fine_weight ?? 0), 3, '.', '') }}</td>
                <td>{{ number_format((float) ($r->total_metal_amount ?? 0), 2, '.', '') }}</td>
                <td>{{ number_format((float) ($r->total_labour_amount ?? 0), 2, '.', '') }}</td>
                <td>{{ number_format((float) ($r->total_other_amount ?? 0), 2, '.', '') }}</td>
                <td>{{ number_format((float) ($r->return_total ?? 0), 2, '.', '') }}</td>
                <td>{{ $r->remarks ?? '-' }}</td>
                <td>{{ $r->created_by ?? '-' }}</td>
            </tr>
            @endforeach
            <tr>
                <td colspan="4" class="text-end"><strong>Total</strong></td>
                <td><strong>{{ (int) ($totals['qty_pcs'] ?? 0) }}</strong></td>
                <td><strong>{{ number_format((float) ($totals['gross_weight'] ?? 0), 3, '.', '') }}</strong></td>
                <td><strong>{{ number_format((float) ($totals['net_weight'] ?? 0), 3, '.', '') }}</strong></td>
                <td><strong>{{ number_format((float) ($totals['fine_weight'] ?? 0), 3, '.', '') }}</strong></td>
                <td><strong>{{ number_format((float) ($totals['metal_amount'] ?? 0), 2, '.', '') }}</strong></td>
                <td><strong>{{ number_format((float) ($totals['labour_amount'] ?? 0), 2, '.', '') }}</strong></td>
                <td><strong>{{ number_format((float) ($totals['other_amount'] ?? 0), 2, '.', '') }}</strong></td>
                <td><strong>{{ number_format((float) ($totals['return_total'] ?? 0), 2, '.', '') }}</strong></td>
                <td></td>
                <td></td>
            </tr>
        </tbody>
    </table>
</body>
</html>
