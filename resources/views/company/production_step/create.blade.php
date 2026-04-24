@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <h4 class="card-title">{{ isset($data) ? 'Edit Production Step' : 'Create Production Step' }}</h4>
        </div>

        <div class="card-body">
            <form method="POST"
                action="{{ isset($data) ? route('company.production-step.update', [$company->slug, \Illuminate\Support\Facades\Crypt::encryptString((string) $data->id)]) : route('company.production-step.store', $company->slug) }}">
                @csrf

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
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Step Name *</label>
                            <input type="text" name="name" class="form-control" required value="{{ old('name', $data->name ?? '') }}">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Labour Formula</label>
                            <select name="labour_formula_id" class="form-select">
                                <option value="">Select Value</option>
                                @foreach($labourFormulas as $formula)
                                <option value="{{ $formula->id }}" {{ (string) old('labour_formula_id', $data->labour_formula_id ?? '') === (string) $formula->id ? 'selected' : '' }}>
                                    {{ $formula->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Production Cost</label>
                            <select name="production_cost_id" class="form-select">
                                <option value="">Select Value</option>
                                @foreach($productionCosts as $cost)
                                <option value="{{ $cost->id }}" {{ (string) old('production_cost_id', $data->production_cost_id ?? '') === (string) $cost->id ? 'selected' : '' }}>
                                    {{ $cost->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Status *</label>
                            <select name="status" class="form-select" required>
                                <option value="1" {{ (string) old('status', isset($data) ? (int) $data->status : 1) === '1' ? 'selected' : '' }}>Active</option>
                                <option value="0" {{ (string) old('status', isset($data) ? (int) $data->status : 1) === '0' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="form-group">
                            <label class="me-4">
                                <input type="checkbox" name="auto_create_cost" value="1" {{ old('auto_create_cost', isset($data) ? (int) $data->auto_create_cost : 0) ? 'checked' : '' }}>
                                Auto Create Cost
                            </label>

                            <label>
                                <input type="checkbox" name="receivable_loss" value="1" {{ old('receivable_loss', isset($data) ? (int) $data->receivable_loss : 0) ? 'checked' : '' }}>
                                Receivable Loss
                            </label>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="form-group">
                            <label>Remarks</label>
                            <textarea name="remarks" rows="3" class="form-control">{{ old('remarks', $data->remarks ?? '') }}</textarea>
                        </div>
                    </div>

                </div>
        </div>

        <div class="card-footer text-end">
            <button type="submit" class="btn btn-success">{{ isset($data) ? 'Update' : 'Save' }}</button>
            <a href="{{ route('company.production-step.index', $company->slug) }}" class="btn btn-secondary">Cancel</a>
        </div>
        </form>
    </div>
</div>
@endsection
