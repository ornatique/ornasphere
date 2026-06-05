<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sales List</title>
    <style>
        @page { margin: 16px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #111;
        }
        .wrapper {
            width: 100%;
            border: 1px solid #111;
        }
        .company {
            text-align: center;
            font-size: 20px;
            font-weight: 700;
            padding: 10px 6px;
            border-bottom: 1px solid #111;
        }
        .title {
            font-size: 12px;
            font-weight: 700;
            padding: 6px 8px;
            border-bottom: 1px solid #111;
        }
        .meta {
            padding: 6px 8px;
            border-bottom: 1px solid #111;
            font-size: 9px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #111;
            padding: 4px 4px;
            vertical-align: middle;
        }
        th {
            background: #eee;
            font-weight: 700;
            text-align: center;
        }
        td.num {
            text-align: right;
            white-space: nowrap;
        }
        td.center {
            text-align: center;
        }
        .total-row td {
            background: #eee;
            font-weight: 700;
        }
        .empty {
            text-align: center;
            padding: 14px;
        }
    </style>
</head>
<body>
@php
    $totals = [
        'qty' => $rows->sum('qty'),
        'gross_wt' => $rows->sum('gross_wt'),
        'net_wt' => $rows->sum('net_wt'),
        'fine_wt' => $rows->sum('fine_wt'),
        'metal_amt' => $rows->sum('metal_amt'),
        'labour_amt' => $rows->sum('labour_amt'),
        'other_amt' => $rows->sum('other_amt'),
        'total_amt' => $rows->sum('total_amt'),
        'received_amt' => $rows->sum('received_amt'),
        'refund_amt' => $rows->sum('refund_amt'),
        'pending_amt' => $rows->sum('pending_amt'),
    ];
@endphp
<div class="wrapper">
    <div class="company">{{ $company->name ?? 'Company' }}</div>
    <div class="title">Sales List</div>
    <div class="meta">
        <strong>Date Range:</strong> {{ \Carbon\Carbon::parse($fromDate)->format('d-m-Y') }} to {{ \Carbon\Carbon::parse($toDate)->format('d-m-Y') }}
        &nbsp; | &nbsp;
        <strong>Total Entries:</strong> {{ $rows->count() }}
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:3%;">#</th>
                <th style="width:8%;">Voucher No</th>
                <th style="width:6%;">Date</th>
                <th style="width:9%;">Customer</th>
                <th style="width:4%;">Qty</th>
                <th style="width:6%;">Gross Wt</th>
                <th style="width:6%;">Net Wt</th>
                <th style="width:6%;">Fine Wt</th>
                <th style="width:7%;">Metal Amt</th>
                <th style="width:7%;">Labour Amt</th>
                <th style="width:7%;">Other Amt</th>
                <th style="width:7%;">Total</th>
                <th style="width:7%;">Received</th>
                <th style="width:7%;">Refund</th>
                <th style="width:7%;">Pending</th>
                <th style="width:9%;">Created By</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $index => $row)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $row['voucher_no'] }}</td>
                    <td class="center">{{ $row['sale_date'] }}</td>
                    <td>{{ $row['customer_name'] }}</td>
                    <td class="center">{{ $row['qty'] }}</td>
                    <td class="num">{{ number_format($row['gross_wt'], 3) }}</td>
                    <td class="num">{{ number_format($row['net_wt'], 3) }}</td>
                    <td class="num">{{ number_format($row['fine_wt'], 3) }}</td>
                    <td class="num">Rs {{ number_format($row['metal_amt'], 2) }}</td>
                    <td class="num">Rs {{ number_format($row['labour_amt'], 2) }}</td>
                    <td class="num">Rs {{ number_format($row['other_amt'], 2) }}</td>
                    <td class="num">Rs {{ number_format($row['total_amt'], 2) }}</td>
                    <td class="num">Rs {{ number_format($row['received_amt'], 2) }}</td>
                    <td class="num">Rs {{ number_format($row['refund_amt'], 2) }}</td>
                    <td class="num">Rs {{ number_format($row['pending_amt'], 2) }}</td>
                    <td>{{ $row['created_by'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="16" class="empty">No sales found</td>
                </tr>
            @endforelse
            <tr class="total-row">
                <td colspan="4" class="num">Grand Total</td>
                <td class="center">{{ $totals['qty'] }}</td>
                <td class="num">{{ number_format($totals['gross_wt'], 3) }}</td>
                <td class="num">{{ number_format($totals['net_wt'], 3) }}</td>
                <td class="num">{{ number_format($totals['fine_wt'], 3) }}</td>
                <td class="num">Rs {{ number_format($totals['metal_amt'], 2) }}</td>
                <td class="num">Rs {{ number_format($totals['labour_amt'], 2) }}</td>
                <td class="num">Rs {{ number_format($totals['other_amt'], 2) }}</td>
                <td class="num">Rs {{ number_format($totals['total_amt'], 2) }}</td>
                <td class="num">Rs {{ number_format($totals['received_amt'], 2) }}</td>
                <td class="num">Rs {{ number_format($totals['refund_amt'], 2) }}</td>
                <td class="num">Rs {{ number_format($totals['pending_amt'], 2) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>
</div>
</body>
</html>
