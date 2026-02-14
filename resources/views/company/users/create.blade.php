@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="row">
        <div class="col-12 grid-margin">
            <div class="card">

                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Create User</h3>
                </div>

                <div class="card-body">

                    <form method="POST"
                        enctype="multipart/form-data"
                        action="{{ route('company.users.store', $company->slug) }}">
                        @csrf

                        {{-- GLOBAL ERROR --}}
                        @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                        @endif

                        <p class="card-description">Basic Information</p>

                        <div class="row">

                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Role</label>
                                    <div class="col-sm-9">
                                        <select name="role" id="roleSelect"
                                            class="form-control @error('role') is-invalid @enderror" required>
                                            <option value="">Select Role</option>
                                            @foreach($roles as $role)
                                            <option value="{{ $role->name }}"
                                                {{ old('role') == $role->name ? 'selected' : '' }}>
                                                {{ ucfirst($role->name) }}
                                            </option>
                                            @endforeach
                                        </select>
                                        @error('role')
                                        <div class="text-danger small">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Name</label>
                                    <div class="col-sm-9">
                                        <input type="text"
                                            name="name"
                                            value="{{ old('name') }}"
                                            class="form-control @error('name') is-invalid @enderror"
                                            required>
                                        @error('name')
                                        <div class="text-danger small">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="row">

                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Email</label>
                                    <div class="col-sm-9">
                                        <input type="email"
                                            name="email"
                                            value="{{ old('email') }}"
                                            class="form-control @error('email') is-invalid @enderror"
                                            required>
                                        @error('email')
                                        <div class="text-danger small">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Profile Image</label>
                                    <div class="col-sm-9">
                                        <input type="file"
                                            name="profile_image"
                                            class="form-control @error('profile_image') is-invalid @enderror">
                                        @error('profile_image')
                                        <div class="text-danger small">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                        </div>

                        {{-- PERSON DETAILS --}}
                        <p class="card-description">Person Details</p>

                        @php
                        $fields = [
                        'person_code','city','area','landmark','pincode',
                        'mobile_no','phone_no',
                        'contact_person1_name','contact_person1_phone',
                        'contact_person2_name','contact_person2_phone',
                        'gst_no','pan_no','aadhaar_no',
                        'hallmark_license_no','reference'
                        ];
                        @endphp

                        @foreach(array_chunk($fields, 2) as $chunk)
                        <div class="row">
                            @foreach($chunk as $field)
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">
                                        {{ ucwords(str_replace('_',' ',$field)) }}
                                    </label>
                                    <div class="col-sm-9">
                                        <input type="text"
                                            name="{{ $field }}"
                                            value="{{ old($field) }}"
                                            class="form-control @error($field) is-invalid @enderror">

                                        @error($field)
                                        <div class="text-danger small">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        @endforeach

                        {{-- ADDRESS --}}
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Address</label>
                                    <div class="col-sm-9">
                                        <textarea name="address"
                                            class="form-control @error('address') is-invalid @enderror">{{ old('address') }}</textarea>
                                        @error('address')
                                        <div class="text-danger small">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- DATES --}}
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Birth Date</label>
                                    <div class="col-sm-9">
                                        <input type="date"
                                            name="birth_date"
                                            value="{{ old('birth_date') }}"
                                            class="form-control @error('birth_date') is-invalid @enderror">
                                        @error('birth_date')
                                        <div class="text-danger small">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Anniversary</label>
                                    <div class="col-sm-9">
                                        <input type="date"
                                            name="anniversary_date"
                                            value="{{ old('anniversary_date') }}"
                                            class="form-control @error('anniversary_date') is-invalid @enderror">
                                        @error('anniversary_date')
                                        <div class="text-danger small">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>


                </div>
                <div class="card-footer text-end">
                    <a href="{{ route('company.users.index', $company->slug) }}"
                        class="btn btn-info">Back</a>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>

                </form>
            </div>
        </div>
    </div>

    {{-- Employee Limit Modal --}}
    <div class="modal fade" id="employeeLimitModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Limit Reached</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    You have reached your employee limit.
                    Please contact Super Admin to upgrade your plan.
                </div>
            </div>
        </div>
    </div>

    @endsection

    @push("scripts")
    <script>
        document.getElementById('roleSelect').addEventListener('change', function() {
            if (this.value === 'Employee') {
                fetch("{{ route('company.check.employee.limit', $company->slug) }}")
                    .then(response => response.json())
                    .then(data => {
                        if (data.limit_reached) {
                            new bootstrap.Modal(document.getElementById('employeeLimitModal')).show();
                            this.value = '';
                        }
                    });
            }
        });
    </script>
    @endpush