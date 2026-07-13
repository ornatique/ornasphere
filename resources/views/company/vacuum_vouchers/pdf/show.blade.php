<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Vacuum Voucher {{ $data->voucher_no }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #111;
            margin: 18px;
        }

        .title {
            text-align: center;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .company {
            text-align: center;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 16px;
        }

        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        .meta td {
            width: 25%;
            padding: 4px 6px;
            border: 1px solid #444;
            vertical-align: top;
        }

        .label {
            font-weight: 700;
        }

        .items {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .items th,
        .items td {
            border: 1px solid #444;
            padding: 6px;
            vertical-align: top;
        }

        .items th {
            background: #f0f0f0;
            font-weight: 700;
            text-align: left;
        }

        .num {
            text-align: right;
            white-space: nowrap;
        }

        .total-row td {
            font-weight: 700;
            background: #f7f7f7;
        }

        .remarks {
            margin-top: 14px;
            border: 1px solid #444;
            padding: 8px;
            min-height: 36px;
        }
    </style>
</head>
<body>
    <div class="title">Vacuum Voucher</div>
    <div class="company">{{ $company->name }}</div>

    <table class="meta">
        <tr>
            <td><span class="label">Voucher No:</span><br>{{ $data->voucher_no }}</td>
            <td><span class="label">Date:</span><br>{{ optional($data->voucher_date)->format('d-m-Y') }}</td>
            <td><span class="label">Process:</span><br>{{ $data->process?->name ?? '-' }}</td>
            <td><span class="label">Worker:</span><br>{{ $data->jobWorker?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td><span class="label">Formula:</span><br>{{ number_format((float) $data->formula_value, 3, '.', '') }}</td>
            <td><span class="label">Created By:</span><br>{{ $data->createdByUser?->name ?? '-' }}</td>
            <td><span class="label">Created At:</span><br>{{ optional($data->created_at)->format('d-m-Y h:i A') }}</td>
            <td><span class="label">Modified Count:</span><br>{{ (int) $data->modified_count }}</td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 8%;">Sr. No</th>
                <th style="width: 22%;">Buch No</th>
                <th class="num" style="width: 17%;">Gross Wt</th>
                <th class="num" style="width: 17%;">Buch Wt</th>
                <th class="num" style="width: 17%;">Net Wt</th>
                <th class="num" style="width: 19%;">Silver Wt</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data->items as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $item->buch_no }}</td>
                <td class="num">{{ number_format((float) $item->gross_wt, 3, '.', '') }}</td>
                <td class="num">{{ number_format((float) $item->buch_wt, 3, '.', '') }}</td>
                <td class="num">{{ number_format((float) $item->net_wt, 3, '.', '') }}</td>
                <td class="num">{{ number_format((float) $item->silver_wt, 3, '.', '') }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align: center;">No rows found</td>
            </tr>
            @endforelse
            <tr class="total-row">
                <td colspan="2">Total</td>
                <td class="num">{{ number_format((float) $data->gross_wt_total, 3, '.', '') }}</td>
                <td class="num">{{ number_format((float) $data->buch_wt_total, 3, '.', '') }}</td>
                <td class="num">{{ number_format((float) $data->net_wt_total, 3, '.', '') }}</td>
                <td class="num">{{ number_format((float) $data->silver_wt_total, 3, '.', '') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="remarks">
        <span class="label">Remarks:</span>
        {{ $data->remarks ?: '-' }}
    </div>
</body>
</html>
