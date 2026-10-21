@php
    $reportCompanyIds = \App\Services\ReportBranches::ids($company);
    $reportBranchValue = request('report_branches', $company->slug);
@endphp
@if(\App\Services\CompanyBranches::canManage(auth()->user()))
<style>
#report-branch-panel .branch-report-controls { display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:start; gap:12px; max-width:1000px; }
#report-branch-panel label { display:block; margin-bottom:10px; font-size:15px; color:#fff; }
#report-branch-panel .branch-report-select { min-width:0; }
#report-branch-panel .select2-container { width:100% !important; }
#report-branch-panel .select2-selection--multiple { min-height:48px; padding:5px 8px; background:#2e2d4d; border:1px solid #555672; border-radius:6px; }
#report-branch-panel .select2-selection__rendered { display:flex !important; flex-wrap:wrap; align-items:center; gap:6px; padding:0 !important; margin:0 !important; white-space:normal; }
#report-branch-panel .select2-selection__choice { display:inline-flex !important; align-items:center; float:none !important; position:relative; max-width:100%; margin:0 !important; padding:7px 12px 7px 30px !important; background:#3d4264 !important; border:1px solid #636c99 !important; border-radius:5px; color:#fff !important; font-size:14px !important; line-height:20px !important; white-space:normal !important; overflow-wrap:anywhere; }
#report-branch-panel .select2-selection__choice__display { padding:0 !important; font-size:14px !important; line-height:20px !important; }
#report-branch-panel .select2-selection__choice__remove { position:absolute !important; left:7px !important; top:50% !important; transform:translateY(-50%); border:0 !important; padding:0 !important; margin:0 !important; background:transparent !important; color:#fff !important; font-size:19px !important; line-height:24px !important; }
#report-branch-panel .select2-search--inline { float:none; margin:0; }
#report-branch-panel .select2-search__field { color:#fff !important; font-size:14px !important; line-height:24px !important; min-height:28px; margin:0 !important; background:transparent !important; }
#report-branch-panel .select2-dropdown { background:#2e2d4d; color:#fff; border:1px solid #636c99; }
#report-branch-panel .select2-results__option { color:#fff; font-size:14px; padding:10px 12px; line-height:22px; white-space:normal; }
#report-branch-panel .select2-results__option[aria-selected="true"],
#report-branch-panel .select2-results__option--selected { background:#424969 !important; color:#fff !important; font-weight:600; }
#report-branch-panel .select2-results__option[aria-selected="true"]::after,
#report-branch-panel .select2-results__option--selected::after { content:'Selected'; float:right; margin-left:12px; color:#bcebd7; font-size:12px; }
#report-branch-panel .select2-results__option--highlighted,
#report-branch-panel .select2-results__option--highlighted[aria-selected="true"],
#report-branch-panel .select2-results__option--highlighted.select2-results__option--selected { background:#306fdd !important; color:#fff !important; }
#report-branch-panel #apply-report-branches { min-height:48px; white-space:nowrap; margin:0; }
#report-branch-panel .branch-report-help { display:block; margin-top:10px; color:#bec5db; font-size:13px; }
@media(max-width:575px) { #report-branch-panel .branch-report-controls { grid-template-columns:minmax(0,1fr); } }
</style>
<div class="card mb-3" id="report-branch-panel"><div class="card-body py-3">
    <label for="report-branches"><strong>Branch reports</strong></label>
    <div class="branch-report-controls">
        <div class="branch-report-select">
        <select id="report-branches" multiple class="form-control" style="width:100%" aria-label="Select report branches">
            <option value="all" @selected($reportBranchValue === 'all')>All companies and branches</option>
            @foreach(\App\Services\CompanyBranches::visibleCompanies(auth()->user()) as $reportBranch)
                <option value="{{ $reportBranch->slug }}" @selected($reportBranchValue !== 'all' && in_array($reportBranch->id, $reportCompanyIds))>{{ $reportBranch->name }}{{ $reportBranch->id === $company->id ? ' (Main Company)' : '' }}</option>
            @endforeach
        </select>
        </div>
        <button type="button" class="btn btn-success" id="apply-report-branches">View Report</button>
    </div>
    <small class="branch-report-help">Select one or more branches to combine their report data.</small>
</div></div>
@endif
<script>
window.reportBranchValue = @json($reportBranchValue);
window.reportBranchUrl = function (value) {
    const url = new URL(value, window.location.href);
    if (url.origin === location.origin && url.pathname.includes('/reports/')) url.searchParams.set('report_branches', window.reportBranchValue);
    return url.href;
};
</script>
@push('scripts')
<script>
// Register before report scripts so the initial DataTable request has the same scope.
$.ajaxPrefilter(function (options) { options.url = window.reportBranchUrl(options.url); });
$(function () {
    if ($.fn.select2) $('#report-branches').select2({width:'100%', placeholder:'Search company or branch', dropdownParent:$('#report-branch-panel'), closeOnSelect:false});
    $('#report-branches').on('select2:select', function (event) {
        const values = $(this).val() || [];
        $(this).val(event.params.data.id === 'all' ? ['all'] : values.filter(value => value !== 'all')).trigger('change.select2');
    });
    $('#apply-report-branches').on('click', function () {
        const selected = $('#report-branches').val() || [];
        if (!selected.length) return;
        const url = new URL(location.href);
        url.searchParams.set('report_branches', selected.includes('all') ? 'all' : selected.join(','));
        ['customer_id','item_id','worker_id','voucher','voucher_no','barcode','search','page','export','city'].forEach(key => url.searchParams.delete(key));
        location.href = url.href;
    });
    document.querySelectorAll('form').forEach(form => {
        if ((form.method || 'get').toLowerCase() !== 'get') return;
        const input = document.createElement('input');
        input.type = 'hidden'; input.name = 'report_branches'; input.value = window.reportBranchValue;
        form.appendChild(input);
    });
    document.addEventListener('click', function (event) {
        const link = event.target.closest('a[href]');
        if (link && !link.getAttribute('href').startsWith('#')) link.href = window.reportBranchUrl(link.href);
    }, true);
});
</script>
@endpush
