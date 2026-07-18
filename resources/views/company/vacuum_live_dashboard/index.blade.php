@extends('company_layout.admin')

@php
    $stageBadge = function (int $count, int $total): string {
        $class = 'badge-pending';
        $label = $count . '/' . $total;
        if ($total > 0 && $count >= $total) {
            $class = 'badge-done';
        } elseif ($count > 0) {
            $class = 'badge-partial';
        }
        return '<span class="stage-badge ' . $class . '">' . e($label) . '</span>';
    };
@endphp

@section('content')
<div class="content-wrapper">
    <div class="card vacuum-live-dashboard">
        <div class="card-header">
            <h4 class="card-title mb-0">Vacuum Live Dashboard</h4>
        </div>
        <div class="card-body">
            <form method="GET" class="filter-box mb-3">
                <div class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label>Date</label>
                        <input type="date" name="date" class="form-control" value="{{ $date }}">
                    </div>
                    <div class="col-md-3">
                        <label>Worker</label>
                        <select name="worker_id" class="form-select">
                            <option value="">All Workers</option>
                            @foreach($workers as $worker)
                                <option value="{{ $worker->id }}" @selected((string) $workerId === (string) $worker->id)>{{ $worker->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Process</label>
                        <select name="process_id" class="form-select">
                            <option value="">All Process</option>
                            @foreach($processes as $process)
                                <option value="{{ $process->id }}" @selected((string) $processId === (string) $process->id)>{{ $process->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 voucher-search-col">
                        <label>Voucher No</label>
                        <input type="text" name="voucher_no" id="voucher_no" class="form-control" value="{{ $voucherNo }}" placeholder="VV26-" autocomplete="off">
                        <div id="voucher_no_suggestions" class="voucher-suggestions d-none"></div>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-primary w-100">Filter</button>
                        <a href="{{ route('company.vacuum-live-dashboard.index', $company->slug) }}" class="btn btn-secondary w-100">Reset</a>
                    </div>
                </div>
            </form>

            <div class="summary-grid mb-3">
                <div class="summary-card"><span>Today Vouchers</span><strong>{{ $summary['vouchers'] }}</strong></div>
                <div class="summary-card"><span>Total Pcs</span><strong>{{ $summary['total_pcs'] }}</strong></div>
                <div class="summary-card"><span>In Bhati Pcs</span><strong>{{ $summary['in_bhati_pcs'] }}</strong></div>
                <div class="summary-card"><span>Pending Metal Issue</span><strong>{{ $summary['pending_metal_issue'] }}</strong></div>
                <div class="summary-card"><span>Pending Casting Receive</span><strong>{{ $summary['pending_casting_receive'] }}</strong></div>
                <div class="summary-card"><span>Pending Tree Issue</span><strong>{{ $summary['pending_tree_issue'] }}</strong></div>
                <div class="summary-card"><span>Pending Tree Receive</span><strong>{{ $summary['pending_tree_receive'] }}</strong></div>
                <div class="summary-card"><span>Pending Sorting</span><strong>{{ $summary['pending_sorting'] }}</strong></div>
                <div class="summary-card"><span>Completed Vouchers</span><strong>{{ $summary['completed_vouchers'] }}</strong></div>
            </div>

            <div class="section-title">Voucher Stage Board</div>
            <div class="table-responsive live-table-wrap mb-4">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Voucher No</th>
                            <th>Created Time</th>
                            <th>Worker</th>
                            <th>Process</th>
                            <th>Total Pcs</th>
                            <th>In Bhati</th>
                            <th>Metal Issue</th>
                            <th>Casting Receive</th>
                            <th>Tree Issue</th>
                            <th>Tree Receive</th>
                            <th>Sorting</th>
                            <th>Current Stage</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $row['voucher_no'] }}</td>
                                <td>{{ $row['created_time'] }}</td>
                                <td>{{ $row['worker'] }}</td>
                                <td>{{ $row['process'] }}</td>
                                <td>{{ $row['total_pcs'] }}</td>
                                <td>{!! $stageBadge($row['counts']['heating'], $row['total_pcs']) !!}</td>
                                <td>{!! $stageBadge($row['counts']['metal_issue'], $row['total_pcs']) !!}</td>
                                <td>{!! $stageBadge($row['counts']['casting_receive'], $row['total_pcs']) !!}</td>
                                <td>{!! $stageBadge($row['counts']['tree_issue'], $row['total_pcs']) !!}</td>
                                <td>{!! $stageBadge($row['counts']['tree_receive'], $row['total_pcs']) !!}</td>
                                <td>{!! $stageBadge($row['counts']['sorting'], $row['total_pcs']) !!}</td>
                                <td><span class="stage-current">{{ $row['current_stage'] }}</span></td>
                                <td class="text-nowrap">
                                    <a href="{{ $row['voucher_url'] }}" class="btn btn-sm btn-primary">View</a>
                                    <a href="{{ $row['history_url'] }}" class="btn btn-sm btn-info">History</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="text-center">No vouchers found for selected date.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="section-title">Live In Bhati</div>
            <div class="table-responsive live-table-wrap">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Voucher No</th>
                            <th>B. No</th>
                            <th>Worker</th>
                            <th>Checked Time</th>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($inBhatiRows as $row)
                            <tr>
                                <td>{{ $row->voucher_no }}</td>
                                <td>{{ $row->buch_no ?: '-' }}</td>
                                <td>{{ $row->worker_name ?: '-' }}</td>
                                <td>{{ $row->checked_time_view }}</td>
                                <td>{{ $row->duration }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center">No buch currently in bhati for selected date.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .vacuum-live-dashboard .filter-box,
    .vacuum-live-dashboard .summary-card {
        border: 1px solid #343852;
        background: #282a3f;
        padding: 12px;
    }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 12px;
    }

    .summary-card span {
        display: block;
        color: #c6c8dc;
        font-size: 12px;
        margin-bottom: 4px;
    }

    .summary-card strong {
        color: #fff;
        font-size: 18px;
    }

    .section-title {
        color: #fff;
        font-weight: 600;
        margin: 14px 0 8px;
    }

    .live-table-wrap {
        max-height: 430px;
        overflow: auto;
        border: 1px solid #343852;
    }

    .vacuum-live-dashboard table {
        margin-bottom: 0;
    }

    .vacuum-live-dashboard th,
    .vacuum-live-dashboard td {
        white-space: nowrap;
        vertical-align: middle;
    }

    .voucher-search-col {
        position: relative;
    }

    .voucher-suggestions {
        position: absolute;
        left: 12px;
        right: 12px;
        top: 100%;
        z-index: 1050;
        max-height: 250px;
        overflow-y: auto;
        background: #22243a;
        border: 1px solid #3f4568;
        border-radius: 4px;
        box-shadow: 0 8px 18px rgba(0, 0, 0, 0.35);
    }

    .voucher-suggestion-item {
        display: block;
        width: 100%;
        padding: 9px 12px;
        border: 0;
        border-bottom: 1px solid #343852;
        background: transparent;
        color: #fff;
        text-align: left;
        cursor: pointer;
    }

    .voucher-suggestion-item:last-child {
        border-bottom: 0;
    }

    .voucher-suggestion-item:hover,
    .voucher-suggestion-item.active {
        background: #303b66;
    }

    .stage-badge {
        display: inline-block;
        min-width: 52px;
        padding: 4px 7px;
        border-radius: 4px;
        text-align: center;
        color: #fff;
        font-weight: 600;
    }

    .badge-done { background: #00b76f; }
    .badge-partial { background: #f0a500; color: #111; }
    .badge-pending { background: #6c7285; }
    .stage-current {
        display: inline-block;
        padding: 4px 8px;
        background: #303b66;
        border-radius: 4px;
        color: #fff;
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const voucherOptions = @json($voucherOptions);
        const form = document.querySelector('.vacuum-live-dashboard .filter-box');
        const input = document.getElementById('voucher_no');
        const suggestions = document.getElementById('voucher_no_suggestions');
        let visibleOptions = [];
        let activeIndex = -1;

        if (!form || !input || !suggestions) {
            return;
        }

        function hideSuggestions() {
            suggestions.classList.add('d-none');
            suggestions.innerHTML = '';
            activeIndex = -1;
        }

        function setActive(index) {
            const items = suggestions.querySelectorAll('.voucher-suggestion-item');
            items.forEach(item => item.classList.remove('active'));
            if (!items.length) {
                activeIndex = -1;
                return;
            }

            activeIndex = Math.max(0, Math.min(index, items.length - 1));
            items[activeIndex].classList.add('active');
            items[activeIndex].scrollIntoView({ block: 'nearest' });
        }

        function renderSuggestions() {
            const keyword = input.value.trim().toLowerCase();
            visibleOptions = voucherOptions.filter(option => {
                return !keyword || String(option.voucher_no).toLowerCase().includes(keyword);
            }).slice(0, 20);

            if (!visibleOptions.length) {
                hideSuggestions();
                return;
            }

            suggestions.innerHTML = visibleOptions.map((option, index) => {
                return `<button type="button" class="voucher-suggestion-item" data-index="${index}">${option.label}</button>`;
            }).join('');
            suggestions.classList.remove('d-none');
            activeIndex = -1;
        }

        function applyVoucher(index) {
            const option = visibleOptions[index];
            if (option) {
                input.value = option.voucher_no;
            }
            hideSuggestions();
            form.submit();
        }

        input.addEventListener('focus', renderSuggestions);
        input.addEventListener('input', renderSuggestions);

        input.addEventListener('keydown', function (event) {
            const isOpen = !suggestions.classList.contains('d-none');

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                if (!isOpen) {
                    renderSuggestions();
                }
                setActive(activeIndex + 1);
                return;
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                setActive(activeIndex <= 0 ? visibleOptions.length - 1 : activeIndex - 1);
                return;
            }

            if (event.key === 'Enter') {
                event.preventDefault();
                if (isOpen && activeIndex >= 0) {
                    applyVoucher(activeIndex);
                    return;
                }
                hideSuggestions();
                form.submit();
                return;
            }

            if (event.key === 'Escape') {
                hideSuggestions();
            }
        });

        suggestions.addEventListener('mousedown', function (event) {
            const item = event.target.closest('.voucher-suggestion-item');
            if (!item) {
                return;
            }
            event.preventDefault();
            applyVoucher(Number(item.dataset.index));
        });

        document.addEventListener('mousedown', function (event) {
            if (!event.target.closest('.voucher-search-col')) {
                hideSuggestions();
            }
        });
    });
</script>
@endpush
