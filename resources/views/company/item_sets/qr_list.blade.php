@extends('company_layout.admin')

@section('content')

<div class="content-wrapper">

    <div class="card">

        <div class="card-header d-flex justify-content-between">
            <h4>QR Code List</h4>

            <button class="btn btn-primary" onclick="printSelected()">
                Print Selected
            </button>
        </div>

        <div class="card-body">

            <table class="table table-bordered">

                <thead>
                    <tr>

                        <th width="40">
                            <input type="checkbox" id="selectAll">
                        </th>

                        <th>ID</th>
                        <th>Item</th>
                        <th>Serial</th>
                        <th>QR</th>

                    </tr>
                </thead>

                <tbody>

                    @foreach($itemSets as $set)

                    <tr>

                        <td>
                            <input type="checkbox"
                                class="qrCheckbox"
                                value="{{ $set->id }}">
                        </td>

                        <td>{{ $set->id }}</td>

                        <td>{{ $set->item->item_name }}</td>

                        <td>{{ $set->qr_code }}</td>

                        <td>
                            <img src="{{ route('company.item_sets.qrImage', [$company->slug, $set->id]) }}"
                                 width="80">
                        </td>

                    </tr>

                    @endforeach

                </tbody>

            </table>

        </div>

    </div>

</div>

@endsection


@push('scripts')

<script>

$('#selectAll').on('change', function() {

    $('.qrCheckbox').prop('checked', this.checked);

});


function printSelected()
{
    let ids = [];

    $('.qrCheckbox:checked').each(function() {

        ids.push($(this).val());

    });

    if(ids.length === 0)
    {
        alert("Select QR first");
        return;
    }

    let url = "{{ route('company.item_sets.printPdf', $company->slug) }}?ids=" + ids.join(',');

    window.open(url, '_blank');
}

</script>

@endpush