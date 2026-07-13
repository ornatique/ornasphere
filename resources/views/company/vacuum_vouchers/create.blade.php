@extends('company_layout.admin')

@section('content')
@php
    $blankRow = [
        'vacuum_buch_id' => '',
        'buch_no' => '',
        'gross_wt' => 0,
        'buch_wt' => 0,
        'net_wt' => 0,
        'silver_wt' => 0,
    ];
    $oldItems = old('items', isset($data) ? $data->items->map(fn($i) => [
        'vacuum_buch_id' => $i->vacuum_buch_id,
        'buch_no' => $i->buch_no,
        'gross_wt' => $i->gross_wt,
        'buch_wt' => $i->buch_wt,
        'net_wt' => $i->net_wt,
        'silver_wt' => $i->silver_wt,
    ])->toArray() : []);
    $minimumRows = isset($data)
        ? max(1, count($oldItems) + 1)
        : 10;
    while (count($oldItems) < $minimumRows) {
        $oldItems[] = $blankRow;
    }
@endphp
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title">{{ isset($data) ? 'Edit Vacuum Voucher' : 'Add Vacuum Voucher' }}</h4>
        </div>
        <form method="POST" action="{{ isset($data) ? route('company.vacuum-vouchers.update', [$company->slug, \Illuminate\Support\Facades\Crypt::encryptString((string) $data->id)]) : route('company.vacuum-vouchers.store', $company->slug) }}">
            @csrf

            <div class="card-body">
                @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Date *</label>
                            <input type="date" name="voucher_date" class="form-control"
                                value="{{ old('voucher_date', isset($data) ? optional($data->voucher_date)->toDateString() : now()->toDateString()) }}" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Process Name *</label>
                            <select name="vacuum_process_id" id="processSelect" class="form-select" required>
                                <option value="">Select Process</option>
                                @foreach($processes as $process)
                                <option value="{{ $process->id }}" {{ (string) old('vacuum_process_id', $data->vacuum_process_id ?? '') === (string) $process->id ? 'selected' : '' }}>
                                    {{ $process->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Worker Name *</label>
                            <select name="job_worker_id" id="workerSelect" class="form-select" required>
                                <option value="">Select Worker</option>
                                @foreach($jobWorkers as $worker)
                                <option value="{{ $worker->id }}" {{ (string) old('job_worker_id', $data->job_worker_id ?? '') === (string) $worker->id ? 'selected' : '' }}>
                                    {{ $worker->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Silver Formula Value *</label>
                            <input type="number" step="0.001" name="formula_value" id="formulaValue" class="form-control"
                                value="{{ old('formula_value', $data->formula_value ?? 11) }}" required>
                        </div>
                    </div>
                </div>

                <div id="voucherGrid" class="table-responsive mt-3">
                    <table class="table table-bordered table-sm vacuum-voucher-grid" id="voucherItemsTable">
                        <thead>
                            <tr>
                                <th style="width: 70px">Sr. No</th>
                                <th style="min-width: 220px">Buch No</th>
                                <th>Gross Wt</th>
                                <th>Buch Wt</th>
                                <th>Net Wt</th>
                                <th>Silver Wt</th>
                                <th style="width: 90px">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($oldItems as $idx => $row)
                            <tr>
                                <td class="sr-no">{{ $idx + 1 }}</td>
                                <td>
                                    <select name="items[{{ $idx }}][vacuum_buch_id]" class="form-select buch-select">
                                        <option value="">Search Buch No</option>
                                        @foreach($buchs as $buch)
                                        <option value="{{ $buch->id }}" data-weight="{{ (float) ($buch->weight ?? 0) }}" {{ (string) ($row['vacuum_buch_id'] ?? '') === (string) $buch->id ? 'selected' : '' }}>
                                            {{ $buch->buch_no }}
                                        </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="number" step="0.001" name="items[{{ $idx }}][gross_wt]" class="form-control gross-wt" value="{{ $row['gross_wt'] ?? 0 }}"></td>
                                <td><input type="number" step="0.001" name="items[{{ $idx }}][buch_wt]" class="form-control buch-wt" value="{{ $row['buch_wt'] ?? 0 }}"></td>
                                <td><input type="number" step="0.001" class="form-control net-wt" value="{{ $row['net_wt'] ?? 0 }}" readonly></td>
                                <td><input type="number" step="0.001" class="form-control silver-wt" value="{{ $row['silver_wt'] ?? 0 }}" readonly></td>
                                <td><button type="button" class="btn btn-sm btn-danger removeRowBtn">Remove</button></td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="2">Total</th>
                                <th id="grossTotal">0.000</th>
                                <th id="buchTotal">0.000</th>
                                <th id="netTotal">0.000</th>
                                <th id="silverTotal">0.000</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="form-group mt-3">
                    <label>Remarks</label>
                    <textarea name="remarks" class="form-control" rows="2">{{ old('remarks', $data->remarks ?? '') }}</textarea>
                </div>
            </div>

            <div class="card-footer text-end">
                <a href="{{ route('company.vacuum-vouchers.index', $company->slug) }}" class="btn btn-info">Back</a>
                <button type="submit" class="btn btn-primary">{{ isset($data) ? 'Update' : 'Save' }}</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('styles')
<style>
    .vacuum-voucher-grid th,
    .vacuum-voucher-grid td {
        padding: 0.45rem 0.55rem;
        vertical-align: middle;
    }

    .vacuum-voucher-grid .form-control,
    .vacuum-voucher-grid .form-select,
    .vacuum-voucher-grid .select2-container--bootstrap4 .select2-selection {
        min-height: 34px;
        padding-top: 0.25rem;
        padding-bottom: 0.25rem;
        font-size: 0.875rem;
    }

    .vacuum-voucher-grid .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.78rem;
    }
</style>
@endpush

@push('scripts')
<script>
let rowIndex = $('#voucherItemsTable tbody tr').length;
const buchOptions = @json($buchs->map(fn($b) => ['id' => $b->id, 'text' => $b->buch_no, 'weight' => (float) ($b->weight ?? 0)])->values());

const toNum = (value) => {
    const parsed = parseFloat(value);
    return Number.isFinite(parsed) ? parsed : 0;
};

const nfix = (value) => {
    const number = toNum(value);
    return (Math.abs(number) < 0.0005 ? 0 : number).toFixed(3);
};

function initSelect2($context) {
    $context.find('.buch-select').select2({
        theme: 'bootstrap4',
        width: '100%',
        placeholder: 'Search Buch No',
        allowClear: true
    });

    refreshBuchOptions();
}

function recalcRow($row) {
    const gross = toNum($row.find('.gross-wt').val());
    const buch = toNum($row.find('.buch-wt').val());
    const formula = toNum($('#formulaValue').val());
    const net = gross - buch;
    const silver = net * formula;

    $row.find('.net-wt').val(nfix(net));
    $row.find('.silver-wt').val(nfix(silver));
}

function recalcAll() {
    let grossTotal = 0;
    let buchTotal = 0;
    let netTotal = 0;
    let silverTotal = 0;

    $('#voucherItemsTable tbody tr').each(function (index) {
        const $row = $(this);
        $row.find('.sr-no').text(index + 1);
        recalcRow($row);
        grossTotal += toNum($row.find('.gross-wt').val());
        buchTotal += toNum($row.find('.buch-wt').val());
        netTotal += toNum($row.find('.net-wt').val());
        silverTotal += toNum($row.find('.silver-wt').val());
    });

    $('#grossTotal').text(nfix(grossTotal));
    $('#buchTotal').text(nfix(buchTotal));
    $('#netTotal').text(nfix(netTotal));
    $('#silverTotal').text(nfix(silverTotal));
}

function selectedBuchIds() {
    return $('.buch-select').map(function () {
        return String($(this).val() || '');
    }).get().filter(Boolean);
}

function refreshBuchOptions() {
    const selected = selectedBuchIds();

    $('.buch-select').each(function () {
        const $select = $(this);
        const current = String($select.val() || '');
        const currentOption = current
            ? $select.find(`option[value="${current}"]`).first().clone()
            : null;

        if ($select.data('select2')) {
            $select.select2('destroy');
        }

        $select.empty().append('<option value="">Search Buch No</option>');
        buchOptions.forEach(function (buch) {
            const id = String(buch.id);
            if (id !== current && selected.includes(id)) {
                return;
            }

            const option = new Option(buch.text, buch.id, false, id === current);
            $(option).attr('data-weight', buch.weight);
            $select.append(option);
        });

        if (current && !$select.find(`option[value="${current}"]`).length && currentOption && currentOption.length) {
            $select.append(currentOption.prop('selected', true));
        }

        $select.select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: 'Search Buch No',
            allowClear: true
        });
    });
}

function refreshGridState() {
    const ready = $('#processSelect').val() && $('#workerSelect').val();
    $('#voucherGrid').toggle(!!ready);
}

function buchOptionsHtml() {
    let html = '<option value="">Search Buch No</option>';
    buchOptions.forEach(function (buch) {
        html += `<option value="${buch.id}" data-weight="${buch.weight}">${$('<div>').text(buch.text).html()}</option>`;
    });
    return html;
}

function appendRow() {
    const row = `
        <tr>
            <td class="sr-no"></td>
            <td><select name="items[${rowIndex}][vacuum_buch_id]" class="form-select buch-select">${buchOptionsHtml()}</select></td>
            <td><input type="number" step="0.001" name="items[${rowIndex}][gross_wt]" class="form-control gross-wt" value="0"></td>
            <td><input type="number" step="0.001" name="items[${rowIndex}][buch_wt]" class="form-control buch-wt" value="0"></td>
            <td><input type="number" step="0.001" class="form-control net-wt" value="0.000" readonly></td>
            <td><input type="number" step="0.001" class="form-control silver-wt" value="0.000" readonly></td>
            <td><button type="button" class="btn btn-sm btn-danger removeRowBtn">Remove</button></td>
        </tr>`;

    const $row = $(row);
    $('#voucherItemsTable tbody').append($row);
    initSelect2($row);
    rowIndex++;
    recalcAll();
}

function rowHasValue($row) {
    return !!$row.find('.buch-select').val()
        || toNum($row.find('.gross-wt').val()) !== 0
        || toNum($row.find('.buch-wt').val()) !== 0;
}

function ensureTrailingRow() {
    const $last = $('#voucherItemsTable tbody tr:last-child');
    if ($last.length && rowHasValue($last)) {
        appendRow();
    }
}

$(document).on('change', '.buch-select', function () {
    const $row = $(this).closest('tr');
    const weight = toNum($(this).find('option:selected').data('weight'));
    $row.find('.buch-wt').val(nfix(weight));
    recalcAll();
    refreshBuchOptions();
    ensureTrailingRow();
});

$(document).on('input', '.gross-wt, .buch-wt, #formulaValue', function () {
    recalcAll();
    if ($(this).hasClass('gross-wt') || $(this).hasClass('buch-wt')) {
        ensureTrailingRow();
    }
});

$('#processSelect, #workerSelect').on('change', refreshGridState);

$(document).on('click', '.removeRowBtn', function () {
    if ($('#voucherItemsTable tbody tr').length <= 10) {
        const $row = $(this).closest('tr');
        $row.find('select').val('').trigger('change.select2');
        $row.find('.gross-wt, .buch-wt').val('0');
    } else {
        $(this).closest('tr').remove();
    }
    recalcAll();
    refreshBuchOptions();
});

$(document).on('keydown', '#voucherItemsTable tbody tr:last-child select, #voucherItemsTable tbody tr:last-child input', function (e) {
    if (e.key !== 'ArrowDown') return;
    e.preventDefault();
    appendRow();
    $('#voucherItemsTable tbody tr:last-child .buch-select').focus();
});

initSelect2($('#voucherItemsTable'));
refreshGridState();
recalcAll();
refreshBuchOptions();
</script>
@endpush
