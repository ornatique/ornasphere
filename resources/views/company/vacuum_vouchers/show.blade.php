@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title">Vacuum Voucher Details</h4>
            <div>
                <a href="{{ route('company.vacuum-vouchers.pdf', [$company->slug, \Illuminate\Support\Facades\Crypt::encryptString((string) $data->id)]) }}" class="btn btn-success">PDF</a>
                <a href="{{ route('company.vacuum-vouchers.edit', [$company->slug, \Illuminate\Support\Facades\Crypt::encryptString((string) $data->id)]) }}" class="btn btn-primary">Edit</a>
                <a href="{{ route('company.vacuum-vouchers.index', $company->slug) }}" class="btn btn-info">Back</a>
            </div>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3"><strong>Voucher No:</strong> {{ $data->voucher_no }}</div>
                <div class="col-md-3"><strong>Date:</strong> {{ optional($data->voucher_date)->format('d-m-Y') }}</div>
                <div class="col-md-3"><strong>Process:</strong> {{ $data->process?->name ?? '-' }}</div>
                <div class="col-md-3"><strong>Worker:</strong> {{ $data->jobWorker?->name ?? '-' }}</div>
                <div class="col-md-3 mt-2"><strong>Formula:</strong> {{ number_format((float) $data->formula_value, 3, '.', '') }}</div>
                <div class="col-md-3 mt-2"><strong>Created By:</strong> {{ $data->createdByUser?->name ?? '-' }}</div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Sr. No</th>
                            <th>Buch No</th>
                            <th>Gross Wt</th>
                            <th>Buch Wt</th>
                            <th>Net Wt</th>
                            <th>Silver Wt</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data->items as $item)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $item->buch_no }}</td>
                            <td>{{ number_format((float) $item->gross_wt, 3, '.', '') }}</td>
                            <td>{{ number_format((float) $item->buch_wt, 3, '.', '') }}</td>
                            <td>{{ number_format((float) $item->net_wt, 3, '.', '') }}</td>
                            <td>{{ number_format((float) $item->silver_wt, 3, '.', '') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="2">Total</th>
                            <th>{{ number_format((float) $data->gross_wt_total, 3, '.', '') }}</th>
                            <th>{{ number_format((float) $data->buch_wt_total, 3, '.', '') }}</th>
                            <th>{{ number_format((float) $data->net_wt_total, 3, '.', '') }}</th>
                            <th>{{ number_format((float) $data->silver_wt_total, 3, '.', '') }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
