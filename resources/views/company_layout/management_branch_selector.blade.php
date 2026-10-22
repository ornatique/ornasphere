@php
    $managementUser = auth()->user();
    $managementController = explode('@', request()->route()->getActionName())[0];
    $managementRoutes = [
        \App\Http\Controllers\Company\CompanyUserController::class => 'company.users.index',
        \App\Http\Controllers\Company\CustomerController::class => 'company.customers.index',
        \App\Http\Controllers\Company\CompanyRoleController::class => 'company.roles.index',
    ];
    $managementRoutes[\App\Http\Controllers\Company\VacuumBuchController::class] = 'company.vacuum-buchs.index';
    $managementRoutes[\App\Http\Controllers\Company\VacuumProcessController::class] = 'company.vacuum-processes.index';
    $managementRoutes[\App\Http\Controllers\Company\VacuumVoucherController::class] = 'company.vacuum-vouchers.index';
    $managementRoutes[\App\Http\Controllers\Company\CastingHeatingController::class] = 'company.casting-heating.index';
    $managementRoutes[\App\Http\Controllers\Company\CastingMetalIssueController::class] = 'company.casting-metal-issue.index';
    $managementRoutes[\App\Http\Controllers\Company\CastingReleaseController::class] = 'company.casting-release.index';
    $managementRoutes[\App\Http\Controllers\Company\TreeCuttingOfficeController::class] = 'company.tree-cutting-office.index';
    $managementRoutes[\App\Http\Controllers\Company\TreeCuttingIssueController::class] = 'company.tree-cutting-issue.index';
    $managementRoutes[\App\Http\Controllers\Company\TreeCuttingReceiveController::class] = 'company.tree-cutting-receive.index';
    $managementRoutes[\App\Http\Controllers\Company\CastingSortingController::class] = 'company.casting-sorting.index';
    $managementRoutes[\App\Http\Controllers\Company\VoucherHistoryController::class] = 'company.voucher-history.index';
    $managementRoutes[\App\Http\Controllers\Company\LabourFormulaController::class] = 'company.labour-formula.index';
    $managementRoutes[\App\Http\Controllers\Company\ProductionCostController::class] = 'company.production-cost.index';
    $managementRoutes[\App\Http\Controllers\Company\ProductionStepController::class] = 'company.production-step.index';
    $managementRoutes[\App\Http\Controllers\Company\JobworkIssueController::class] = 'company.jobwork-issue.index';
    $managementRoutes[\App\Http\Controllers\Company\JobworkReceiveController::class] = 'company.jobwork-receive.index';
    $managementRoutes[\App\Http\Controllers\Company\ItemController::class] = 'company.items.index';
    $managementRoutes[\App\Http\Controllers\Company\LabelConfigController::class] = 'company.label_config.index';
    $managementRoutes[\App\Http\Controllers\Company\ItemSetController::class] = 'company.list_itemset';
    $managementRoutes[\App\Http\Controllers\Company\OtherChargeController::class] = 'company.other-charge.index';
    if ($managementController === \App\Http\Controllers\Company\ItemSetController::class && request()->routeIs('company.item_sets.qrList', 'company.item_sets.print*')) $managementRoutes[$managementController] = 'company.item_sets.qrList';
    $managementRoutes[\App\Http\Controllers\Company\SaleController::class] = 'company.sales.index';
    $managementRoutes[\App\Http\Controllers\Company\ApprovalController::class] = 'company.approval.index';
    $managementRoutes[\App\Http\Controllers\Company\SaleReturnController::class] = 'company.returns.index';
    $managementRoutes[\App\Http\Controllers\Company\CustomerAdvanceController::class] = 'company.sales.advance.index';
    $managementRoutes[\App\Http\Controllers\Company\BullionController::class] = 'company.bullion.index';
    $managementRoutes[\App\Http\Controllers\Company\QrMakingController::class] = 'company.qr-making.index';
    $managementRoutes[\App\Http\Controllers\Company\StockGalleryController::class] = 'company.stock-gallery.index';
    if (request()->routeIs('company.reports.visiting-cards.*')) $managementRoutes[\App\Http\Controllers\Company\ReportController::class] = 'company.reports.visiting-cards.index';
    $managementIndex = $managementRoutes[$managementController] ?? null;
@endphp
@if($managementIndex && $managementUser && \App\Services\CompanyBranches::canManage($managementUser))
<div style="margin:24px 24px 0;padding:20px 24px;background:#292c43;border:1px solid #484d6a;border-radius:12px">
    <label for="management-branch" style="display:block;color:#fff;font-weight:600;margin-bottom:10px">Select company / branch</label>
    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
    <div id="management-branch-field" style="flex:1 1 280px;max-width:865px;min-width:0">
    <select multiple id="management-branch" class="form-control" style="max-width:650px;min-height:46px;color:#fff">
        @foreach(\App\Services\CompanyBranches::visibleCompanies($managementUser) as $managementBranch)
        <option value="{{ $managementBranch->id }}" data-url="{{ route($managementIndex, $managementBranch->slug) }}" @selected(in_array((string) $managementBranch->id, array_map('strval', (array) request('management_branches', [])), true) || (!request()->has('management_branches') && request()->route('slug') === $managementBranch->slug)) @disabled(!$managementBranch->status)>{{ $managementBranch->name }} ({{ $managementBranch->id === $managementUser->company_id ? 'Main company' : 'Branch' }}){{ !$managementBranch->status ? ' - Inactive' : '' }}</option>
        @endforeach
    </select>
    </div>
    <button type="button" id="management-branch-view" class="btn btn-success" style="min-height:48px;padding:12px 25px">View</button>
    </div>
    <p style="color:#bdc7df;margin:10px 0 0;font-size:13px">Select one or more companies or branches, then click View to see their records together. Save any edits before switching.</p>
</div>
@push('scripts')
<script>
$(function () {
    const field = $('#management-branch');
    $('#management-branch-view').on('click', function () {
        const ids = field.val() || [];
        if (!ids.length) { field.select2('open'); return; }
        const target = field.find('option:selected').first().attr('data-url');
        const url = new URL(target, window.location.origin);
        ids.forEach(id => url.searchParams.append('management_branches[]', id));
        window.location.assign(url.toString());
    });
    if ($.fn.select2 && !field.hasClass('select2-hidden-accessible')) {
        field.select2({
            width: '100%',
            closeOnSelect: false,
            placeholder: 'Search and select companies or branches',
            minimumResultsForSearch: 0,
            templateSelection: function (item) {
                return $('<span class="management-selected-tag"></span>').text(item.text);
            },
            templateResult: function (item) {
                const row = $('<span class="management-result"></span>').text(item.text);
                if (item.element && item.element.selected) {
                    row.append($('<small></small>').text('Selected'));
                }
                return row;
            },
            dropdownParent: $('#management-branch-field')
        });
        field.on('select2:open', function () {
            const search = document.querySelector('#management-branch-field .select2-search__field');
            if (search) {
                search.placeholder = 'Search company or branch';
                search.setAttribute('aria-label', 'Search company or branch');
                search.focus();
            }
        });
    }
});
</script>
@endpush
<style>
#management-branch-field .select2-selection--multiple {min-height:48px!important;height:auto!important;padding:5px 8px!important;background:#303149;border:1px solid #59617f;border-radius:8px}
#management-branch-field .select2-selection--multiple .select2-selection__rendered {display:flex!important;flex-wrap:wrap;gap:6px;height:auto!important;padding:0!important;align-items:center}
#management-branch-field .select2-selection--multiple .select2-selection__choice {display:flex;align-items:center;max-width:100%;margin:0!important;padding:0 8px!important;background:#3d4568;border:1px solid #6975a6;border-radius:5px;color:#fff}
#management-branch-field .select2-selection__choice__remove {color:#fff!important;margin-right:7px!important;position:static!important}
#management-branch-field .select2-selection--multiple .management-selected-tag {border:0;padding:0;background:transparent;line-height:30px}
#management-branch-field .select2-search--inline {float:none;min-width:150px;flex:1;margin:0}
#management-branch-field .select2-search--inline .select2-search__field {margin:0!important;width:100%!important;min-height:32px;color:#fff;background:transparent}
#management-branch-field .select2-search__field::placeholder {color:#c5cce0}

#management-branch-field .select2-container {width:100%!important}
#management-branch-field .select2-selection--single {height:48px!important;min-height:48px;box-sizing:border-box;padding:0!important;display:flex;align-items:center;background:#303149;border:1px solid #59617f;border-radius:8px}
#management-branch-field .select2-selection__rendered {display:flex!important;align-items:center;height:100%;width:100%;min-width:0;box-sizing:border-box;line-height:normal!important;margin:0!important;color:#fff;padding:0 36px 0 8px!important}
#management-branch-field .select2-selection__arrow {height:46px;top:0;right:8px}
#management-branch-field .select2-container--focus .select2-selection {outline:2px solid #9aaaff;outline-offset:2px}
#management-branch-field .management-selected-tag {display:block;max-width:100%;box-sizing:border-box;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin:0;line-height:30px;padding:0 10px;border:1px solid #6975a6;border-radius:5px;background:#3d4568;color:#fff;font-weight:600;font-size:13px}
#management-branch-field .select2-results__option {padding:13px 12px;color:#fff}
#management-branch-field .select2-results__option[aria-selected="true"],#management-branch-field .select2-results__option--highlighted {background:#3269d3;color:#fff}
#management-branch-field .management-result {display:flex;align-items:center;justify-content:space-between;gap:15px}
#management-branch-field .management-result small {color:#b9e8ff}
#management-branch-field .select2-results__option[aria-disabled="true"] {color:#8f95ad;background:transparent}
#management-branch-field .select2-dropdown {background:#303149;border:1px solid #6975a6}
#management-branch-field .select2-container {vertical-align:middle}
#management-branch-view {height:48px;line-height:24px;margin:0;border-radius:6px}
@media(max-width:576px){#management-branch-field{flex-basis:100%!important}#management-branch-view{width:100%}}
</style>
@endif
