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

                @php
                    $actionLabels = [
                        'view' => 'View',
                        'create' => 'Create',
                        'edit' => 'Edit',
                        'delete' => 'Delete',
                        'manage' => 'Manage',
                    ];
                    $actionMap = [
                        'set' => 'view',
                        'print' => 'view',
                        'config' => 'view',
                        'charge' => 'view',
                    ];
                    $moduleLabels = [
                        'vacuum-live-dashboard' => 'Production Monitor',
                    ];
                @endphp

                <div class="permission-toolbar">
                    <label class="permission-check mb-0">
                        <input type="checkbox" id="select-all">
                        <span>Select All Permissions</span>
                    </label>
                    <div class="permission-search">
                        <input type="text"
                               id="permission-search"
                               class="form-control"
                               placeholder="Search permission">
                    </div>
                </div>

                <div class="permission-table-wrap">
                    <table class="table table-bordered permission-table">
                        <thead>
                            <tr>
                                <th class="permission-module-col">Module</th>
                                @foreach($actionLabels as $label)
                                    <th class="permission-action-col">{{ $label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($permissions as $module => $perms)
                                @php
                                    $moduleLabel = $moduleLabels[$module] ?? ucwords(str_replace(['-', '_', '.'], ' ', $module));
                                    $actionPermissions = [];

                                    foreach ($perms as $permission) {
                                        $permissionName = strtolower((string) $permission->name);
                                        $rawAction = null;

                                        if (preg_match('/(view|create|edit|delete|manage|set|print|config|charge)$/i', $permissionName, $match)) {
                                            $rawAction = strtolower($match[1]);
                                        }

                                        $normalizedAction = $actionMap[$rawAction] ?? $rawAction;

                                        if (array_key_exists($normalizedAction, $actionLabels) && ! isset($actionPermissions[$normalizedAction])) {
                                            $actionPermissions[$normalizedAction] = $permission;
                                        }
                                    }
                                @endphp

                                <tr class="permission-row" data-search="{{ strtolower($moduleLabel . ' ' . $module) }}">
                                    <td>
                                        <label class="permission-module-label">
                                            <input type="checkbox"
                                                   class="group-select"
                                                   data-group="{{ $module }}">
                                            <span>{{ $moduleLabel }}</span>
                                        </label>
                                    </td>
                                    @foreach($actionLabels as $action => $label)
                                        <td class="text-center">
                                            @if(isset($actionPermissions[$action]))
                                                @php
                                                    $permission = $actionPermissions[$action];
                                                    $permissionId = 'permission-' . md5($module . '-' . $action . '-' . $permission->name);
                                                @endphp
                                                <label class="permission-action-label" for="{{ $permissionId }}">
                                                    <input type="checkbox"
                                                           class="permission-checkbox"
                                                           data-group="{{ $module }}"
                                                           name="permissions[]"
                                                           value="{{ $permission->name }}"
                                                           id="{{ $permissionId }}"
                                                           {{ in_array($permission->name, $rolePermissions) ? 'checked' : '' }}>
                                                </label>
                                            @else
                                                <span class="permission-empty">-</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
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

@push('styles')
<style>
    .permission-toolbar {
        border: 1px solid #343852;
        background: #282a3f;
        padding: 10px 12px;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }

    .permission-search {
        width: min(360px, 100%);
    }

    .permission-search .form-control {
        height: 38px;
    }

    .permission-table-wrap {
        border: 1px solid #343852;
        max-height: calc(100vh - 360px);
        overflow: auto;
    }

    .permission-table {
        margin-bottom: 0;
        min-width: 760px;
        table-layout: fixed;
    }

    .permission-table thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #25263a;
        color: #fff;
    }

    .permission-table th,
    .permission-table td {
        padding: 10px 12px;
        vertical-align: middle;
    }

    .permission-module-col {
        width: 320px;
    }

    .permission-action-col {
        width: 110px;
        text-align: center;
    }

    .permission-check,
    .permission-module-label,
    .permission-action-label {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin: 0;
        cursor: pointer;
    }

    .permission-module-label span,
    .permission-check span {
        color: #fff;
        font-weight: 700;
    }

    .permission-module-label span {
        text-transform: uppercase;
    }

    .permission-table input[type="checkbox"],
    .permission-toolbar input[type="checkbox"] {
        width: 16px;
        height: 16px;
        cursor: pointer;
    }

    .permission-empty {
        color: #777b95;
    }

    .permission-row.is-hidden {
        display: none;
    }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    const selectAll = document.getElementById('select-all');
    const permissionSearch = document.getElementById('permission-search');
    const permissions = document.querySelectorAll('.permission-checkbox');
    const groupSelects = document.querySelectorAll('.group-select');
    const permissionRows = document.querySelectorAll('.permission-row');

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

    if (permissionSearch) {
        permissionSearch.addEventListener('input', function () {
            const term = this.value.trim().toLowerCase();

            permissionRows.forEach(row => {
                const searchableText = row.dataset.search || '';
                row.classList.toggle('is-hidden', term !== '' && !searchableText.includes(term));
            });
        });
    }

    function syncAll() {
        selectAll.checked = permissions.length > 0 && [...permissions].every(cb => cb.checked);

        groupSelects.forEach(group => {
            const groupName = group.dataset.group;
            const groupPerms = document.querySelectorAll(
                `.permission-checkbox[data-group="${groupName}"]`
            );
            group.checked = groupPerms.length > 0 && [...groupPerms].every(cb => cb.checked);
        });
    }

    syncAll(); // initial sync
});
</script>
@endpush
