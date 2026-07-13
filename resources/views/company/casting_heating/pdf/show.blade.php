<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Casting Heating {{ $voucher->voucher_no }}</title>
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

        .center {
            text-align: center;
        }
    </style>
</head>
<body>
    @php
        $totalPcs = (int) ($voucher->items_count ?? $voucher->items->count());
    @endphp

    <div class="title">Casting Heating Voucher</div>
    <div class="company">{{ $company->name }}</div>

    <table class="meta">
        <tr>
            <td><span class="label">Voucher No:</span><br>{{ $voucher->voucher_no }}</td>
            <td><span class="label">Date:</span><br>{{ optional($voucher->voucher_date)->format('d-m-Y') }}</td>
            <td><span class="label">Process:</span><br>{{ $voucher->process?->name ?? '-' }}</td>
            <td><span class="label">Worker:</span><br>{{ $voucher->jobWorker?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td><span class="label">Total Pcs:</span><br>{{ $totalPcs }}</td>
            <td><span class="label">In Bhati Pcs:</span><br>{{ (int) $inBhatiCount }}</td>
            <td><span class="label">Created At:</span><br>{{ optional($voucher->created_at)->format('d-m-Y h:i A') }}</td>
            <td><span class="label">Printed At:</span><br>{{ now()->format('d-m-Y h:i A') }}</td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 12%;">Sr. No</th>
                <th style="width: 38%;">Buch No</th>
                <th class="center" style="width: 18%;">In Bhati</th>
                <th style="width: 32%;">Check Time</th>
            </tr>
        </thead>
        <tbody>
            @forelse($voucher->items as $item)
            @php
                $heatingItem = $heatingItems->get($item->id);
                $checkedAt = $heatingItem?->checked_at;
            @endphp
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $item->buch_no }}</td>
                <td class="center">{{ ($heatingItem?->in_bhati) ? 'Yes' : 'No' }}</td>
                <td>{{ $checkedAt ? $checkedAt->format('d-m-Y h:i A') : '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="center">No Buch rows found</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
