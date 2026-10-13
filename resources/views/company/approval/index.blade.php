@extends('company_layout.admin')

@section('content')
<style>
    #approvalTable {
        width: 100% !important;
        min-width: 1650px;
    }

    #approvalTable th,
    #approvalTable td {
        white-space: nowrap;
        vertical-align: middle;
    }

    #approvalTable td:last-child {
        white-space: normal;
    }

    #approvalTable .approval-item-names {
        display: inline-block;
        max-width: 560px;
        overflow: hidden;
        text-overflow: ellipsis;
        vertical-align: middle;
        white-space: nowrap;
    }

    #approvalTable .approval-item-more {
        color: #9fb5ff;
        font-weight: 700;
        margin-left: 6px;
    }

    .approval-list-grid-wrap {
        overflow-x: auto;
        overflow-y: hidden;
        border: 0;
        border-radius: 0;
        scrollbar-width: thin;
        scrollbar-color: transparent transparent;
    }

    .approval-list-grid-wrap:hover {
        scrollbar-color: rgba(125, 145, 255, 0.7) rgba(255, 255, 255, 0.08);
    }

    .approval-list-grid-wrap::-webkit-scrollbar {
        width: 10px;
        height: 10px;
    }

    .approval-list-grid-wrap::-webkit-scrollbar-track {
        background: transparent;
    }

    .approval-list-grid-wrap::-webkit-scrollbar-thumb {
        background: transparent;
        border-radius: 10px;
    }

    .approval-list-grid-wrap:hover::-webkit-scrollbar-thumb {
        background: rgba(125, 145, 255, 0.7);
    }

    .approval-list-grid-wrap:hover::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.08);
    }

    .approval-summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 14px;
        margin-bottom: 18px;
    }

    .approval-summary-box {
        border: 1px solid rgba(255, 255, 255, 0.12);
        background: rgba(255, 255, 255, 0.03);
        padding: 12px 14px;
    }

    .approval-summary-box span {
        display: block;
        color: #b8b8c7;
        font-size: 13px;
        margin-bottom: 6px;
    }

    .approval-summary-box strong {
        color: #fff;
        font-size: 24px;
        line-height: 1;
    }

    @media (max-width: 767px) {
        .approval-summary-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="content-wrapper">

    <div class="card">

        {{-- HEADER --}}
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title">Approval List</h4>
            <a href="{{ route('company.approval.create',$company->slug) }}"
                class="btn btn-primary ">
                Create Approval
            </a>
        </div>

        {{-- FILTER --}}
        <div class="card-body border-bottom">
            <div class="row">

                <div class="col-md-2">
                    <label>From Date</label>
                    <input type="date" id="from_date" class="form-control">
                </div>

                <div class="col-md-2">
                    <label>To Date</label>
                    <input type="date" id="to_date" class="form-control">
                </div>

                <div class="col-md-3">
                    <label>Customer</label>
                    <select id="customer_id" class="form-select">
                        <option value="">All Customers</option>
                        @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" data-pdf-customer-id="{{ \Illuminate\Support\Facades\Crypt::encryptString((string) $customer->id) }}">
                            {{ $customer->name }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label>View Type</label>
                    <select id="view_type" class="form-select">
                        <option value="voucher" selected>Voucher Wise</option>
                        <option value="item">Item Wise</option>
                    </select>
                </div>

                <div class="col-md-3 gap-2 d-flex align-items-end">
                    <button class="btn btn-primary" id="filterBtn">
                        Show
                    </button>
                    <button class="btn btn-secondary" id="clearBtn">
                        Clear
                    </button>
                    <a class="btn btn-danger" id="exportPdfBtn" target="_blank">PDF</a>
                </div>

            </div>
        </div>

        {{-- TABLE --}}
        <div class="card-body">
            <div class="approval-summary-grid">
                <div class="approval-summary-box">
                    <span>Total Qty</span>
                    <strong id="approvalSummaryQty">0</strong>
                </div>
                <div class="approval-summary-box">
                    <span>Total Gross Wt</span>
                    <strong id="approvalSummaryGross">0.000</strong>
                </div>
                <div class="approval-summary-box">
                    <span>Total Other Wt</span>
                    <strong id="approvalSummaryOther">0.000</strong>
                </div>
                <div class="approval-summary-box">
                    <span>Total Net Wt</span>
                    <strong id="approvalSummaryNet">0.000</strong>
                </div>
            </div>

            <div class="table-responsive approval-list-grid-wrap">
                <table class="table table-bordered" id="approvalTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Approval No</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Item Name</th>
                            <th>QR Code</th>
                            <th>Qty</th>
                            <th>Gross Wt</th>
                            <th>Other Wt</th>
                            <th>Net Wt</th>
                            <th>Fine Wt</th>
                            <th>Metal Amt</th>
                            <th>Labour Amt</th>
                            <th>Other Amt</th>
                            <th>Total Amt</th>
                            <th>Created By</th>
                            <th>Modified At</th>
                            <th>Modified Count</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                </table>
            </div>

        </div>

    </div>

</div>

@endsection
@push('scripts')
<script>
    const now = new Date();
    const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
    $('#from_date').val(today);
    $('#to_date').val(today);
    if ($.fn.select2) {
        $('#customer_id').select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: 'All Customers',
            allowClear: true
        });
    }

    function escapeHtml(value) {
        return $('<div>').text(value ?? '').html();
    }

    function updateApprovalSummary(summary) {
        const totals = summary || {};
        $('#approvalSummaryQty').text(Number(totals.total_qty || 0).toLocaleString('en-IN'));
        $('#approvalSummaryGross').text(Number(totals.total_gross_wt || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 3,
            maximumFractionDigits: 3
        }));
        $('#approvalSummaryNet').text(Number(totals.total_net_wt || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 3,
            maximumFractionDigits: 3
        }));
        $('#approvalSummaryOther').text(Number(totals.total_other_wt || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 3, maximumFractionDigits: 3
        }));
    }

    let table = $('#approvalTable').DataTable({
        processing: true,
        serverSide: true,
        searching: true,
        scrollX: true,
        autoWidth: false,
        scrollCollapse: true,

        ajax: {
            url: "{{ route('company.approval.index', $company->slug) }}",
            data: function(d) {
                d.customer_id = $('#customer_id').val();
                d.from_date = $('#from_date').val();
                d.to_date = $('#to_date').val();
                d.view_type = $('#view_type').val();
            }
        },

        columns: [{
                data: 'DT_RowIndex',
                orderable: false,
                searchable: false
            },
            {
                data: 'approval_no'
            },
            {
                data: 'approval_date'
            },
            {
                data: 'customer_name'
            },
            {
                data: 'item_names',
                orderable: false,
                searchable: false,
                render: function(data, type) {
                    if (type !== 'display') {
                        return data;
                    }

                    const fullText = String(data || '-');
                    if (fullText === '-') {
                        return '-';
                    }

                    const names = fullText.split(',').map(name => name.trim()).filter(Boolean);
                    const visibleNames = names.slice(0, 4).join(', ');
                    const moreCount = Math.max(names.length - 4, 0);
                    const moreText = moreCount > 0 ? `<span class="approval-item-more">+${moreCount} more</span>` : '';

                    return `<span class="approval-item-names" title="${escapeHtml(fullText)}">${escapeHtml(visibleNames)}${moreText}</span>`;
                }
            },
            {
                data: 'qr_code'
            },
            {
                data: 'total_qty'
            },
            {
                data: 'total_gross_weight'
            },
            {
                data: 'total_other_weight'
            },
            {
                data: 'total_net_weight'
            },
            {
                data: 'total_fine_weight'
            },
            {
                data: 'total_metal_amount'
            },
            {
                data: 'total_labour_amount'
            },
            {
                data: 'total_other_amount'
            },
            {
                data: 'total_amount'
            },
            {
                data: 'creator_name',
                orderable: false,
                searchable: false
            },
            {
                data: 'modified_at',
                orderable: false,
                searchable: false
            },
            {
                data: 'modified_count',
                orderable: false,
                searchable: false
            },
            {
                data: 'status',
                orderable: false,
                searchable: false
            },
            {
                data: 'action',
                orderable: false,
                searchable: false
            }
        ],
        order: [
            [2, 'desc']
        ],
        columnDefs: [{
            targets: '_all',
            className: 'text-nowrap'
        }, {
            targets: [19],
            className: ''
        }]
    });

    table.on('xhr.dt', function(e, settings, json) {
        updateApprovalSummary(json && json.summary);
    });

    $('#filterBtn').click(function() {
        updateApprovalPdfLink();
        table.draw();
    });

    $('#clearBtn').click(function() {
        $('#customer_id').val('').trigger('change.select2');
        $('#from_date').val(today);
        $('#to_date').val(today);
        $('#view_type').val('voucher');
        updateApprovalPdfLink();
        table.draw();
    });

    function updateApprovalPdfLink() {
        const base = "{{ route('company.approval.export.pdf', $company->slug) }}";
        const selectedCustomer = $('#customer_id option:selected');
        const pdfCustomerId = selectedCustomer.val() ? (selectedCustomer.data('pdf-customer-id') || '') : '';
        const params = new URLSearchParams({
            customer_id: pdfCustomerId,
            from_date: $('#from_date').val() || '',
            to_date: $('#to_date').val() || '',
            view_type: $('#view_type').val() || 'voucher',
        });
        $('#exportPdfBtn').attr('href', `${base}?${params.toString()}`);
    }

    $('#customer_id, #from_date, #to_date, #view_type').on('change', updateApprovalPdfLink);
    $('#view_type').on('change', function() {
        table.draw();
    });

    updateApprovalPdfLink();
    $('#customer_id').val('').trigger('change.select2');
</script>
@endpush
