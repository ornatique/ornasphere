@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Edit Category Person</h3>
        </div>

        <form method="POST" action="{{ route('company.category-persons.update', [$company->slug, encrypt($categoryPerson->id)]) }}">
            @csrf
            @method('PUT')

            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Category Name <span class="text-danger">*</span></label>
                            <input type="text"
                                   name="category_name"
                                   value="{{ old('category_name', $categoryPerson->category_name) }}"
                                   class="form-control @error('category_name') is-invalid @enderror"
                                   placeholder="Category name"
                                   required>
                            @error('category_name')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer text-end">
                <a href="{{ route('company.category-persons.index', $company->slug) }}" class="btn btn-info">Back</a>
                <button type="submit" class="btn btn-primary">Update</button>
            </div>
        </form>
    </div>
</div>
@endsection
