@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">

<div class="card">
    <div class="card-header">
        <h4 class="card-title">Approval List (Return)</h4>
    </div>

    <div class="card-body">

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                @foreach($approvals as $a)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $a->customer->name ?? '-' }}</td>
                    <td>{{ $a->approval_date }}</td>
                    <td>
                        <a href="{{ route('company.approval.return.items', [$company->slug, $a->id]) }}"
                           class="btn btn-primary">
                           Select
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

    </div>
</div>

</div>
@endsection