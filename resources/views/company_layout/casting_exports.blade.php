<div class="d-flex flex-wrap mb-3" style="gap:8px">
    <button type="button" class="btn btn-success casting-list-export" data-format="excel">Export Excel</button>
    <button type="button" class="btn btn-info casting-list-export" data-format="pdf">Export PDF</button>
</div>
@push('scripts')
<script>
$(function () {
    $('.casting-list-export').on('click', function () {
        const element = $('.content-wrapper table').filter(function () { return $.fn.dataTable.isDataTable(this); }).first();
        if (!element.length) return;
        const table = element.DataTable();
        const params = $.extend(true, {}, table.ajax.params() || {});
        params.export = this.dataset.format;
        params.start = 0;
        params.length = -1;
        const url = new URL(table.ajax.url(), window.location.origin);
        const query = new URLSearchParams($.param(params));
        query.forEach((value, key) => url.searchParams.set(key, value));
        window.location.assign(url.toString());
    });
});
</script>
@endpush
