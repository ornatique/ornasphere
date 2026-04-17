@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">

    <div class="card">

        <div class="card-header">
            <h4 class="mb-0">Label Printing</h4>
        </div>

        <div class="card-body border-bottom">
            <form id="labelFilterForm" method="GET" action="{{ route('company.item_sets.qrList', $company->slug) }}" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label>From Date</label>
                    <input type="date" name="from_date" id="from_date" value="{{ $fromDate ?? request('from_date') }}" class="form-control">
                </div>

                <div class="col-md-3">
                    <label>To Date</label>
                    <input type="date" name="to_date" id="to_date" value="{{ $toDate ?? request('to_date') }}" class="form-control">
                </div>

                <div class="col-md-3">
                    <label>Item</label>
                    <select name="item_id" id="item_id" class="form-select">
                        <option value="">All Items</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}" {{ (string)request('item_id') === (string)$item->id ? 'selected' : '' }}>
                                {{ $item->item_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3 d-flex gap-2">
                    <button type="button" id="btnShow" class="btn btn-primary w-50">Show</button>
                    <a href="{{ route('company.item_sets.qrList', $company->slug) }}" class="btn btn-secondary w-50">Reset</a>
                </div>
            </form>
        </div>

        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <strong>Total Labels:</strong> <span id="totalLabels">0</span>
                    <span class="ms-3"><strong>Selected QR:</strong> <span id="selectedLabels">0</span></span>
                </div>
                <button type="button" class="btn btn-success" onclick="printSelected()">Print Selected</button>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="labelListTable">
                    <thead>
                        <tr>
                            <th width="50">
                                <input type="checkbox" id="selectAll">
                            </th>
                            <th>Item</th>
                            <th>Label Code</th>
                            <th>Gross Wt</th>
                            <th>Other Wt</th>
                            <th>Net Wt</th>
                            <th>Sale Other</th>
                            <th>Date Time</th>
                        </tr>
                    </thead>

                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>
@endsection


@push('scripts')
<script>
let selectedIds = new Set();
function updateSelectedCount() {
    $('#selectedLabels').text(selectedIds.size);
}
function getCurrentFilters() {
    return {
        from_date: $('#from_date').val(),
        to_date: $('#to_date').val(),
        item_id: $('#item_id').val(),
    };
}
function fetchAllFilteredIds() {
    return $.get("{{ route('company.item_sets.qrList', $company->slug) }}", {
        ...getCurrentFilters(),
        only_ids: 1
    });
}

const table = $('#labelListTable').DataTable({
    processing: true,
    serverSide: true,
    ajax: {
        url: "{{ route('company.item_sets.qrList', $company->slug) }}",
        data: function (d) {
            Object.assign(d, getCurrentFilters());
        }
    },
    columns: [
        { data: 'select', name: 'select', orderable: false, searchable: false },
        { data: 'item_name', name: 'item_name' },
        { data: 'label_code', name: 'qr_code' },
        { data: 'gross_weight', name: 'gross_weight' },
        { data: 'other_weight', name: 'other' },
        { data: 'net_weight', name: 'net_weight' },
        { data: 'sale_other', name: 'sale_other' },
        { data: 'date_time', name: 'printed_at' }
    ],
    order: [[7, 'desc']],
    drawCallback: function(settings) {
        $('#totalLabels').text(settings.json ? settings.json.recordsFiltered : 0);
        bindSelectionState();
    }
});

function bindSelectionState() {
    $('.qrCheckbox').each(function () {
        const id = $(this).val();
        const defaultChecked = String($(this).data('default-checked')) === '1';

        if (defaultChecked) {
            selectedIds.add(id);
        }
    });

    $('.qrCheckbox').each(function () {
        const id = $(this).val();
        $(this).prop('checked', selectedIds.has(id));
    });

    const filteredTotal = parseInt($('#totalLabels').text(), 10) || 0;
    $('#selectAll').prop('checked', filteredTotal > 0 && selectedIds.size === filteredTotal);
    updateSelectedCount();
}

$('#btnShow').on('click', function () {
    table.ajax.reload();
});

$('#selectAll').on('change', async function () {
    const checked = this.checked;

    if (checked) {
        try {
            const response = await fetchAllFilteredIds();
            const ids = Array.isArray(response.ids) ? response.ids : [];
            selectedIds = new Set(ids.map(String));
            $('.qrCheckbox').prop('checked', true);
            updateSelectedCount();
        } catch (e) {
            $('#selectAll').prop('checked', false);
            alert('Unable to select all rows. Please try again.');
        }
        return;
    }

    selectedIds.clear();
    $('.qrCheckbox').prop('checked', false);
    updateSelectedCount();
});

$(document).on('change', '.qrCheckbox', function () {
    const id = $(this).val();
    if (this.checked) selectedIds.add(id);
    else selectedIds.delete(id);
    updateSelectedCount();
});

function printSelected() {
    let ids = Array.from(selectedIds);

    if (!ids.length) {
        alert('Select at least one label');
        return;
    }

    const form = $('<form>', {
        method: 'POST',
        action: "{{ route('company.item_sets.printPdf.post', $company->slug) }}",
        target: '_blank'
    });

    form.append($('<input>', {
        type: 'hidden',
        name: '_token',
        value: "{{ csrf_token() }}"
    }));

    ids.forEach(function (id) {
        form.append($('<input>', {
            type: 'hidden',
            name: 'ids[]',
            value: id
        }));
    });

    $('body').append(form);
    form.trigger('submit');
    form.remove();
}
</script>
@endpush
