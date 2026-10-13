# Raw stock from Receive / Purchase

An item must have `jobwork_item_type=raw`. Receive its physical stock using the existing item receipt/purchase form with `product_id` set to that item. A cash-only Receive Amount entry does not create material stock.

Jobwork Issue consumes available **net grams**, oldest receipt first. Linked Jobwork Receive of the same raw item restores that issue's source receipts proportionally. Example: receive 500 g, issue 100 g, return 50 g -> 450 g available. Customer fine-credit/cash ledgers are not modified by these physical movements. Source party is preserved.

Finished items do not consume raw stock under this rule. Raw returns must use the **issue-linked** receive screen/API; Direct Receive is not a raw return against an issue. On the existing receive editor, submit the complete received total: after receiving 50 g then another 20 g, save 70 g, not 20 g. This editor replaces its previous lines.

Repeated saves replace allocations. Over-issues, over-returns and reductions of returns already re-issued are rejected. A raw issue with receive lines cannot be edited/deleted until its receive lines are removed; removal is itself prevented if returned stock has already been re-issued. Partial raw returns no longer automatically mark the outstanding weight as loss.

Stock Position web/API, PDF, CSV and web receipt detail reports use remaining receipt weights. Gross/other/fine weights are proportionally scaled to remaining net grams; partial bulk receipts retain a quantity of one until exhausted. Labour/other monetary fields retain their original receipt values. Jobwork dropdown availability is a page-load snapshot; the server checks again on save.

## Upload files

- app/Services/JobworkRawStock.php
- app/Console/Commands/BackfillJobworkRawStock.php
- database/migrations/2026_10_10_120000_create_jobwork_raw_stock_allocations.php
- app/Http/Controllers/Company/JobworkIssueController.php
- app/Http/Controllers/Company/JobworkReceiveController.php
- app/Http/Controllers/Company/ReportController.php
- app/Http/Controllers/Api/JobworkIssueApiController.php
- app/Http/Controllers/Api/JobworkReceiveApiController.php
- app/Http/Controllers/Api/ReportApiController.php
- resources/views/company/jobwork_issue/create.blade.php
- resources/views/company/jobwork_receive/show.blade.php
- routes/api.php

Run the targeted migration before serving the updated code:

```sh
php artisan migrate --path=database/migrations/2026_10_10_120000_create_jobwork_raw_stock_allocations.php --force
php artisan view:clear
php artisan route:clear
```

For each company with existing raw jobwork issues, replace COMPANY_ID with its numeric ID and run the dry-run before enabling new issues:

```sh
php artisan jobwork:backfill-raw-stock COMPANY_ID --dry-run
php artisan jobwork:backfill-raw-stock COMPANY_ID
```

The backfill uses existing receipts and issues, creates only allocation links, and skips already-linked issues. If it reports a shortage or invalid return, no allocations in that command run are saved. Resolve the source data first. Unlinked historical issues do not reduce stock until this step succeeds. Run deployment/backfill during a maintenance window so users cannot work from the pre-backfill balance.

## API changes

Existing issue and linked receive payload field names are unchanged:

- `POST /api/jobwork-issues` and `PUT /api/jobwork-issues/{id}` now enforce available raw net weight and update stock automatically (including existing aliases).
- `PUT` or `POST /api/jobwork-receive/{id}` restores raw stock for the same item; `{id}` is the **jobwork issue ID**, as in the existing API. Send `items[].item_id` and normal receive weight fields. No separate stock adjustment request is needed.
- Raw issue net weight must be positive and agree with gross minus other weight when gross is supplied.
- Validation failures return HTTP 422 with `errors.items` when requesting JSON. The app should show this message.
- `GET /api/reports/stock-position` and its exports return the remaining stock with the existing response structure.
- New authenticated `GET /api/jobwork-issues/raw-stock` returns company-scoped current availability for the app's item selector:

```json
{"success":true,"data":[{"item_id":123,"item_name":"Patru","available_net_wt":450,"unit":"g"}]}
```

The app only needs a new call if it wants to display availability before submitting. Refresh after issuing or receiving; save-time validation remains authoritative. Never send a second stock subtraction/addition for the same voucher.

The admin issue form also shows a live preview for selected raw items: current shop stock, draft issue net weight, and projected stock after saving. On edit it adds back the existing voucher allocation for the preview. The admin issue-linked receive form shows saved shop stock, issued weight, total raw returned, and outstanding weight with the worker. These admin UI additions require no further API payload changes.

No production database migration, backfill or deployment was performed from this task.
