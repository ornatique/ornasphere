@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card card-primary">

        {{-- HEADER --}}
        <div class="card-header">
            <h3 class="card-title">Create Role</h3>
        </div>

        <form method="POST"
            action="{{ route('company.roles.store', $company->slug) }}">
            @csrf

            <div class="card-body">

                {{-- ROLE NAME --}}
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Role Name</label>
                            <input type="text"
                                name="name"
                                class="form-control"
                                required>
                        </div>
                    </div>
                </div>

                {{-- GLOBAL SELECT ALL --}}
                <div class="mb-3">
                    <label class="fw-bold">
                        <input type="checkbox" id="select-all-permissions">
                        Select All Permissions
                    </label>
                </div>

                {{-- PERMISSION GROUPS --}}
                {{-- PERMISSION GROUPS --}}
                <div class="row">

                    @foreach($permissions as $module => $perms)
                    @php
                        $moduleLabel = ucwords(str_replace(['-', '_', '.'], ' ', $module));
                    @endphp
                    <div class="col-md-4 col-sm-6 mb-4">

                        <div class="card border-primary shadow-sm">

                            {{-- HEADER --}}
                            <div class="card-header bg-dark text-white py-2">
                                <label class="mb-0">
                                    <input type="checkbox"
                                        class="group-select "
                                        data-group="{{ $module }}">
                                    <strong class="text-uppercase ms-1">
                                        {{ $moduleLabel }}
                                    </strong>
                                </label>
                            </div>

                            {{-- BODY --}}
                            <div class="card-body p-3">

                                @foreach($perms as $permission)

                                @php
                                    $permissionName = strtolower((string) $permission->name);
                                    $rawAction = null;
                                    if (preg_match('/(view|create|edit|delete|manage|set|print|config|charge)$/i', $permissionName, $match)) {
                                        $rawAction = strtolower($match[1]);
                                    }
                                    $actionMap = [
                                        'set' => 'view',
                                        'print' => 'view',
                                        'config' => 'view',
                                        'charge' => 'view',
                                    ];
                                    $normalizedAction = $actionMap[$rawAction] ?? $rawAction;
                                    $allowedActions = ['view', 'create', 'edit', 'delete', 'manage'];
                                @endphp

                                @if(in_array($normalizedAction, $allowedActions, true))

                                <div class="form-check mb-2">
                                    <input type="checkbox"
                                        class="form-check-input permission-checkbox "
                                        data-group="{{ $module }}"
                                        name="permissions[]"
                                        value="{{ $permission->name }}"
                                        id="{{ $permission->name }}" style="margin-left: 0 !important;">

                                    <label class="form-check-label"
                                        for="{{ $permission->name }}">
                                        {{ ucfirst($normalizedAction) }}
                                    </label>
                                </div>

                                @endif

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
                    class="btn btn-info">
                    Back
                </a>

                <button class="btn btn-primary">
                    Save
                </button>
            </div>

        </form>
    </div>
</div>
@endsection
@push("scripts")
<script>
document.addEventListener('DOMContentLoaded', function () {

    const selectAll = document.getElementById('select-all-permissions');
    const permissions = document.querySelectorAll('.permission-checkbox');
    const groupSelects = document.querySelectorAll('.group-select');

    if(selectAll){
        selectAll.addEventListener('change', function () {
            permissions.forEach(cb => cb.checked = this.checked);
            groupSelects.forEach(gs => gs.checked = this.checked);
        });
    }

    groupSelects.forEach(group => {
        group.addEventListener('change', function () {
            const groupName = this.dataset.group;
            document
                .querySelectorAll(`.permission-checkbox[data-group="${groupName}"]`)
                .forEach(cb => cb.checked = this.checked);
            syncAll();
        });
    });

    permissions.forEach(cb => cb.addEventListener('change', syncAll));

    function syncAll() {
        selectAll.checked = [...permissions].every(cb => cb.checked);
        groupSelects.forEach(group => {
            const groupName = group.dataset.group;
            const groupPerms = document.querySelectorAll(`.permission-checkbox[data-group="${groupName}"]`);
            group.checked = groupPerms.length > 0 && [...groupPerms].every(cb => cb.checked);
        });
    }

    syncAll();
});
</script>

@endpush
