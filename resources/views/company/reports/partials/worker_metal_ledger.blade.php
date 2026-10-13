@php
    $weight = fn($value) => number_format((float) $value, 3, '.', '');
    $balance = fn($value) => number_format(abs((float) $value), 3, '.', '').($value > 0 ? ' Dr' : ($value < 0 ? ' Cr' : ''));
@endphp
@if(!empty($ledger['cash_openings']))
<h4>Cash Opening Balances (Rs)</h4>
<p>Amounts brought forward on the dates below; these are separate from the metal ledger.</p>
<table class="table table-bordered"><thead><tr><th>Worker</th><th>Opening Date</th><th>Debit</th><th>Credit</th></tr></thead><tbody>
@foreach($ledger['cash_openings'] as $cash)
<tr><td>{{ $cash->worker_name }}</td><td>{{ \Carbon\Carbon::parse($cash->date)->format('d-m-Y') }}</td><td>{{ number_format($cash->debit,2) }}</td><td>{{ number_format($cash->credit,2) }}</td></tr>
@endforeach
</tbody></table>
@endif
@forelse($ledger['groups'] as $group)
<div class="ledger-group">
    <h4>{{ $group['worker_name'] }} — {{ $group['metal'] }}</h4>
    <p>Net pending: <strong>{{ $balance($group['closing']) }} g</strong></p>
    @if(($ledger['filters']['format'] ?? 'detailed') === 'daily')
        @include('company.reports.partials.worker_metal_daily')
    @else
    <div class="table-responsive"><table class="table table-bordered ledger-table">
        <thead><tr><th>Date</th><th>Voucher / Reference</th><th>Type</th><th>Item</th><th class="ledger-debit">Net Debit</th><th class="ledger-credit">Net Credit</th><th>Net Balance</th></tr></thead>
        <tbody><tr><td colspan="4">Opening balance</td><td class="ledger-debit">—</td><td class="ledger-credit">—</td><td>{{ $balance($group['opening']) }}</td></tr>
        @foreach($group['rows'] as $entry)
        <tr><td>{{ \Carbon\Carbon::parse($entry['date'])->format('d-m-Y') }}</td><td>{{ $entry['voucher'] }}</td><td>{{ $entry['type'] }}</td><td>{{ $entry['item'] }}</td><td class="ledger-debit">{{ $weight($entry['debit']) }}</td><td class="ledger-credit">{{ $weight($entry['credit']) }}</td><td>{{ $balance($entry['balance']) }}</td></tr>
        @endforeach
        <tr><th colspan="4">Period totals / Closing balance</th><th class="ledger-debit">{{ $weight($group['debit']) }}</th><th class="ledger-credit">{{ $weight($group['credit']) }}</th><th>{{ $balance($group['closing']) }}</th></tr></tbody>
    </table></div>
    @endif
</div>
@empty
<p>No jobwork transactions found for these filters.</p>
@endforelse
