# Worker Metal Ledger

Open Reports > Worker Loss Report > Worker Metal Ledger (Dr / Cr).
Filter by worker and From/To date. Blank From includes all history; blank To includes all recorded dates.
Each worker/metal has its own opening, period debit/credit and closing net-weight balance (without purity conversion).
Issues use jobwork_date; receipts use receive_date. Receipt credits are counted once and partial receipts do not repeat issue debits.
Positive balance is Dr (pending from worker); negative is Cr (excess received).
Other weight and recorded loss are not assumed to be recovered metal or approved settlements.
Customer-only direct receipts without a jobworker are excluded from this worker ledger.

## API

Use existing authenticated endpoints with `mode=ledger`:

```
GET /api/reports/worker-loss?mode=ledger&worker_id=1&from_date=2026-10-01&to_date=2026-10-07
GET /api/reports/worker-loss/export/pdf?mode=ledger&worker_id=1&from_date=2026-10-01&to_date=2026-10-07
GET /api/reports/worker-loss/export/excel?mode=ledger&worker_id=1&from_date=2026-10-01&to_date=2026-10-07
```

JSON: success, data.filters, data.groups. Groups contain worker_id, worker_name, metal, opening, debit, credit, closing, rows. Rows contain date, voucher, type, item, debit, credit, balance. Signed balances use positive=Dr, negative=Cr. Excel export is CSV, consistent with the existing export.

## Deploy

Upload these files preserving paths:

- app/Services/WorkerMetalLedger.php
- app/Http/Controllers/Company/ReportController.php
- app/Http/Controllers/Api/ReportApiController.php
- resources/views/company/reports/worker_loss.blade.php
- resources/views/company/reports/worker_metal_ledger.blade.php
- resources/views/company/reports/partials/worker_metal_ledger.blade.php
- resources/views/company/reports/pdf/worker_metal_ledger.blade.php

Run `php artisan view:clear`. No migration or new permission is required; existing worker-loss routes and permissions apply.
Demo PDF uses fictitious data. Local checks: SQLite ledger tests, PHP syntax, Blade compile and rendered PDF inspection. Production data has not been reconciled or changed.
