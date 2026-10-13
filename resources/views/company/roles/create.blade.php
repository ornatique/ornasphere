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
                    $moduleLabels = $permissionModuleLabels ?? [];
                    $menuLabels = $permissionMenuLabels ?? [];
                @endphp

                <div class="permission-toolbar">
                    <label class="permission-check mb-0">
                        <input type="checkbox" id="select-all-permissions">
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
                            @php $currentMenu = null; @endphp
                            @foreach($permissions as $module => $perms)
                                @php
                                    $moduleLabel = $moduleLabels[$module] ?? ucwords(str_replace(['-', '_', '.'], ' ', $module));
                                    $menuLabel = $menuLabels[$module] ?? 'Other';
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

                                @if($currentMenu !== $menuLabel)
                                    @php $currentMenu = $menuLabel; @endphp
                                    <tr class="permission-section-row" data-menu="{{ \Illuminate\Support\Str::slug($menuLabel) }}" data-search="{{ strtolower($menuLabel) }}">
                                        <td colspan="{{ count($actionLabels) + 1 }}">
                                            <label class="permission-section-label">
                                                <input type="checkbox"
                                                       class="section-select"
                                                       data-menu="{{ \Illuminate\Support\Str::slug($menuLabel) }}">
                                                <span>{{ $menuLabel }}</span>
                                            </label>
                                        </td>
                                    </tr>
                                @endif

                                <tr class="permission-row" data-menu="{{ \Illuminate\Support\Str::slug($menuLabel) }}" data-search="{{ strtolower($moduleLabel . ' ' . $module) }}">
                                    <td>
                                        <label class="permission-module-label">
                                            <input type="checkbox"
                                                   class="group-select"
                                                   data-menu="{{ \Illuminate\Support\Str::slug($menuLabel) }}"
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
                                                           data-menu="{{ \Illuminate\Support\Str::slug($menuLabel) }}"
                                                           data-group="{{ $module }}"
                                                           name="permissions[]"
                                                           value="{{ $permission->name }}"
                                                           id="{{ $permissionId }}">
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
        border-collapse: collapse;
        border-spacing: 0;
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

    .permission-section-row td {
        background: #202239;
        color: #bfc8ff;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .02em;
        text-transform: uppercase;
        position: static;
    }

    .permission-module-col {
        width: 320px;
    }

    .permission-action-col {
        width: 110px;
        text-align: center;
    }

    .permission-check,
    .permission-section-label,
    .permission-module-label,
    .permission-action-label {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin: 0;
        cursor: pointer;
    }

    .permission-section-label span,
    .permission-module-label span,
    .permission-check span {
        color: #fff;
        font-weight: 700;
    }

    .permission-section-label span {
        color: #bfc8ff;
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

    .permission-row.is-hidden,
    .permission-section-row.is-hidden {
        display: none;
    }
</style>
@endpush
@push("scripts")
<script>
document.addEventListener('DOMContentLoaded', function () {

    const selectAll = document.getElementById('select-all-permissions');
    const permissionSearch = document.getElementById('permission-search');
    const permissions = document.querySelectorAll('.permission-checkbox');
    const groupSelects = document.querySelectorAll('.group-select');
    const sectionSelects = document.querySelectorAll('.section-select');
    const permissionRows = document.querySelectorAll('.permission-row');
    const sectionRows = document.querySelectorAll('.permission-section-row');

    if(selectAll){
        selectAll.addEventListener('change', function () {
            permissions.forEach(cb => cb.checked = this.checked);
            groupSelects.forEach(gs => gs.checked = this.checked);
            sectionSelects.forEach(ss => ss.checked = this.checked);
        });
    }

    sectionSelects.forEach(section => {
        section.addEventListener('change', function () {
            const menuName = this.dataset.menu;
            document
                .querySelectorAll(`.permission-checkbox[data-menu="${menuName}"], .group-select[data-menu="${menuName}"]`)
                .forEach(cb => cb.checked = this.checked);
            syncAll();
        });
    });

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

    if (permissionSearch) {
        permissionSearch.addEventListener('input', function () {
            const term = this.value.trim().toLowerCase();

            permissionRows.forEach(row => {
                const searchableText = row.dataset.search || '';
                row.classList.toggle('is-hidden', term !== '' && !searchableText.includes(term));
            });

            sectionRows.forEach(section => {
                let hasVisibleRow = false;
                let next = section.nextElementSibling;

                while (next && !next.classList.contains('permission-section-row')) {
                    if (next.classList.contains('permission-row') && !next.classList.contains('is-hidden')) {
                        hasVisibleRow = true;
                        break;
                    }
                    next = next.nextElementSibling;
                }

                section.classList.toggle('is-hidden', !hasVisibleRow);
            });
        });
    }

    function syncAll() {
        selectAll.checked = permissions.length > 0 && [...permissions].every(cb => cb.checked);
        groupSelects.forEach(group => {
            const groupName = group.dataset.group;
            const groupPerms = document.querySelectorAll(`.permission-checkbox[data-group="${groupName}"]`);
            group.checked = groupPerms.length > 0 && [...groupPerms].every(cb => cb.checked);
        });
        sectionSelects.forEach(section => {
            const menuName = section.dataset.menu;
            const sectionPerms = document.querySelectorAll(`.permission-checkbox[data-menu="${menuName}"]`);
            section.checked = sectionPerms.length > 0 && [...sectionPerms].every(cb => cb.checked);
        });
    }

    syncAll();
});
</script>

@endpush
