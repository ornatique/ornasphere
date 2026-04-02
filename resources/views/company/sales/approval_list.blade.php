@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">

<div class="card">

    <div class="card-header">
        <h4 class="card-title">Approval List (For Sale)</h4>
    </div>

    <div class="card-body">

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                @foreach($approvals as $key => $a)
                <tr>
                    <td>{{ $key + 1 }}</td>

                    <td>{{ $a->customer->name ?? '-' }}</td>

                    <td>{{ $a->approval_date }}</td>

                    <td>
                        @if($a->status == 'partial')
                            <span class="badge bg-info">Partial</span>
                        @elseif($a->status == 'closed')
                            <span class="badge bg-success">Closed</span>
                        @else
                            <span class="badge bg-warning">Pending</span>
                        @endif
                    </td>

                    <td>
                        <a href="{{ route('company.approval-sales.approval.items', [$company->slug, $a->id]) }}"
                           class="btn btn-primary btn-sm">
                            Select Items
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