@extends('layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="row">
        <div class="col-12 grid-margin">
            <div class="card company-form-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-1">Add Company</h3>
                        <p class="mb-0 text-muted">Create company account and first admin login.</p>
                    </div>
                    <a href="{{ route('superadmin.companies.index') }}" class="btn btn-info">Back</a>
                </div>

                <form method="POST" enctype="multipart/form-data" action="{{ route('superadmin.companies.store') }}">
                    @csrf

                    <div class="card-body">
                        @if ($errors->has('error'))
                            <div class="alert alert-danger">{{ $errors->first('error') }}</div>
                        @endif

                        <div class="company-form-section">
                            <div class="section-heading">
                                <h5>Company Details</h5>
                                <span>Basic company profile and access limit</span>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Company Name <span class="text-danger">*</span></label>
                                        <input type="text" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" placeholder="Enter company name">
                                        @error('name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Company Email <span class="text-danger">*</span></label>
                                        <input type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" placeholder="company@example.com">
                                        @error('email')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>User Allow <span class="text-danger">*</span></label>
                                        <input type="number" min="1" name="max_users" value="{{ old('max_users', 1) }}" class="form-control @error('max_users') is-invalid @enderror">
                                        @error('max_users')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Plan <span class="text-danger">*</span></label>
                                        <select name="plan" class="form-select @error('plan') is-invalid @enderror">
                                            <option value="">Select Plan</option>
                                            <option value="yearly" {{ old('plan') == 'yearly' ? 'selected' : '' }}>Yearly</option>
                                        </select>
                                        @error('plan')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Status</label>
                                        <select name="status" class="form-select @error('status') is-invalid @enderror">
                                            <option value="1" {{ old('status', '1') == '1' ? 'selected' : '' }}>Active</option>
                                            <option value="0" {{ old('status') == '0' ? 'selected' : '' }}>Inactive</option>
                                        </select>
                                        @error('status')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Company Logo</label>
                                        <input type="file" name="company_logo" accept="image/png,image/jpeg,image/webp" class="form-control @error('company_logo') is-invalid @enderror" id="companyLogoInput">
                                        @error('company_logo')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <small class="text-muted">JPG, PNG, WEBP. Max 2MB.</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="company-form-section">
                            <div class="section-heading">
                                <h5>Admin Login Details</h5>
                                <span>This user will receive the password setup link</span>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Admin Name</label>
                                        <input type="text" name="admin_name" value="{{ old('admin_name') }}" class="form-control @error('admin_name') is-invalid @enderror" placeholder="Default: Company Name Admin">
                                        @error('admin_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="logo-preview-box">
                                        <img id="companyLogoPreview" src="{{ asset('celestial/assets/images/logo.svg') }}?v={{ @filemtime(public_path('celestial/assets/images/logo.svg')) }}" alt="Company Logo Preview">
                                        <div>
                                            <strong>Logo Preview</strong>
                                            <p class="mb-0 text-muted">Selected logo will appear on company list.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="company-form-section">
                            <div class="section-heading">
                                <h5>Address</h5>
                                <span>Optional company location details</span>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Address 1</label>
                                        <input type="text" name="address_1" id="address_1" value="{{ old('address_1') }}" class="form-control @error('address_1') is-invalid @enderror">
                                        @error('address_1')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Address 2</label>
                                        <input type="text" name="address_2" value="{{ old('address_2') }}" class="form-control">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>City</label>
                                        <input type="text" name="city" id="city" value="{{ old('city') }}" class="form-control @error('city') is-invalid @enderror">
                                        @error('city')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>State</label>
                                        <input type="text" name="state" id="state" value="{{ old('state') }}" class="form-control @error('state') is-invalid @enderror">
                                        @error('state')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Postcode</label>
                                        <input type="text" name="postcode" id="postcode" value="{{ old('postcode') }}" class="form-control @error('postcode') is-invalid @enderror">
                                        @error('postcode')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Country</label>
                                        <select name="country" id="country" class="form-select @error('country') is-invalid @enderror">
                                            <option value="">Select Country</option>
                                            <option value="America" {{ old('country') == 'America' ? 'selected' : '' }}>America</option>
                                            <option value="India" {{ old('country', 'India') == 'India' ? 'selected' : '' }}>India</option>
                                            <option value="Italy" {{ old('country') == 'Italy' ? 'selected' : '' }}>Italy</option>
                                            <option value="Russia" {{ old('country') == 'Russia' ? 'selected' : '' }}>Russia</option>
                                            <option value="Britain" {{ old('country') == 'Britain' ? 'selected' : '' }}>Britain</option>
                                        </select>
                                        @error('country')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer d-flex justify-content-end gap-2">
                        <a href="{{ route('superadmin.companies.index') }}" class="btn btn-info">Back</a>
                        <button type="submit" class="btn btn-primary">Save Company</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .company-form-card .card-header {
        padding: 22px 26px;
    }

    .company-form-card .card-body {
        padding: 26px;
    }

    .company-form-section {
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 8px;
        padding: 22px;
        margin-bottom: 22px;
        background: rgba(255, 255, 255, 0.02);
    }

    .section-heading {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        margin-bottom: 18px;
        padding-bottom: 14px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }

    .section-heading h5 {
        margin: 0;
        font-size: 18px;
        font-weight: 700;
        color: #fff;
    }

    .section-heading span {
        color: #9aa3b2;
        font-size: 13px;
    }

    .company-form-card label {
        color: #cbd3df;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .logo-preview-box {
        min-height: 88px;
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 14px 16px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 8px;
        background: rgba(0, 0, 0, 0.08);
    }

    .logo-preview-box img {
        width: 58px;
        height: 58px;
        border-radius: 8px;
        object-fit: cover;
        background: #fff;
    }

    @media (max-width: 767px) {
        .section-heading {
            align-items: flex-start;
            flex-direction: column;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const input = document.getElementById('companyLogoInput');
        const preview = document.getElementById('companyLogoPreview');

        if (!input || !preview) {
            return;
        }

        input.addEventListener('change', function () {
            const file = this.files && this.files[0];
            if (!file) {
                return;
            }

            preview.src = URL.createObjectURL(file);
        });
    });
</script>
@endpush

@include('superadmin.auth.company.partials.address_autofill')
