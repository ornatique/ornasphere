@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <h4 class="card-title">{{ isset($data) ? 'Edit Vacuum Buch' : 'Create Vacuum Buch' }}</h4>
        </div>

        <form method="POST"
            action="{{ isset($data) ? route('company.vacuum-buchs.update', [$company->slug, \Illuminate\Support\Facades\Crypt::encryptString((string) $data->id)]) : route('company.vacuum-buchs.store', $company->slug) }}">
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

                @if(isset($data))
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Buch No *</label>
                                <input type="text" name="buch_no" class="form-control" required value="{{ old('buch_no', $data->buch_no ?? '') }}">
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Size (Inch)</label>
                                <input type="number" step="0.01" min="0" name="size_inch" class="form-control" value="{{ old('size_inch', $data->size_inch ?? '') }}">
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Weight</label>
                                <input type="number" step="0.001" min="0" name="weight" class="form-control" value="{{ old('weight', $data->weight ?? '') }}">
                            </div>
                        </div>
                    </div>
                @else
                    @php
                        $rows = old('rows');
                        if (!is_array($rows) || count($rows) === 0) {
                            $rows = array_fill(0, 10, ['buch_no' => '', 'size_inch' => '', 'weight' => '']);
                        }
                        while (count($rows) < 10) {
                            $rows[] = ['buch_no' => '', 'size_inch' => '', 'weight' => ''];
                        }
                        $rows = array_values($rows);
                    @endphp

                    <div class="table-responsive vacuum-buch-grid-wrap">
                        <table class="table table-bordered vacuum-buch-grid mb-0" id="vacuumBuchGrid">
                            <thead>
                                <tr>
                                    <th class="sr-col">Sr. No</th>
                                    <th>Buch No *</th>
                                    <th>Size (Inch)</th>
                                    <th>Weight</th>
                                    <th class="action-col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rows as $index => $row)
                                    <tr class="buch-row">
                                        <td class="sr-no">{{ $index + 1 }}</td>
                                        <td>
                                            <input type="text"
                                                   name="rows[{{ $index }}][buch_no]"
                                                   class="form-control buch-input"
                                                   value="{{ $row['buch_no'] ?? '' }}"
                                                   placeholder="Buch No">
                                        </td>
                                        <td>
                                            <input type="number"
                                                   step="0.01"
                                                   min="0"
                                                   name="rows[{{ $index }}][size_inch]"
                                                   class="form-control row-watch"
                                                   value="{{ $row['size_inch'] ?? '' }}"
                                                   placeholder="0.00">
                                        </td>
                                        <td>
                                            <input type="number"
                                                   step="0.001"
                                                   min="0"
                                                   name="rows[{{ $index }}][weight]"
                                                   class="form-control row-watch"
                                                   value="{{ $row['weight'] ?? '' }}"
                                                   placeholder="0.000">
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-danger remove-row">Remove</button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="card-footer text-end">
                <button type="submit" class="btn btn-success">{{ isset($data) ? 'Update' : 'Save' }}</button>
                <a href="{{ route('company.vacuum-buchs.index', $company->slug) }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('styles')
<style>
    .vacuum-buch-grid-wrap {
        max-height: calc(100vh - 360px);
        overflow: auto;
        border: 1px solid #343852;
    }

    .vacuum-buch-grid {
        min-width: 820px;
    }

    .vacuum-buch-grid thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #25263a;
        color: #fff;
    }

    .vacuum-buch-grid th,
    .vacuum-buch-grid td {
        vertical-align: middle;
        padding: 10px;
    }

    .vacuum-buch-grid .sr-col {
        width: 90px;
    }

    .vacuum-buch-grid .action-col {
        width: 120px;
    }
</style>
@endpush

@push('scripts')
@unless(isset($data))
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tbody = document.querySelector('#vacuumBuchGrid tbody');
    if (!tbody) return;

    function rowTemplate(index) {
        return `
            <tr class="buch-row">
                <td class="sr-no">${index + 1}</td>
                <td>
                    <input type="text" name="rows[${index}][buch_no]" class="form-control buch-input" placeholder="Buch No">
                </td>
                <td>
                    <input type="number" step="0.01" min="0" name="rows[${index}][size_inch]" class="form-control row-watch" placeholder="0.00">
                </td>
                <td>
                    <input type="number" step="0.001" min="0" name="rows[${index}][weight]" class="form-control row-watch" placeholder="0.000">
                </td>
                <td>
                    <button type="button" class="btn btn-sm btn-danger remove-row">Remove</button>
                </td>
            </tr>
        `;
    }

    function reindexRows() {
        tbody.querySelectorAll('.buch-row').forEach((row, index) => {
            row.querySelector('.sr-no').textContent = index + 1;
            row.querySelectorAll('input').forEach((input) => {
                input.name = input.name.replace(/rows\[\d+\]/, `rows[${index}]`);
            });
        });
    }

    function rowHasValue(row) {
        return Array.from(row.querySelectorAll('input')).some((input) => input.value.trim() !== '');
    }

    function ensureNextRow() {
        const rows = tbody.querySelectorAll('.buch-row');
        const lastRow = rows[rows.length - 1];

        if (lastRow && rowHasValue(lastRow)) {
            tbody.insertAdjacentHTML('beforeend', rowTemplate(rows.length));
        }
    }

    tbody.addEventListener('input', function (event) {
        if (event.target.matches('.buch-input, .row-watch')) {
            ensureNextRow();
        }
    });

    tbody.addEventListener('click', function (event) {
        if (!event.target.matches('.remove-row')) {
            return;
        }

        const rows = tbody.querySelectorAll('.buch-row');
        if (rows.length <= 1) {
            event.target.closest('tr').querySelectorAll('input').forEach((input) => input.value = '');
            return;
        }

        event.target.closest('tr').remove();
        reindexRows();
        ensureNextRow();
    });
});
</script>
@endunless
@endpush
