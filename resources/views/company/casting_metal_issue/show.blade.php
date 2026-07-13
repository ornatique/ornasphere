@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header casting-metal-header">
            <div>
                <h4 class="card-title mb-1">Casting Metal Issue</h4>
                <div class="casting-metal-subtitle">{{ $voucher->voucher_no }} | {{ optional($voucher->voucher_date)->format('d-m-Y') }}</div>
            </div>
            <a href="{{ route('company.casting-metal-issue.index', $company->slug) }}" class="btn btn-secondary">Back</a>
        </div>

        <form method="POST" action="{{ route('company.casting-metal-issue.update', [$company->slug, \Illuminate\Support\Facades\Crypt::encryptString((string) $voucher->id)]) }}">
            @csrf
            <div class="card-body">
                @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <div class="casting-metal-summary mb-3">
                    <div><span>Process</span><strong>{{ $voucher->process?->name ?? '-' }}</strong></div>
                    <div><span>Worker</span><strong>{{ $voucher->jobWorker?->name ?? '-' }}</strong></div>
                    <div><span>Total Pcs</span><strong>{{ (int) ($voucher->items_count ?? $voucher->items->count()) }}</strong></div>
                    <div><span>Created At</span><strong>{{ optional($voucher->created_at)->format('d-m-Y h:i A') }}</strong></div>
                </div>

                <div class="table-responsive casting-metal-scroll">
                    <table class="table table-bordered table-sm casting-metal-table">
                        <thead>
                            <tr>
                                <th style="width: 80px;">Sr. No</th>
                                <th style="width: 180px;">B. No</th>
                                <th class="text-center" style="width: 130px;">Status</th>
                                <th style="width: 160px;">Silver Weight</th>
                                <th class="text-center" style="width: 90px;">I/F</th>
                                <th style="width: 160px;">Pure Fine</th>
                                <th style="width: 120px;">%</th>
                                <th style="width: 160px;">O/M</th>
                                <th style="width: 160px;">Metal Weight</th>
                                <th style="width: 180px;">Issue Silver Wt</th>
                                <th style="width: 320px;">Remark</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($voucher->items as $item)
                            @php
                                $heatingItem = $heatingItems->get($item->id);
                                $issueItem = $issueItems->get($item->id);
                                $inBhati = (bool) ($heatingItem?->in_bhati);
                                $isIf = (bool) old('items.' . $item->id . '.is_if', $issueItem?->is_if);
                            @endphp
                            <tr data-if-row>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $item->buch_no }}</td>
                                <td class="text-center">
                                    <span class="status-box {{ $inBhati ? 'status-in' : 'status-out' }}">
                                        {{ $inBhati ? 'In Bhati' : 'Not In Bhati' }}
                                    </span>
                                </td>
                                <td>{{ number_format((float) $item->silver_wt, 3, '.', '') }}</td>
                                <td class="text-center">
                                    <input type="hidden" name="items[{{ $item->id }}][is_if]" value="0">
                                    <input type="checkbox"
                                        name="items[{{ $item->id }}][is_if]"
                                        value="1"
                                        class="if-checkbox"
                                        data-if-toggle
                                        {{ $isIf ? 'checked' : '' }}>
                                </td>
                                <td>
                                    <input type="number"
                                        name="items[{{ $item->id }}][pure_fine]"
                                        class="form-control if-input"
                                        data-pure-fine
                                        step="0.001"
                                        min="0"
                                        inputmode="decimal"
                                        value="{{ old('items.' . $item->id . '.pure_fine', $issueItem?->pure_fine) }}">
                                </td>
                                <td>
                                    <input type="number"
                                        name="items[{{ $item->id }}][if_percentage]"
                                        class="form-control if-input"
                                        data-if-percentage
                                        step="0.01"
                                        min="0.01"
                                        max="100"
                                        inputmode="decimal"
                                        value="{{ old('items.' . $item->id . '.if_percentage', $issueItem?->if_percentage) }}">
                                </td>
                                <td>
                                    <input type="number"
                                        class="form-control if-input"
                                        data-other-metal
                                        value="{{ $issueItem?->other_metal !== null ? number_format((float) $issueItem->other_metal, 3, '.', '') : '' }}"
                                        readonly>
                                </td>
                                <td>
                                    <input type="number"
                                        class="form-control if-input"
                                        data-metal-weight
                                        value="{{ $issueItem?->metal_weight !== null ? number_format((float) $issueItem->metal_weight, 3, '.', '') : '' }}"
                                        readonly>
                                </td>
                                <td>
                                    <input type="number"
                                        name="items[{{ $item->id }}][issue_silver_wt]"
                                        class="form-control issue-silver-input"
                                        step="0.001"
                                        min="0"
                                        inputmode="decimal"
                                        value="{{ old('items.' . $item->id . '.issue_silver_wt', $issueItem?->issue_silver_wt) }}">
                                </td>
                                <td>
                                    <input type="text"
                                        name="items[{{ $item->id }}][remarks]"
                                        class="form-control"
                                        maxlength="1000"
                                        value="{{ old('items.' . $item->id . '.remarks', $issueItem?->remarks) }}">
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="11" class="text-center">No Buch rows found</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-footer text-end">
                <a href="{{ route('company.casting-metal-issue.index', $company->slug) }}" class="btn btn-secondary">Back</a>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('styles')
<style>
    .casting-metal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }

    .casting-metal-subtitle {
        color: #b8b8d4;
        font-size: 13px;
    }

    .casting-metal-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(150px, 1fr));
        gap: 10px;
    }

    .casting-metal-summary > div {
        border: 1px solid rgba(255, 255, 255, 0.08);
        background: rgba(255, 255, 255, 0.035);
        padding: 10px 12px;
    }

    .casting-metal-summary span {
        display: block;
        color: #b8b8d4;
        font-size: 12px;
        margin-bottom: 3px;
    }

    .casting-metal-summary strong {
        color: #fff;
        font-size: 14px;
    }

    .casting-metal-scroll {
        max-height: calc(100vh - 430px);
        overflow-y: auto;
        border: 1px solid rgba(255, 255, 255, 0.08);
    }

    .casting-metal-table {
        margin-bottom: 0;
        table-layout: fixed;
        width: 100%;
    }

    .casting-metal-table thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #25263a;
    }

    .casting-metal-table th,
    .casting-metal-table td {
        padding: 0.65rem 0.8rem;
        vertical-align: middle;
    }

    .status-box {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 92px;
        padding: 0.28rem 0.55rem;
        color: #fff;
        font-size: 12px;
        font-weight: 700;
    }

    .status-in {
        background: #16a34a;
    }

    .status-out {
        background: #dc2626;
    }

    .issue-silver-input {
        min-width: 120px;
    }

    .if-checkbox {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }

    .if-input {
        min-width: 110px;
    }

    .if-field-hidden {
        visibility: hidden;
    }

    @media (max-width: 991px) {
        .casting-metal-summary {
            grid-template-columns: repeat(2, minmax(150px, 1fr));
        }
    }

    @media (max-width: 575px) {
        .casting-metal-summary {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    const metalToNum = (value) => {
        const parsed = parseFloat(value);
        return Number.isFinite(parsed) ? parsed : 0;
    };

    const metalNfix = (value) => {
        const number = metalToNum(value);
        return (Math.abs(number) < 0.0005 ? 0 : number).toFixed(3);
    };

    function recalcIfRow(row) {
        const enabled = row.querySelector('[data-if-toggle]')?.checked;
        const pureFine = row.querySelector('[data-pure-fine]');
        const percentage = row.querySelector('[data-if-percentage]');
        const otherMetal = row.querySelector('[data-other-metal]');
        const metalWeight = row.querySelector('[data-metal-weight]');
        const ifInputs = row.querySelectorAll('[data-pure-fine], [data-if-percentage], [data-other-metal], [data-metal-weight]');

        ifInputs.forEach((input) => {
            input.classList.toggle('if-field-hidden', !enabled);
            if (input !== otherMetal) {
                input.disabled = !enabled;
            }
        });

        if (!enabled) {
            if (otherMetal) {
                otherMetal.value = '';
            }
            if (metalWeight) {
                metalWeight.value = '';
            }
            return;
        }

        const pureFineValue = metalToNum(pureFine?.value);
        const percentageValue = metalToNum(percentage?.value);
        const calculatedMetalWeight = pureFineValue > 0 && percentageValue > 0
            ? pureFineValue / (percentageValue / 100)
            : null;

        if (metalWeight) {
            metalWeight.value = calculatedMetalWeight !== null ? metalNfix(calculatedMetalWeight) : '';
        }

        if (otherMetal) {
            otherMetal.value = calculatedMetalWeight !== null
                ? metalNfix(calculatedMetalWeight - pureFineValue)
                : '';
        }
    }

    document.querySelectorAll('[data-if-row]').forEach(recalcIfRow);

    document.addEventListener('input', function (event) {
        if (event.target.matches('[data-pure-fine], [data-if-percentage]')) {
            recalcIfRow(event.target.closest('[data-if-row]'));
        }
    });

    document.addEventListener('change', function (event) {
        if (event.target.matches('[data-if-toggle]')) {
            recalcIfRow(event.target.closest('[data-if-row]'));
        }
    });
</script>
@endpush
