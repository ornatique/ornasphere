@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">

    <div class="card">

        <div class="card-header">
            <h4 class="card-title">Approval Return</h4>
        </div>

        <div class="card-body">

            <form id="returnForm">
                @csrf

                <input type="hidden" name="approval_id" value="{{ $approval->id }}">

                <div class="mb-3">
                    <label>Voucher Remarks</label>
                    <textarea name="voucher_remarks" class="form-control" rows="2" placeholder="Enter remarks for this return"></textarea>
                </div>

                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll"></th>
                            <th>HUID</th>
                            <th>QR</th>
                            <th>Gross</th>
                            <th>Net</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                        @foreach($approval->items as $item)

                        @if($item->status == 'pending')

                        <tr>
                            <td>
                                <input type="checkbox"
                                    name="items[]"
                                    value="{{ $item->id }}"
                                    class="itemCheckbox">
                            </td>

                            <td>{{ optional($item->itemSet)->HUID }}</td>
                            <td>{{ optional($item->itemSet)->qr_code }}</td>

                            <td>{{ $item->gross_weight }}</td>
                            <td>{{ $item->net_weight }}</td>

                            <td>
                                <span class="badge bg-warning">Approval</span>
                            </td>
                        </tr>

                        @endif

                        @endforeach

                    </tbody>
                </table>

                <button type="button" id="saveReturn"
                    class="btn btn-danger">
                    Return Selected
                </button>

            </form>

        </div>

    </div>

</div>
@endsection


@push('scripts')
<script>
    $('#selectAll').click(function() {
        $('.itemCheckbox').prop('checked', this.checked);
    });

    $('#saveReturn').click(function() {

        if ($('.itemCheckbox:checked').length == 0) {
            alert('Select items');
            return;
        }

        $.post("{{ route('company.approval.return.store', $company->slug) }}",
            $('#returnForm').serialize(),
            function() {

                alert('Return Done');
                // window.location.href = "{{ route('company.approval.index', $company->slug) }}";

            });
    });
</script>
@endpush
