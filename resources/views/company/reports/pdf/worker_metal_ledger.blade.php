<!DOCTYPE html><html><head><meta charset="utf-8"><style>
@page { size: A4 portrait; margin: 24px; } body { font-family: DejaVu Sans, sans-serif; font-size: 9px; }
h2 { font-size: 16px; } h4 { margin-bottom: 6px; } table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
td, th { border: 1px solid #333; padding: 5px; } th { background: #eee; } thead { display: table-header-group; } tr { page-break-inside: avoid; }
</style></head><body>
<h2>Worker Metal Ledger — {{ $company->name }}</h2>
<p>Format: {{ ($ledger['filters']['format'] ?? 'detailed') === 'daily' ? 'Date-wise Summary' : 'Detailed Ledger' }}</p>
<p>From: {{ !empty($ledger['filters']['from_date']) ? \Carbon\Carbon::parse($ledger['filters']['from_date'])->format('d-m-Y') : 'Beginning' }} · To: {{ !empty($ledger['filters']['to_date']) ? \Carbon\Carbon::parse($ledger['filters']['to_date'])->format('d-m-Y') : 'Latest' }} · Unit: grams</p>
<p>Dr: pending from worker. Cr: excess received. Balances use issued and received net weight without purity conversion. Loss is not automatically deducted.</p>
@include('company.reports.partials.worker_metal_ledger')
</body></html>
