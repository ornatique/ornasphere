@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="row">
        <div class="col-12 grid-margin">
            <div class="card">

                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Edit User</h3>
                </div>

                <div class="card-body">

                    <form method="POST"
                          enctype="multipart/form-data"
                          action="{{ route('company.users.update', [$company->slug, Crypt::encryptString($user->id)]) }}">
                        @csrf
                        @method('PUT')

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

                            {{-- ROLE --}}
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Role</label>
                                    <div class="col-sm-9">
                                        <select name="role"
                                            class="form-control @error('role') is-invalid @enderror" required>
                                            <option value="">Select Role</option>
                                            @foreach($roles as $role)
                                            <option value="{{ $role->name }}"
                                                {{ old('role', $user->getRoleNames()->first()) == $role->name ? 'selected' : '' }}>
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

                            {{-- NAME --}}
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Name</label>
                                    <div class="col-sm-9">
                                        <input type="text"
                                            name="name"
                                            value="{{ old('name', $user->name) }}"
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

                            {{-- EMAIL (readonly recommended) --}}
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Email</label>
                                    <div class="col-sm-9">
                                        <input type="email"
                                            value="{{ $user->email }}"
                                            class="form-control"
                                            readonly>
                                    </div>
                                </div>
                            </div>

                            {{-- PROFILE IMAGE --}}
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Profile Image</label>
                                    <div class="col-sm-9">
                                        <input type="file"
                                            name="profile_image"
                                            class="form-control @error('profile_image') is-invalid @enderror">

                                        @if($user->profile_image)
                                            <img src="{{ asset('storage/'.$user->profile_image) }}"
                                                 width="60"
                                                 class="mt-2 rounded">
                                        @endif

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
                                            value="{{ old($field, $user->$field) }}"
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
                                            class="form-control @error('address') is-invalid @enderror">{{ old('address', $user->address) }}</textarea>
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
                                            value="{{ old('birth_date', $user->birth_date) }}"
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
                                            value="{{ old('anniversary_date', $user->anniversary_date) }}"
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
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>

                </form>
            </div>
        </div>
    </div>
</div>
@endsection
