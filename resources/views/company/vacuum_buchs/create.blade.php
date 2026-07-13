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
            </div>

            <div class="card-footer text-end">
                <button type="submit" class="btn btn-success">{{ isset($data) ? 'Update' : 'Save' }}</button>
                <a href="{{ route('company.vacuum-buchs.index', $company->slug) }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
