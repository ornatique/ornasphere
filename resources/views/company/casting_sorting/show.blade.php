@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header casting-sorting-header">
            <div>
                <h4 class="card-title mb-1">Casting Sorting</h4>
                <div class="casting-sorting-subtitle">{{ $voucher->voucher_no }} | {{ optional($voucher->voucher_date)->format('d-m-Y') }}</div>
            </div>
            <a href="{{ route('company.casting-sorting.index', $company->slug) }}" class="btn btn-secondary">Back</a>
        </div>

        <form method="POST" action="{{ route('company.casting-sorting.update', [$company->slug, \Illuminate\Support\Facades\Crypt::encryptString((string) $voucher->id)]) }}">
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

                <div class="casting-sorting-summary mb-3">
                    <div><span>Process</span><strong>{{ $voucher->process?->name ?? '-' }}</strong></div>
                    <div><span>Worker</span><strong>{{ $voucher->jobWorker?->name ?? '-' }}</strong></div>
                    <div><span>Total Pcs</span><strong>{{ (int) $treeReceiveCount }}</strong></div>
                    <div><span>Created At</span><strong>{{ optional($voucher->created_at)->format('d-m-Y h:i A') }}</strong></div>
                </div>

                <div class="table-responsive casting-sorting-scroll">
                    <table class="table table-bordered table-sm casting-sorting-table">
                        <thead>
                            <tr>
                                <th style="width: 80px;">Sr. No</th>
                                <th style="width: 420px;">Item Selected</th>
                                <th style="width: 260px;">Weight</th>
                                <th style="width: 220px;">Quantity</th>
                                <th style="width: 120px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="casting-sorting-rows">
                            @php
                                $rows = old('rows');
                                if ($rows === null) {
                                    $rows = $sortingItems->map(fn($row) => [
                                        'item_id' => $row->item_id,
                                        'weight' => $row->weight,
                                        'quantity' => $row->quantity,
                                    ])->values()->all();
                                }
                                while (count($rows) < 10) {
                                    $rows[] = ['item_id' => '', 'weight' => '', 'quantity' => ''];
                                }
                                $lastSortingRow = $rows[count($rows) - 1] ?? [];
                                $lastSortingRowHasData = !empty($lastSortingRow['item_id'] ?? '')
                                    || !empty($lastSortingRow['weight'] ?? '')
                                    || !empty($lastSortingRow['quantity'] ?? '');
                                if (count($rows) >= 10 && $lastSortingRowHasData) {
                                    $rows[] = ['item_id' => '', 'weight' => '', 'quantity' => ''];
                                }
                            @endphp
                            @foreach($rows as $index => $row)
                            <tr data-sorting-row>
                                <td data-row-no>{{ $loop->iteration }}</td>
                                <td>
                                    <select name="rows[{{ $index }}][item_id]" class="form-control sorting-item-select">
                                        <option value="">Select Item</option>
                                        @foreach($items as $item)
                                        <option value="{{ $item->id }}" @selected((string) ($row['item_id'] ?? '') === (string) $item->id)>
                                            {{ $item->item_name }}{{ $item->item_code ? ' - ' . $item->item_code : '' }}
                                        </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="number"
                                        name="rows[{{ $index }}][weight]"
                                        class="form-control"
                                        step="0.001"
                                        min="0"
                                        inputmode="decimal"
                                        data-sorting-weight
                                        value="{{ $row['weight'] ?? '' }}">
                                </td>
                                <td>
                                    <input type="number"
                                        name="rows[{{ $index }}][quantity]"
                                        class="form-control"
                                        step="1"
                                        min="0"
                                        inputmode="numeric"
                                        data-sorting-quantity
                                        value="{{ $row['quantity'] ?? '' }}">
                                </td>
                                <td>
                                    <button type="button" class="btn btn-danger btn-sm" data-remove-row>Remove</button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="2">Total</th>
                                <th><span id="sorting-weight-total">0.000</span></th>
                                <th><span id="sorting-quantity-total">0</span></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="card-footer text-end">
                <a href="{{ route('company.casting-sorting.index', $company->slug) }}" class="btn btn-secondary">Back</a>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('styles')
<style>
    .casting-sorting-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
    .casting-sorting-subtitle { color: #b8b8d4; font-size: 13px; }
    .casting-sorting-summary { display: grid; grid-template-columns: repeat(4, minmax(150px, 1fr)); gap: 10px; }
    .casting-sorting-summary > div { border: 1px solid rgba(255, 255, 255, 0.08); background: rgba(255, 255, 255, 0.035); padding: 10px 12px; }
    .casting-sorting-summary span { display: block; color: #b8b8d4; font-size: 12px; margin-bottom: 3px; }
    .casting-sorting-summary strong { color: #fff; font-size: 14px; }
    .casting-sorting-scroll { max-height: calc(100vh - 360px); overflow-y: auto; border: 1px solid rgba(255, 255, 255, 0.08); }
    .casting-sorting-table { margin-bottom: 0; table-layout: fixed; width: 100%; }
    .casting-sorting-table thead th { position: sticky; top: 0; z-index: 2; background: #25263a; }
    .casting-sorting-table tfoot th { position: sticky; bottom: 0; z-index: 2; background: #25263a; }
    .casting-sorting-table th, .casting-sorting-table td { padding: 0.65rem 0.8rem; vertical-align: middle; }
    .sorting-item-select { width: 380px; max-width: 100%; background: #fff; color: #1f2937; }
    @media (max-width: 991px) { .casting-sorting-summary { grid-template-columns: repeat(2, minmax(150px, 1fr)); } }
    @media (max-width: 575px) { .casting-sorting-summary { grid-template-columns: 1fr; } }
</style>
@endpush

@push('scripts')
<script>
    const sortingItems = @json($items->map(fn($item) => [
        'id' => $item->id,
        'name' => $item->item_name . ($item->item_code ? ' - ' . $item->item_code : ''),
    ])->values());
    let sortingRowIndex = {{ count($rows) }};

    function escapeHtml(value) {
        const span = document.createElement('span');
        span.textContent = value;
        return span.innerHTML;
    }

    function sortingItemOptions() {
        return '<option value="">Select Item</option>' + sortingItems.map((item) => {
            return `<option value="${item.id}">${escapeHtml(item.name)}</option>`;
        }).join('');
    }

    function buildSortingRow(index) {
        const row = document.createElement('tr');
        row.setAttribute('data-sorting-row', '');
        row.innerHTML = `
            <td data-row-no></td>
            <td>
                <select name="rows[${index}][item_id]" class="form-control sorting-item-select" data-sorting-item>
                    ${sortingItemOptions()}
                </select>
            </td>
            <td>
                <input type="number" name="rows[${index}][weight]" class="form-control" step="0.001" min="0" inputmode="decimal" data-sorting-weight>
            </td>
            <td>
                <input type="number" name="rows[${index}][quantity]" class="form-control" step="1" min="0" inputmode="numeric" data-sorting-quantity>
            </td>
            <td>
                <button type="button" class="btn btn-danger btn-sm" data-remove-row>Remove</button>
            </td>
        `;
        return row;
    }

    function rowHasSortingData(row) {
        return Array.from(row.querySelectorAll('input, select')).some((input) => input.value !== '');
    }

    function ensureBlankLastSortingRow() {
        const tbody = document.getElementById('casting-sorting-rows');
        const rows = tbody.querySelectorAll('[data-sorting-row]');
        const lastRow = rows[rows.length - 1];
        if (lastRow && rowHasSortingData(lastRow)) {
            tbody.appendChild(buildSortingRow(sortingRowIndex++));
        }
    }

    function refreshSortingRows() {
        document.querySelectorAll('#casting-sorting-rows [data-row-no]').forEach((cell, index) => {
            cell.textContent = index + 1;
        });

        let weightTotal = 0;
        let quantityTotal = 0;
        document.querySelectorAll('[data-sorting-weight]').forEach((input) => {
            const value = parseFloat(input.value);
            if (Number.isFinite(value)) {
                weightTotal += value;
            }
        });
        document.querySelectorAll('[data-sorting-quantity]').forEach((input) => {
            const value = parseInt(input.value, 10);
            if (Number.isFinite(value)) {
                quantityTotal += value;
            }
        });
        document.getElementById('sorting-weight-total').textContent = weightTotal.toFixed(3);
        document.getElementById('sorting-quantity-total').textContent = quantityTotal;
    }

    document.addEventListener('click', function (event) {
        if (!event.target.matches('[data-remove-row]')) {
            return;
        }

        const rows = document.querySelectorAll('#casting-sorting-rows [data-sorting-row]');
        if (rows.length <= 1) {
            event.target.closest('[data-sorting-row]').querySelectorAll('input, select').forEach((input) => input.value = '');
        } else {
            event.target.closest('[data-sorting-row]').remove();
        }
        ensureBlankLastSortingRow();
        refreshSortingRows();
    });

    document.addEventListener('input', function (event) {
        if (event.target.matches('[data-sorting-weight], [data-sorting-quantity]')) {
            ensureBlankLastSortingRow();
            refreshSortingRows();
        }
    });

    document.addEventListener('change', function (event) {
        if (event.target.matches('.sorting-item-select')) {
            ensureBlankLastSortingRow();
            refreshSortingRows();
        }
    });

    ensureBlankLastSortingRow();
    refreshSortingRows();
</script>
@endpush
