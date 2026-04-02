@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">

    <div class="card">

        {{-- HEADER --}}
        <div class="card-header d-flex justify-content-between">
            <h4 class="card-title">Create Sale From Approval</h4>

            <a href="{{ route('company.approval-sales.fromApproval', $company->slug) }}"
                class="btn btn-secondary">
                Back
            </a>
        </div>

        <div class="card-body">

            {{-- CUSTOMER INFO --}}
            <div class="row mb-3">
                <div class="col-md-4">
                    <label><strong>Customer</strong></label>
                    <p>{{ $approval->customer->name ?? '-' }}</p>
                </div>

                <div class="col-md-4">
                    <label><strong>Date</strong></label>
                    <p>{{ $approval->approval_date }}</p>
                </div>
            </div>

            {{-- FORM --}}
            <form id="saleForm">

                @csrf

                <input type="hidden" name="approval_id" value="{{ $approval->id }}">
                <input type="hidden" name="customer_id" value="{{ $approval->customer_id }}">

                {{-- TABLE --}}
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="selectAll">
                            </th>
                            <th>HUID</th>
                            <th>QR</th>
                            <th>Gross</th>
                            <th>Net</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                        @foreach($approval->items as $item)

                        <tr>

                            <td>
                                @if($item->status != 'sold')
                                <input type="checkbox"
                                    name="items[]"
                                    value="{{ $item->id }}"
                                    class="itemCheckbox">
                                @endif
                            </td>

                            {{-- HUID --}}
                            <td>
                                {{ $item->itemSet->HUID ?? $item->huid ?? '-' }}
                            </td>

                            <td>
                                {{ $item->itemSet->qr_code ?? $item->qr_code ?? '-' }}
                            </td>

                            {{-- WEIGHTS --}}
                            <td>{{ number_format($item->gross_weight, 3) }}</td>
                            <td>{{ number_format($item->net_weight, 3) }}</td>

                            {{-- STATUS --}}
                            <td>
                                @if($item->status == 'sold')
                                <span class="badge bg-success">Sold</span>
                                @else
                                <span class="badge bg-warning">Pending</span>
                                @endif
                            </td>

                        </tr>

                        @endforeach

                    </tbody>

                </table>

                {{-- BUTTON --}}
                <button type="button" id="createSale"
                    class="btn btn-success mt-3 text-end">
                    Create Sale
                </button>

            </form>

        </div>

    </div>

</div>
@endsection


@push('scripts')
<script>
    // SELECT ALL
    $('#selectAll').click(function() {
        $('.itemCheckbox').prop('checked', this.checked);
    });

    // CREATE SALE
    $('#createSale').click(function() {

        let checked = $('.itemCheckbox:checked').length;

        if (checked == 0) {
            alert('Select at least one item');
            return;
        }

        $.post("{{ route('company.approval-sales.store.fromApproval', $company->slug) }}",
            $('#saleForm').serialize(),
            function(res) {

                alert('Sale Created Successfully');

                window.location.href = "{{ route('company.sales.index', $company->slug) }}";

            }
        );

    });
</script>
@endpush