<div class="table-responsive"><table class="table table-bordered ledger-table">
    <thead><tr><th>Date</th><th>Particulars</th><th class="ledger-debit">Debit — Issued</th><th class="ledger-credit">Credit — Received</th><th>Running Balance</th></tr></thead>
    <tbody>
        <tr><td>—</td><td>Opening balance brought forward</td><td class="ledger-debit">—</td><td class="ledger-credit">—</td><td>{{ $balance($group['opening']) }}</td></tr>
        @foreach($group['rows'] as $entry)
        <tr><td>{{ \Carbon\Carbon::parse($entry['date'])->format('d-m-Y') }}</td><td>{{ $entry['particulars'] }}</td><td class="ledger-debit">{{ $weight($entry['debit']) }}</td><td class="ledger-credit">{{ $weight($entry['credit']) }}</td><td>{{ $balance($entry['balance']) }}</td></tr>
        @endforeach
        <tr><th colspan="2">Period totals / Closing pending</th><th class="ledger-debit">{{ $weight($group['debit']) }}</th><th class="ledger-credit">{{ $weight($group['credit']) }}</th><th>{{ $balance($group['closing']) }}</th></tr>
    </tbody>
</table></div>
