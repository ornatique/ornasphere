@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        
        {{-- HEADER --}}
        <div class="card-header  text-white">
            <h4 class="card-title">Edit Role</h4>
        </div>

        <form method="POST"
              action="{{ route('company.roles.update', [$company->slug, encrypt($role->id)]) }}">
            @csrf
            @method('PUT')

            <div class="card-body">

                {{-- Role Name --}}
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Role Name</label>
                        <input type="text"
                               name="name"
                               value="{{ old('name', $role->name) }}"
                               class="form-control"
                               required>
                    </div>
                </div>

                {{-- Global Select All --}}
                <div class="mb-3">
                    <label>
                        <input type="checkbox" id="select-all">
                        <strong>Select All Permissions</strong>
                    </label>
                </div>

                {{-- Grouped Permissions --}}
                <div class="row">

                    @foreach($permissions as $module => $perms)
                    <div class="col-md-3 mb-4">
                        <div class="card border-primary">

                            {{-- Group Header --}}
                            <div class="card-header bg-dark text-white py-2">
                                <label class="mb-0">
                                    <input type="checkbox"
                                           class="group-select"
                                           data-group="{{ $module }}">
                                    <strong class="text-uppercase">
                                        {{ $module }}
                                    </strong>
                                </label>
                            </div>

                            {{-- Group Body --}}
                            <div class="card-body p-2">
                                @foreach($perms as $permission)

                                @php
                                    $parts = explode('-', $permission->name);
                                    $action = $parts[1] ?? '';
                                @endphp

                                <div class="form-check">
                                    <input type="checkbox"
                                           class="form-check-input permission-checkbox"
                                           data-group="{{ $module }}"
                                           name="permissions[]"
                                           value="{{ $permission->name }}" style="margin-left: 0 !important;"
                                           {{ in_array($permission->name, $rolePermissions) ? 'checked' : '' }} >

                                    <label class="form-check-label">
                                        {{ ucfirst($action) }}
                                    </label>
                                </div>

                                @endforeach
                            </div>

                        </div>
                    </div>
                    @endforeach

                </div>

            </div>

            {{-- FOOTER --}}
            <div class="card-footer text-end">
                <a href="{{ route('company.roles.index', $company->slug) }}"
                   class="btn btn-secondary">
                    Back
                </a>

                <button class="btn btn-success">
                    Update
                </button>
            </div>

        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    const selectAll = document.getElementById('select-all');
    const permissions = document.querySelectorAll('.permission-checkbox');
    const groupSelects = document.querySelectorAll('.group-select');

    // Global select all
    selectAll.addEventListener('change', function () {
        permissions.forEach(cb => cb.checked = this.checked);
        groupSelects.forEach(gs => gs.checked = this.checked);
    });

    // Group select
    groupSelects.forEach(group => {
        group.addEventListener('change', function () {
            const groupName = this.dataset.group;

            document.querySelectorAll(
                `.permission-checkbox[data-group="${groupName}"]`
            ).forEach(cb => cb.checked = this.checked);

            syncAll();
        });
    });

    // Auto sync
    permissions.forEach(cb => cb.addEventListener('change', syncAll));

    function syncAll() {
        selectAll.checked = [...permissions].every(cb => cb.checked);

        groupSelects.forEach(group => {
            const groupName = group.dataset.group;
            const groupPerms = document.querySelectorAll(
                `.permission-checkbox[data-group="${groupName}"]`
            );
            group.checked = [...groupPerms].every(cb => cb.checked);
        });
    }

    syncAll(); // initial sync
});
</script>
@endpush
