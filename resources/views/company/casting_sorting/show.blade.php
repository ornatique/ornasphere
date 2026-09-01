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
                                <th style="width: 360px;">Item Selected</th>
                                <th style="width: 220px;">Stock Type</th>
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
                                        'stock_type' => $row->stock_type ?: 'raw_material',
                                        'weight' => $row->weight,
                                        'quantity' => $row->quantity,
                                    ])->values()->all();
                                }
                                while (count($rows) < 10) {
                                    $rows[] = ['item_id' => '', 'stock_type' => 'raw_material', 'weight' => '', 'quantity' => ''];
                                }
                                $lastSortingRow = $rows[count($rows) - 1] ?? [];
                                $lastSortingRowHasData = !empty($lastSortingRow['item_id'] ?? '')
                                    || !empty($lastSortingRow['weight'] ?? '')
                                    || !empty($lastSortingRow['quantity'] ?? '');
                                if (count($rows) >= 10 && $lastSortingRowHasData) {
                                    $rows[] = ['item_id' => '', 'stock_type' => 'raw_material', 'weight' => '', 'quantity' => ''];
                                }
                            @endphp
                            @foreach($rows as $index => $row)
                            @php
                                $selectedItem = $items->firstWhere('id', (int) ($row['item_id'] ?? 0));
                                $selectedItemName = $selectedItem
                                    ? $selectedItem->item_name . ($selectedItem->item_code ? ' - ' . $selectedItem->item_code : '')
                                    : '';
                            @endphp
                            <tr data-sorting-row>
                                <td data-row-no>{{ $loop->iteration }}</td>
                                <td>
                                    <div class="sorting-search-select" data-sorting-search-select>
                                        <input type="hidden"
                                            name="rows[{{ $index }}][item_id]"
                                            value="{{ $row['item_id'] ?? '' }}"
                                            data-sorting-item-value>
                                        <input type="text"
                                            class="form-control sorting-item-search"
                                            value="{{ $selectedItemName }}"
                                            placeholder="Search / Select Item"
                                            autocomplete="off"
                                            data-sorting-item-search>
                                        <div class="sorting-options" data-sorting-options></div>
                                    </div>
                                </td>
                                <td>
                                    <select name="rows[{{ $index }}][stock_type]" class="form-control casting-search-select" data-stock-type>
                                        <option value="raw_material" @selected(($row['stock_type'] ?? 'raw_material') === 'raw_material')>Raw Material</option>
                                        <option value="finished_item" @selected(($row['stock_type'] ?? '') === 'finished_item')>Finished Item</option>
                                        <option value="scrap" @selected(($row['stock_type'] ?? '') === 'scrap')>Scrap</option>
                                        <option value="repair" @selected(($row['stock_type'] ?? '') === 'repair')>Repair</option>
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
                                <th colspan="3">Total</th>
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
    .sorting-search-select { position: relative; width: 380px; max-width: 100%; }
    .sorting-item-search { width: 100%; background: #302d55; color: #fff; border-color: rgba(142, 162, 255, 0.45); }
    .sorting-item-search:focus { background: #302d55; color: #fff; border-color: #8ea2ff; box-shadow: none; }
    .sorting-options { display: none; position: absolute; top: calc(100% + 2px); left: 0; right: 0; z-index: 1055; max-height: 400px; overflow-y: auto; background: #302d55; border: 1px solid #8ea2ff; box-shadow: 0 12px 28px rgba(0, 0, 0, 0.28); }
    .sorting-options.is-open { display: block; }
    .sorting-option { padding: 8px 12px; color: #fff; cursor: pointer; line-height: 1.25; }
    .sorting-option:hover, .sorting-option.is-active { background: #2d6cdf; color: #fff; }
    .sorting-option-muted { padding: 8px 12px; color: #d8d8ef; font-weight: 600; }
    @media (max-width: 991px) { .casting-sorting-summary { grid-template-columns: repeat(2, minmax(150px, 1fr)); } }
    @media (max-width: 575px) { .casting-sorting-summary { grid-template-columns: 1fr; } }
</style>
@endpush

@push('scripts')
@php
    $sortingItemOptions = $items->map(function ($item) {
        return [
            'id' => $item->id,
            'name' => $item->item_name . ($item->item_code ? ' - ' . $item->item_code : ''),
            'item_name' => $item->item_name,
            'item_code' => $item->item_code,
        ];
    })->values();
@endphp
<script>
    const sortingItems = @json($sortingItemOptions);
    let sortingRowIndex = {{ count($rows) }};

    function escapeHtml(value) {
        const span = document.createElement('span');
        span.textContent = value;
        return span.innerHTML;
    }

    function buildSortingSearchSelect(index) {
        return `
            <div class="sorting-search-select" data-sorting-search-select>
                <input type="hidden" name="rows[${index}][item_id]" value="" data-sorting-item-value>
                <input type="text" class="form-control sorting-item-search" value="" placeholder="Search / Select Item" autocomplete="off" data-sorting-item-search>
                <div class="sorting-options" data-sorting-options></div>
            </div>
        `;
    }

    function buildSortingRow(index) {
        const row = document.createElement('tr');
        row.setAttribute('data-sorting-row', '');
        row.innerHTML = `
            <td data-row-no></td>
            <td>
                ${buildSortingSearchSelect(index)}
            </td>
            <td>
                <select name="rows[${index}][stock_type]" class="form-control casting-search-select" data-stock-type>
                    <option value="raw_material" selected>Raw Material</option>
                    <option value="finished_item">Finished Item</option>
                    <option value="scrap">Scrap</option>
                    <option value="repair">Repair</option>
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

    function initCastingSelects(context = document) {
        if (!window.jQuery || !$.fn.select2) {
            return;
        }

        $(context).find('.casting-search-select').each(function () {
            const $select = $(this);
            if ($select.hasClass('select2-hidden-accessible')) {
                return;
            }

            $select.select2({
                theme: 'bootstrap4',
                width: '100%',
                minimumResultsForSearch: 0
            });
        });
    }

    function rowHasSortingData(row) {
        return Array.from(row.querySelectorAll('input, select'))
            .filter((input) => !input.matches('[data-stock-type], [data-sorting-item-search]'))
            .some((input) => input.value !== '');
    }

    function closeSortingDropdowns(exceptWrapper = null) {
        document.querySelectorAll('[data-sorting-search-select]').forEach((wrapper) => {
            if (wrapper !== exceptWrapper) {
                wrapper.querySelector('[data-sorting-options]')?.classList.remove('is-open');
            }
        });
    }

    function renderSortingOptions(wrapper, keyword = '') {
        const optionsBox = wrapper.querySelector('[data-sorting-options]');
        const hiddenInput = wrapper.querySelector('[data-sorting-item-value]');
        const search = keyword.trim().toLowerCase();
        const selectedId = hiddenInput.value;
        const matches = sortingItems
            .filter((item) => {
                if (!search) {
                    return true;
                }

                return [item.name, item.item_name, item.item_code]
                    .filter(Boolean)
                    .some((value) => String(value).toLowerCase().includes(search));
            })
            .slice(0, 80);

        if (matches.length === 0) {
            optionsBox.innerHTML = '<div class="sorting-option-muted">No item found</div>';
        } else {
            optionsBox.innerHTML = matches.map((item) => {
                const activeClass = String(item.id) === String(selectedId) ? ' is-active' : '';
                return `<div class="sorting-option${activeClass}" data-sorting-option data-id="${item.id}" data-name="${escapeHtml(item.name)}">${escapeHtml(item.name)}</div>`;
            }).join('');
        }

        closeSortingDropdowns(wrapper);
        optionsBox.classList.add('is-open');
    }

    function ensureBlankLastSortingRow() {
        const tbody = document.getElementById('casting-sorting-rows');
        const rows = tbody.querySelectorAll('[data-sorting-row]');
        const lastRow = rows[rows.length - 1];
        if (lastRow && rowHasSortingData(lastRow)) {
            const newRow = buildSortingRow(sortingRowIndex++);
            tbody.appendChild(newRow);
            initCastingSelects(newRow);
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
            event.target.closest('[data-sorting-row]').querySelectorAll('[data-stock-type]').forEach((input) => {
                input.value = 'raw_material';
                if (window.jQuery && $.fn.select2) {
                    $(input).trigger('change.select2');
                }
            });
            event.target.closest('[data-sorting-row]').querySelectorAll('[data-sorting-options]').forEach((box) => box.classList.remove('is-open'));
        } else {
            event.target.closest('[data-sorting-row]').remove();
        }
        ensureBlankLastSortingRow();
        refreshSortingRows();
    });

    document.addEventListener('input', function (event) {
        if (event.target.matches('[data-sorting-item-search]')) {
            const wrapper = event.target.closest('[data-sorting-search-select]');
            wrapper.querySelector('[data-sorting-item-value]').value = '';
            renderSortingOptions(wrapper, event.target.value);
            refreshSortingRows();
            return;
        }

        if (event.target.matches('[data-sorting-weight], [data-sorting-quantity]')) {
            ensureBlankLastSortingRow();
            refreshSortingRows();
        }
    });

    document.addEventListener('focusin', function (event) {
        if (event.target.matches('[data-sorting-item-search]')) {
            renderSortingOptions(event.target.closest('[data-sorting-search-select]'), event.target.value);
        }
    });

    document.addEventListener('click', function (event) {
        const option = event.target.closest('[data-sorting-option]');
        if (option) {
            const wrapper = option.closest('[data-sorting-search-select]');
            wrapper.querySelector('[data-sorting-item-value]').value = option.dataset.id;
            wrapper.querySelector('[data-sorting-item-search]').value = option.dataset.name;
            wrapper.querySelector('[data-sorting-options]').classList.remove('is-open');
            ensureBlankLastSortingRow();
            refreshSortingRows();
            return;
        }

        if (!event.target.closest('[data-sorting-search-select]')) {
            closeSortingDropdowns();
        }
    });

    initCastingSelects();
    ensureBlankLastSortingRow();
    refreshSortingRows();
</script>
@endpush
