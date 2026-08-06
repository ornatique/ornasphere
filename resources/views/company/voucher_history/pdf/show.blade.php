<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Voucher Process History {{ $voucher->voucher_no }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; margin: 16px; }
        .title { text-align: center; font-size: 18px; font-weight: 700; margin-bottom: 3px; }
        .company { text-align: center; font-size: 13px; font-weight: 700; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #444; padding: 5px; vertical-align: top; }
        th { background: #f0f0f0; font-weight: 700; text-align: left; }
        .meta { margin-bottom: 10px; }
        .meta td { width: 25%; }
        .label { font-weight: 700; }
        .section-title { margin: 12px 0 4px; font-size: 12px; font-weight: 700; background: #ededed; padding: 5px; border: 1px solid #444; }
        .num { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        tfoot td { font-weight: 700; background: #f0f0f0; }
    </style>
</head>
<body>
    <div class="title">Voucher Process History</div>
    <div class="company">{{ $company->name }}</div>

    <table class="meta">
        <tr>
            <td><span class="label">Voucher No:</span><br>{{ $voucher->voucher_no }}</td>
            <td><span class="label">Voucher Date:</span><br>{{ optional($voucher->voucher_date)->format('d-m-Y') }}</td>
            <td><span class="label">Process:</span><br>{{ $voucher->process?->name ?? '-' }}</td>
            <td><span class="label">Worker:</span><br>{{ $voucher->jobWorker?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td><span class="label">Gross Wt:</span><br>{{ $history['summary']['gross_wt'] }}</td>
            <td><span class="label">Buch Wt:</span><br>{{ $history['summary']['buch_wt'] }}</td>
            <td><span class="label">Net Wt:</span><br>{{ $history['summary']['net_wt'] }}</td>
            <td><span class="label">Silver Wt:</span><br>{{ $history['summary']['silver_wt'] }}</td>
        </tr>
        <tr>
            <td><span class="label">Total Pcs:</span><br>{{ $history['summary']['total_pcs'] }}</td>
            <td><span class="label">Created At:</span><br>{{ optional($voucher->created_at)->format('d-m-Y h:i A') }}</td>
            <td><span class="label">Printed At:</span><br>{{ now()->format('d-m-Y h:i A') }}</td>
            <td><span class="label">Printed By:</span><br>{{ auth()->user()->name ?? '-' }}</td>
        </tr>
    </table>

    <div class="section-title">1. Casting Heating - {{ $history['casting_heating']['in_bhati_count'] }} In Bhati / {{ $history['summary']['total_pcs'] }} Pcs</div>
    <table>
        <thead><tr><th style="width:8%;">Sr No</th><th>Buch No</th><th>In Bhati</th><th>Checked At</th></tr></thead>
        <tbody>
            @forelse($history['casting_heating']['rows'] as $row)
            <tr><td>{{ $loop->iteration }}</td><td>{{ $row['buch_no'] }}</td><td>{{ $row['in_bhati'] }}</td><td>{{ $row['checked_at'] }}</td></tr>
            @empty
            <tr><td colspan="4" class="center">No data found</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">2. Casting Metal Issue</div>
    <table>
        <thead><tr><th style="width:8%;">Sr No</th><th>Buch No</th><th class="num">Silver Wt</th><th class="num">Issue Silver Wt</th><th>Issued At</th></tr></thead>
        <tbody>
            @forelse($history['casting_metal_issue']['rows'] as $row)
            <tr><td>{{ $loop->iteration }}</td><td>{{ $row['buch_no'] }}</td><td class="num">{{ $row['silver_wt'] }}</td><td class="num">{{ $row['issue_silver_wt'] }}</td><td>{{ $row['issued_at'] }}</td></tr>
            @empty
            <tr><td colspan="5" class="center">No data found</td></tr>
            @endforelse
        </tbody>
        @if($history['casting_metal_issue']['rows']->isNotEmpty())
        <tfoot>
            <tr>
                <td colspan="2">Total</td>
                <td class="num">{{ $history['casting_metal_issue']['totals']['silver_wt'] }}</td>
                <td class="num">{{ $history['casting_metal_issue']['totals']['issue_silver_wt'] }}</td>
                <td></td>
            </tr>
        </tfoot>
        @endif
    </table>

    <div class="section-title">3. Casting Receive</div>
    <table>
        <thead><tr><th style="width:8%;">Sr No</th><th>Buch No</th><th class="num">Release Tree Wt</th><th class="num">Tree Bhuko</th><th class="num">Loss</th><th>Received At</th></tr></thead>
        <tbody>
            @forelse($history['casting_receive']['rows'] as $row)
            <tr><td>{{ $loop->iteration }}</td><td>{{ $row['buch_no'] }}</td><td class="num">{{ $row['release_tree_wt'] }}</td><td class="num">{{ $row['release_tree_bhuko'] }}</td><td class="num">{{ $row['loss'] }}</td><td>{{ $row['received_at'] }}</td></tr>
            @empty
            <tr><td colspan="6" class="center">No data found</td></tr>
            @endforelse
        </tbody>
        @if($history['casting_receive']['rows']->isNotEmpty())
        <tfoot>
            <tr>
                <td colspan="2">Total</td>
                <td class="num">{{ $history['casting_receive']['totals']['release_tree_wt'] }}</td>
                <td class="num">{{ $history['casting_receive']['totals']['release_tree_bhuko'] }}</td>
                <td class="num">{{ $history['casting_receive']['totals']['loss'] }}</td>
                <td></td>
            </tr>
        </tfoot>
        @endif
    </table>

    <div class="section-title">4. Tree Cutting Issue Office</div>
    <table>
        <thead><tr><th style="width:8%;">Sr No</th><th>Buch No</th><th class="num">Tree Wt</th><th class="num">Tree Bhuko</th><th class="num">Remaining Tree Wt</th><th>Office At</th></tr></thead>
        <tbody>
            @forelse($history['tree_cutting_office']['rows'] as $row)
            <tr><td>{{ $loop->iteration }}</td><td>{{ $row['buch_no'] }}</td><td class="num">{{ $row['tree_wt'] }}</td><td class="num">{{ $row['tree_bhuko'] }}</td><td class="num">{{ $row['remaining_tree_wt'] }}</td><td>{{ $row['office_at'] }}</td></tr>
            @empty
            <tr><td colspan="6" class="center">No data found</td></tr>
            @endforelse
        </tbody>
        @if($history['tree_cutting_office']['rows']->isNotEmpty())
        <tfoot>
            <tr>
                <td colspan="2">Total</td>
                <td class="num">{{ $history['tree_cutting_office']['totals']['tree_wt'] }}</td>
                <td class="num">{{ $history['tree_cutting_office']['totals']['tree_bhuko'] }}</td>
                <td class="num">{{ $history['tree_cutting_office']['totals']['remaining_tree_wt'] }}</td>
                <td></td>
            </tr>
        </tfoot>
        @endif
    </table>

    <div class="section-title">5. Tree Cutting Issue</div>
    <table>
        <thead><tr><th style="width:8%;">Sr No</th><th>Buch No</th><th>Worker</th><th class="num">Receive Tree Wt</th><th>Issued At</th></tr></thead>
        <tbody>
            @forelse($history['tree_cutting_issue']['rows'] as $row)
            <tr><td>{{ $loop->iteration }}</td><td>{{ $row['buch_no'] }}</td><td>{{ $row['worker'] }}</td><td class="num">{{ $row['receive_tree_wt'] }}</td><td>{{ $row['issued_at'] }}</td></tr>
            @empty
            <tr><td colspan="5" class="center">No data found</td></tr>
            @endforelse
        </tbody>
        @if($history['tree_cutting_issue']['rows']->isNotEmpty())
        <tfoot>
            <tr>
                <td colspan="3">Total</td>
                <td class="num">{{ $history['tree_cutting_issue']['totals']['receive_tree_wt'] }}</td>
                <td></td>
            </tr>
        </tfoot>
        @endif
    </table>

    <div class="section-title">6. Tree Cutting Receive</div>
    <table>
        <thead><tr><th style="width:8%;">Sr No</th><th>Buch No</th><th>Worker</th><th class="num">Receive Pc Wt</th><th class="num">Tree Bhuko</th><th class="num">Loss</th><th>Received At</th></tr></thead>
        <tbody>
            @forelse($history['tree_cutting_receive']['rows'] as $row)
            <tr><td>{{ $loop->iteration }}</td><td>{{ $row['buch_no'] }}</td><td>{{ $row['worker'] }}</td><td class="num">{{ $row['receive_pc_wt'] }}</td><td class="num">{{ $row['receive_tree_bhuko'] }}</td><td class="num">{{ $row['loss'] }}</td><td>{{ $row['received_at'] }}</td></tr>
            @empty
            <tr><td colspan="7" class="center">No data found</td></tr>
            @endforelse
        </tbody>
        @if($history['tree_cutting_receive']['rows']->isNotEmpty())
        <tfoot>
            <tr>
                <td colspan="3">Total</td>
                <td class="num">{{ $history['tree_cutting_receive']['totals']['receive_pc_wt'] }}</td>
                <td class="num">{{ $history['tree_cutting_receive']['totals']['receive_tree_bhuko'] }}</td>
                <td class="num">{{ $history['tree_cutting_receive']['totals']['loss'] }}</td>
                <td></td>
            </tr>
        </tfoot>
        @endif
    </table>

    <div class="section-title">7. Casting Sorting</div>
    <table>
        <thead><tr><th style="width:8%;">Sr No</th><th>Item</th><th class="num">Weight</th><th class="num">Quantity</th><th>Sorted At</th></tr></thead>
        <tbody>
            @forelse($history['casting_sorting']['rows'] as $row)
            <tr><td>{{ $loop->iteration }}</td><td>{{ $row['item'] }}</td><td class="num">{{ $row['weight'] }}</td><td class="num">{{ $row['quantity'] }}</td><td>{{ $row['sorted_at'] }}</td></tr>
            @empty
            <tr><td colspan="5" class="center">No data found</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
