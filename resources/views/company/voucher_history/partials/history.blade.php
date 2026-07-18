<div class="voucher-summary-grid mb-2">
    <div><span>Voucher No</span><strong>{{ $voucher->voucher_no }}</strong></div>
    <div><span>Voucher Date</span><strong>{{ optional($voucher->voucher_date)->format('d-m-Y') }}</strong></div>
    <div><span>Gross Wt</span><strong>{{ $history['summary']['gross_wt'] }}</strong></div>
    <div><span>Buch Wt</span><strong>{{ $history['summary']['buch_wt'] }}</strong></div>
    <div><span>Net Wt</span><strong>{{ $history['summary']['net_wt'] }}</strong></div>
    <div><span>Silver Wt</span><strong>{{ $history['summary']['silver_wt'] }}</strong></div>
    <div><span>Total Pcs</span><strong>{{ $history['summary']['total_pcs'] }}</strong></div>
    <div><span>Created At</span><strong>{{ optional($voucher->created_at)->format('d-m-Y h:i A') }}</strong></div>
</div>

<div class="voucher-history-timeline">
    <div class="history-section">
        <div class="history-step"><span>1</span></div>
        <div class="history-title">
            <h5>Casting Heating</h5>
            <span class="status-pill">{{ $history['casting_heating']['in_bhati_count'] }} In Bhati / {{ $history['summary']['total_pcs'] }} Pcs</span>
        </div>
        <div class="history-table-wrap">
            <table class="table table-bordered table-sm history-table">
                <thead><tr><th>Sr No</th><th>Buch No</th><th>In Bhati</th><th>Checked At</th></tr></thead>
                <tbody>
                    @forelse($history['casting_heating']['rows'] as $row)
                    <tr><td>{{ $loop->iteration }}</td><td>{{ $row['buch_no'] }}</td><td>{{ $row['in_bhati'] }}</td><td>{{ $row['checked_at'] }}</td></tr>
                    @empty
                    <tr><td colspan="4" class="text-center">No data found</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="history-section">
        <div class="history-step"><span>2</span></div>
        <div class="history-title"><h5>Casting Metal Issue</h5></div>
        <div class="history-table-wrap">
            <table class="table table-bordered table-sm history-table">
                <thead><tr><th>Sr No</th><th>Buch No</th><th>Silver Wt</th><th>Issued At</th></tr></thead>
                <tbody>
                    @forelse($history['casting_metal_issue']['rows'] as $row)
                    <tr><td>{{ $loop->iteration }}</td><td>{{ $row['buch_no'] }}</td><td>{{ $row['silver_wt'] }}</td><td>{{ $row['issued_at'] }}</td></tr>
                    @empty
                    <tr><td colspan="4" class="text-center">No data found</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="history-section">
        <div class="history-step"><span>3</span></div>
        <div class="history-title"><h5>Casting Receive</h5></div>
        <div class="history-table-wrap">
            <table class="table table-bordered table-sm history-table">
                <thead><tr><th>Sr No</th><th>Buch No</th><th>Release Tree Wt</th><th>Tree Bhuko</th><th>Loss</th><th>Received At</th></tr></thead>
                <tbody>
                    @forelse($history['casting_receive']['rows'] as $row)
                    <tr><td>{{ $loop->iteration }}</td><td>{{ $row['buch_no'] }}</td><td>{{ $row['release_tree_wt'] }}</td><td>{{ $row['release_tree_bhuko'] }}</td><td>{{ $row['loss'] }}</td><td>{{ $row['received_at'] }}</td></tr>
                    @empty
                    <tr><td colspan="6" class="text-center">No data found</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="history-section">
        <div class="history-step"><span>4</span></div>
        <div class="history-title"><h5>Tree Cutting Issue</h5></div>
        <div class="history-table-wrap">
            <table class="table table-bordered table-sm history-table">
                <thead><tr><th>Sr No</th><th>Buch No</th><th>Worker</th><th>Receive Tree Wt</th><th>Issued At</th></tr></thead>
                <tbody>
                    @forelse($history['tree_cutting_issue']['rows'] as $row)
                    <tr><td>{{ $loop->iteration }}</td><td>{{ $row['buch_no'] }}</td><td>{{ $row['worker'] }}</td><td>{{ $row['receive_tree_wt'] }}</td><td>{{ $row['issued_at'] }}</td></tr>
                    @empty
                    <tr><td colspan="5" class="text-center">No data found</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="history-section">
        <div class="history-step"><span>5</span></div>
        <div class="history-title"><h5>Tree Cutting Receive</h5></div>
        <div class="history-table-wrap">
            <table class="table table-bordered table-sm history-table">
                <thead><tr><th>Sr No</th><th>Buch No</th><th>Worker</th><th>Receive Pc Wt</th><th>Tree Bhuko</th><th>Loss</th><th>Received At</th></tr></thead>
                <tbody>
                    @forelse($history['tree_cutting_receive']['rows'] as $row)
                    <tr><td>{{ $loop->iteration }}</td><td>{{ $row['buch_no'] }}</td><td>{{ $row['worker'] }}</td><td>{{ $row['receive_pc_wt'] }}</td><td>{{ $row['receive_tree_bhuko'] }}</td><td>{{ $row['loss'] }}</td><td>{{ $row['received_at'] }}</td></tr>
                    @empty
                    <tr><td colspan="7" class="text-center">No data found</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="history-section">
        <div class="history-step"><span>6</span></div>
        <div class="history-title"><h5>Casting Sorting</h5></div>
        <div class="history-table-wrap">
            <table class="table table-bordered table-sm history-table">
                <thead><tr><th>Sr No</th><th>Item</th><th>Weight</th><th>Quantity</th><th>Sorted At</th></tr></thead>
                <tbody>
                    @forelse($history['casting_sorting']['rows'] as $row)
                    <tr><td>{{ $loop->iteration }}</td><td>{{ $row['item'] }}</td><td>{{ $row['weight'] }}</td><td>{{ $row['quantity'] }}</td><td>{{ $row['sorted_at'] }}</td></tr>
                    @empty
                    <tr><td colspan="5" class="text-center">No data found</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
