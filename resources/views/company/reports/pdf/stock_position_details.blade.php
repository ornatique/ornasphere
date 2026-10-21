<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 7mm 5mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8.5px; line-height: 1.25; }
        h3, p { margin: 0 0 5px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #333; padding: 2px 3px; word-wrap: break-word; }
        th { background: #f2f2f2; font-size: 8px; }
        .summary td { font-weight: bold; background: #fafafa; }
    </style>
</head>
<body>
@include('company.reports.partials.pdf_company_scope')
    <h3>Stock Position Details - {{ \App\Services\ReportBranches::heading($company) }}</h3>
    <p>{{ $itemName }} | {{ $stockTypeName }} @if($customerName !== '-') | {{ $customerName }} @endif</p>

<p>With image: <strong>{{ request()->attributes->get('stock_image_counts')['with_image'] ?? 0 }}</strong> &nbsp; Without image: <strong>{{ request()->attributes->get('stock_image_counts')['without_image'] ?? 0 }}</strong> (before image filter)</p><p>Image filter: {{ request('image_status','all') }}</p>
    <table style="margin-bottom: 6px;">
        <tbody>
            <tr class="summary">
                <td>Qty Pcs: {{ (int) $summary['qty_pcs'] }}</td>
                <td>Gross Wt: {{ number_format((float) $summary['gross_weight'], 3, '.', '') }}</td>
                <td>Net Wt: {{ number_format((float) $summary['net_weight'], 3, '.', '') }}</td>
                <td>Fine Wt: {{ number_format((float) $summary['fine_weight'], 3, '.', '') }}</td>
            </tr>
        </tbody>
    </table>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Label Code</th>
                <th>Item</th>
                <th>Stock Type</th>
                <th>Qty</th>
                <th>Gross Wt</th>
                <th>Net Wt</th>
                <th>Fine Wt</th>
            <th>Image status</th><th>Company / Branch</th></tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $row->source_date ? \Carbon\Carbon::parse($row->source_date)->format('d-m-Y h:i A') : '-' }}</td>
                    <td>{{ $row->label_code ?? '-' }}</td>
                    <td>{{ $row->item_name ?? '-' }}</td>
                    <td>{{ $row->stock_type_name ?? '-' }}</td>
                    <td>{{ (int) ($row->qty_pcs ?? 0) }}</td>
                    <td>{{ number_format((float) ($row->gross_weight ?? 0), 3, '.', '') }}</td>
                    <td>{{ number_format((float) ($row->net_weight ?? 0), 3, '.', '') }}</td>
                    <td>{{ number_format((float) ($row->fine_weight ?? 0), 3, '.', '') }}</td>
                <td>{{ $row->has_image ? 'With image' : 'Without image' }}</td><td>{{ \App\Services\ReportBranches::name($row->company_id ?? null) }}</td></tr>
            @empty
                <tr>
                    <td colspan="11" style="text-align: center;">No stock details found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
