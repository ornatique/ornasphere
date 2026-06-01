@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">Receive / Return / Purchase</h4>
        </div>
        <div class="card-body">
            <form class="row g-3 mb-3 align-items-end" id="customerLoadForm">
                <div class="col-md-5">
                    <label>Party (Active Customer)</label>
                    <select name="customer_id" id="customer_id" class="form-select">
                        <option value="">Select Party</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}" {{ (int)$selectedCustomerId === (int)$c->id ? 'selected' : '' }}>
                                {{ $c->name }}{{ !empty($c->city) ? ' - ' . $c->city : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-primary w-100" id="btnLoadCustomer">Load</button>
                </div>
                <div class="col-md-3">
                    <a href="#" class="btn btn-danger w-100" id="btnAdvanceHistoryPdf" target="_blank">Export PDF History</a>
                </div>
            </form>

            <div class="row g-2 mb-4">
                <div class="col-md-3" id="cashBalanceCard">
                    <div class="balance-card">
                        <small id="cashBalanceLabel">Cash Balance Credit</small>
                        <h5 class="mb-0" id="cashBalance">{{ number_format(abs((float)($balance['cash_balance'] ?? 0)), 2) }}</h5>
                    </div>
                </div>
                <div class="col-md-3" id="goldBalanceCard">
                    <div class="balance-card">
                        <small id="goldBalanceLabel">Gold Fine Balance Credit</small>
                        <h5 class="mb-0" id="goldBalance">{{ number_format(abs((float)data_get($balance, 'metal_balance.gold', 0)), 3) }}</h5>
                    </div>
                </div>
                <div class="col-md-3" id="silverBalanceCard">
                    <div class="balance-card">
                        <small id="silverBalanceLabel">Silver Fine Balance Credit</small>
                        <h5 class="mb-0" id="silverBalance">{{ number_format(abs((float)data_get($balance, 'metal_balance.silver', 0)), 3) }}</h5>
                    </div>
                </div>
                <div class="col-md-3" id="otherBalanceCard">
                    <div class="balance-card">
                        <small id="otherBalanceLabel">Other Metal Balance Credit</small>
                        <h5 class="mb-0" id="otherBalance">{{ number_format(abs((float)data_get($balance, 'metal_balance.other', 0)), 3) }}</h5>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('company.sales.advance.store', $company->slug) }}" class="row g-3 mb-4" id="advanceForm">
                @csrf
                <input type="hidden" name="entry_type" value="receive_amount">
                <div class="col-md-2">
                    <label>Date</label>
                    <input type="date" name="entry_date" class="form-control" value="{{ now()->format('Y-m-d') }}" required>
                </div>
                <div class="col-md-3">
                    <label>Party</label>
                    <select name="customer_id" id="entry_customer_id" class="form-select" required>
                        <option value="">Select Party</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}" {{ (int)$selectedCustomerId === (int)$c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label>Payment Mode</label>
                    <select name="payment_mode" class="form-select">
                        <option value="">Select</option>
                        <option value="cash">Cash</option>
                        <option value="online">Online</option>
                        <option value="upi">UPI</option>
                        <option value="bank">Bank</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label>Amount</label>
                    <input type="number" step="any" min="0" name="amount" id="amount" class="form-control" placeholder="0.00" required>
                </div>
                <div class="col-md-6">
                    <label>Remarks</label>
                    <input type="text" name="remarks" class="form-control" maxlength="255" placeholder="Remarks">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-success w-100">Save Entry</button>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="button" class="btn btn-warning w-100" id="btnOpenConvertModal">
                        Balance Conversion
                    </button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered" id="advanceLedgerTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date & Time</th>
                            <th>Party</th>
                            <th>Entry Type</th>
                            <th>Mode</th>
                            <th>Cash In</th>
                            <th>Cash Out</th>
                            <th>Metal Type</th>
                            <th>Metal In</th>
                            <th>Metal Out</th>
                            <th>Rate</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody id="advanceLedgerBody">
                        <tr>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td class="text-center">Select party and click Load</td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="convertModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Balance Conversion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('company.sales.advance.store', $company->slug) }}" id="convertForm">
                @csrf
                <input type="hidden" name="entry_type" id="convert_entry_type" value="convert_to_metal">
                <input type="hidden" name="customer_id" id="convert_customer_id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label>Date</label>
                            <input type="date" name="entry_date" class="form-control" value="{{ now()->format('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-3">
                            <label>Conversion Type</label>
                            <select id="convert_type" class="form-select" required>
                                <option value="convert_to_metal">Rupees To Metal</option>
                                <option value="convert_to_rupees">Metal To Rupees</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>Metal Type</label>
                            <select name="metal_type" id="convert_metal_type" class="form-select" required>
                                <option value="">Select</option>
                                <option value="gold">Gold</option>
                                <option value="silver">Silver</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>Metal Rate</label>
                            <input type="number" step="any" min="0.01" name="rate" id="convert_rate" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="col-md-3">
                            <label id="convert_amount_label">Amount From Advance</label>
                            <input type="number" step="any" min="0.01" name="amount" id="convert_amount" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="col-md-3">
                            <label id="convert_preview_label">Fine Weight (Auto)</label>
                            <input type="text" id="convert_fine_preview" class="form-control" value="0.000" readonly>
                        </div>
                        <div class="col-md-6">
                            <label>Remarks</label>
                            <input type="text" name="remarks" id="convert_remarks" class="form-control" maxlength="255" placeholder="Remarks">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Convert & Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    #customerLoadForm .btn {
        min-height: 44px;
    }

    .balance-card {
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 10px;
        padding: 10px 12px;
        min-height: 76px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        background: rgba(255, 255, 255, 0.02);
    }

    .balance-card small {
        opacity: .95;
        margin-bottom: 4px;
        font-size: 17px;
        font-weight: 700;
        letter-spacing: .2px;
    }

    .balance-card h5 {
        font-size: 28px;
        font-weight: 800;
        line-height: 1.1;
        color: #ffffff;
        letter-spacing: .3px;
    }

    #advanceLedgerTable th {
        white-space: nowrap;
    }

    #advanceLedgerTable td {
        vertical-align: middle;
    }

    #convertModal .modal-dialog {
        max-width: 1100px;
    }

    #convertModal .modal-content {
        border-radius: 12px;
        border: 1px solid rgba(255, 255, 255, 0.12);
        overflow: hidden;
    }

    #convertModal .modal-header {
        padding: 14px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.12);
    }

    #convertModal .modal-title {
        font-weight: 700;
        letter-spacing: .2px;
    }

    #convertModal .modal-body {
        padding: 20px;
    }

    #convertModal .modal-footer {
        padding: 14px 20px;
        border-top: 1px solid rgba(255, 255, 255, 0.12);
        gap: 8px;
    }

    #convertModal label {
        display: inline-block;
        margin-bottom: 6px;
        font-weight: 600;
    }

    #convertModal .form-control,
    #convertModal .form-select {
        min-height: 46px;
        border: 1px solid rgba(255, 255, 255, 0.28);
    }

    #convertModal .form-select {
        appearance: auto;
        -webkit-appearance: auto;
        -moz-appearance: auto;
        padding-right: 2.25rem;
    }

    #convertModal .form-control:focus,
    #convertModal .form-select:focus {
        border-color: #4f8cff;
        box-shadow: 0 0 0 0.15rem rgba(79, 140, 255, 0.25);
    }
</style>
@endpush

@push('scripts')
<script>
$(function () {
    let availableCashRaw = 0;
    let availableMetal = { gold: 0, silver: 0, other: 0 };
    let advanceLedgerDt = null;

    function setBal(labelId, valueId, labelText, raw, decimals) {
        const type = raw >= 0 ? 'Credit' : 'Debit';
        $(labelId).text(labelText + ' ' + type);
        $(valueId).text(Math.abs(parseFloat(raw || 0)).toFixed(decimals));
    }

    let currentCustomerPdfKey = '';

    function updateHistoryPdfLink(customerKey) {
        const base = "{{ route('company.sales.advance.pdf', $company->slug) }}";
        if (!customerKey) {
            $('#btnAdvanceHistoryPdf').attr('href', '#');
            return;
        }
        $('#btnAdvanceHistoryPdf').attr('href', base + '?customer_key=' + encodeURIComponent(customerKey));
    }

    function toggleMetalCards(goldRaw, silverRaw, otherRaw) {
        const hasGold = Math.abs(parseFloat(goldRaw || 0)) > 0.000001;
        const hasSilver = Math.abs(parseFloat(silverRaw || 0)) > 0.000001;
        const hasOther = Math.abs(parseFloat(otherRaw || 0)) > 0.000001;
        $('#goldBalanceCard').toggle(hasGold);
        $('#silverBalanceCard').toggle(hasSilver);
        $('#otherBalanceCard').toggle(hasOther);
    }

    function initAdvanceTable() {
        if (!$.fn.DataTable) return;
        if (advanceLedgerDt) {
            advanceLedgerDt.destroy();
            advanceLedgerDt = null;
        }
        advanceLedgerDt = $('#advanceLedgerTable').DataTable({
            paging: true,
            searching: true,
            ordering: true,
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            order: [[1, 'desc'], [0, 'desc']],
            columnDefs: [
                { orderable: false, targets: [0] }
            ]
        });
    }

    function setTableMessage(msg) {
        if (advanceLedgerDt) {
            advanceLedgerDt.destroy();
            advanceLedgerDt = null;
        }
        $('#advanceLedgerBody').html(
            '<tr>' +
                '<td></td><td></td><td></td><td></td><td></td><td></td>' +
                '<td class="text-center">' + msg + '</td>' +
                '<td></td><td></td><td></td><td></td><td></td>' +
            '</tr>'
        );
    }

    function loadCustomerLedger(customerId) {
        if (!customerId) {
            currentCustomerPdfKey = '';
            availableCashRaw = 0;
            availableMetal = { gold: 0, silver: 0, other: 0 };
            setBal('#cashBalanceLabel', '#cashBalance', 'Cash Balance', 0, 2);
            setBal('#goldBalanceLabel', '#goldBalance', 'Gold Fine Balance', 0, 3);
            setBal('#silverBalanceLabel', '#silverBalance', 'Silver Fine Balance', 0, 3);
            setBal('#otherBalanceLabel', '#otherBalance', 'Other Metal Balance', 0, 3);
            toggleMetalCards(0, 0, 0);
            setTableMessage('Select party and click Load');
            return;
        }

        setTableMessage('Loading...');
        $.get('{{ route('company.sales.advance.data', $company->slug) }}', { customer_id: customerId })
            .done(function (resp) {
                if (!resp || !resp.success) {
                    setTableMessage('No entries found');
                    return;
                }

                const b = resp.balance || {};
                const m = b.metal_balance || {};
                availableCashRaw = parseFloat(b.cash_balance || 0);
                availableMetal = {
                    gold: parseFloat(m.gold || 0),
                    silver: parseFloat(m.silver || 0),
                    other: parseFloat(m.other || 0)
                };
                setBal('#cashBalanceLabel', '#cashBalance', 'Cash Balance', parseFloat(b.cash_balance || 0), 2);
                  setBal('#goldBalanceLabel', '#goldBalance', 'Gold Fine Balance', parseFloat(m.gold || 0), 3);
                  setBal('#silverBalanceLabel', '#silverBalance', 'Silver Fine Balance', parseFloat(m.silver || 0), 3);
                  setBal('#otherBalanceLabel', '#otherBalance', 'Other Metal Balance', parseFloat(m.other || 0), 3);
                  toggleMetalCards(parseFloat(m.gold || 0), parseFloat(m.silver || 0), parseFloat(m.other || 0));
                  currentCustomerPdfKey = String(resp.customer_key || '');
                  updateHistoryPdfLink(currentCustomerPdfKey);
                  $('#advanceLedgerBody').html(resp.rows_html || (
                      '<tr>' +
                          '<td></td><td></td><td></td><td></td><td></td><td></td>' +
                        '<td class="text-center">No entries found</td>' +
                        '<td></td><td></td><td></td><td></td><td></td>' +
                    '</tr>'
                ));
                $('#entry_customer_id').val(String(customerId));
                $('#convert_customer_id').val(String(customerId));
                if (parseInt(resp.row_count || 0, 10) > 0) {
                    initAdvanceTable();
                } else {
                    setTableMessage('No entries found');
                }
            })
            .fail(function () {
                setTableMessage('Something went wrong. Please try again later.');
            });
    }

    $('#btnLoadCustomer').on('click', function () {
        const customerId = $('#customer_id').val();
        $('#entry_customer_id').val(customerId);
        currentCustomerPdfKey = '';
        updateHistoryPdfLink('');
        loadCustomerLedger(customerId);
    });

    $('#customer_id').on('change', function () {
        const cid = $(this).val();
        $('#entry_customer_id').val(cid);
        $('#convert_customer_id').val(cid);
        currentCustomerPdfKey = '';
        updateHistoryPdfLink('');
    });

    $('#btnAdvanceHistoryPdf').on('click', function (e) {
        const cid = $('#customer_id').val() || $('#entry_customer_id').val();
        if (!cid) {
            e.preventDefault();
            alert('Please select party and click Load first.');
            return;
        }
        if (!currentCustomerPdfKey) {
            e.preventDefault();
            alert('Please click Load first.');
            return;
        }
        updateHistoryPdfLink(currentCustomerPdfKey);
    });

    $('#btnOpenConvertModal').on('click', function () {
        const cid = $('#customer_id').val() || $('#entry_customer_id').val();
        if (!cid) {
            alert('Please select party and click Load first.');
            return;
        }
        $('#convert_customer_id').val(cid);
        updateAutoConvertRemark();
        if (window.bootstrap && bootstrap.Modal) {
            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('convertModal'));
            modal.show();
        } else {
            $('#convertModal').modal('show');
        }
    });

    function updateFinePreview() {
        const mode = String($('#convert_type').val() || 'convert_to_metal');
        const amt = parseFloat($('#convert_amount').val() || 0);
        const rate = parseFloat($('#convert_rate').val() || 0);
        if (mode === 'convert_to_rupees') {
            const rupees = (amt > 0 && rate > 0) ? (amt * rate) : 0;
            $('#convert_fine_preview').val(rupees.toFixed(2));
        } else {
            const fine = (amt > 0 && rate > 0) ? (amt / rate) : 0;
            $('#convert_fine_preview').val(fine.toFixed(3));
        }
    }

    function syncConvertUiMode() {
        const mode = String($('#convert_type').val() || 'convert_to_metal');
        $('#convert_entry_type').val(mode);
        if (mode === 'convert_to_rupees') {
            $('#convert_amount_label').text('Fine Weight From Metal');
            $('#convert_preview_label').text('Rupees (Auto)');
            $('#convert_amount').attr('step', '0.001');
        } else {
            $('#convert_amount_label').text('Amount From Advance');
            $('#convert_preview_label').text('Fine Weight (Auto)');
            $('#convert_amount').attr('step', '0.01');
        }
        updateAutoConvertRemark();
        updateFinePreview();
    }

    function updateAutoConvertRemark() {
        const mode = String($('#convert_type').val() || 'convert_to_metal');
        const metal = String($('#convert_metal_type').val() || '').trim().toLowerCase();
        if (!metal) {
            $('#convert_remarks').val('');
            return;
        }
        const metalTitle = metal.charAt(0).toUpperCase() + metal.slice(1);
        const text = mode === 'convert_to_rupees'
            ? `${metalTitle} To Rupees Convert`
            : `Rupees To ${metalTitle} Convert`;
        $('#convert_remarks').val(text);
    }

    $('#convert_amount, #convert_rate').on('input', updateFinePreview);
    $('#convert_type').on('change', syncConvertUiMode);
    $('#convert_metal_type').on('change', updateAutoConvertRemark);

    $('#convertForm').on('submit', function (e) {
        const mode = String($('#convert_type').val() || 'convert_to_metal');
        const metalType = String($('#convert_metal_type').val() || '');
        const amt = parseFloat($('#convert_amount').val() || 0);
        if (mode === 'convert_to_rupees') {
            const availMetal = parseFloat((availableMetal[metalType] || 0));
            if (amt > availMetal) {
                e.preventDefault();
                alert('Fine weight exceeds available metal balance.');
                return;
            }
        } else {
            if (amt > availableCashRaw) {
                e.preventDefault();
                alert('Amount exceeds available cash advance balance.');
                return;
            }
        }
    });

    toggleMetalCards(
        parseFloat("{{ (float)data_get($balance, 'metal_balance.gold', 0) }}"),
        parseFloat("{{ (float)data_get($balance, 'metal_balance.silver', 0) }}"),
        parseFloat("{{ (float)data_get($balance, 'metal_balance.other', 0) }}")
    );

    const initialCustomerId = '{{ (int)$selectedCustomerId }}';
    if (initialCustomerId && initialCustomerId !== '0') {
        $('#customer_id').val(initialCustomerId);
        $('#entry_customer_id').val(initialCustomerId);
        updateHistoryPdfLink('');
        loadCustomerLedger(initialCustomerId);
    } else {
        updateHistoryPdfLink('');
    }
    syncConvertUiMode();
});
</script>
@endpush
