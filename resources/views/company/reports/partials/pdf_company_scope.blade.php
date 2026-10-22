@if($company instanceof \App\Models\Company && request()->user())
    @php
        $pdfCompanyIds = \App\Services\ReportBranches::ids($company);
    @endphp
    @if(count($pdfCompanyIds) > 1)
        <div style="margin:10px 0;padding:8px;border:1px solid #bbb;background:#f3f3f3;font-size:10px;line-height:1.6;overflow-wrap:break-word;">
            <strong>Selected companies / branches ({{ count($pdfCompanyIds) }}):</strong>
            {{ \App\Services\ReportBranches::heading($company) }}
        </div>
    @endif
@endif
