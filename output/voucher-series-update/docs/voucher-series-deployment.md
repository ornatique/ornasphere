# Company voucher configuration

Company admins: open Profile Details → Voucher Configuration. Blank prefix uses company initials (Madina Jwellary → MJ). Enter SJ to override it. Only company admins may view or change configuration; other users can create vouchers under their existing permissions.

New voucher types: SL, APP, AR, SR, RP, JI, JWR, VV. Each module has its own counter shared by web and API. Mixed approval/sale returns use AR. Existing voucher numbers stay unchanged. Prefix changes continue the module counter (MJ-APP-005 → SJ-APP-006). Three digits is a minimum; 999 becomes 1000. No yearly reset. Deleting a saved voucher does not decrement its counter. Failed transactional saves roll back their allocation.

Casting and tree-cutting continue displaying the original Vacuum Voucher reference. Jobwork receive against an issue keeps its issue reference. Item labels/barcodes and the disabled order/CAD/RPT modules are unchanged.

## Deploy

Back up the database and deploy all listed files together during a maintenance window; the new code requires the migration. Do not roll back this migration after using the series: removing counters loses the allocation history.

```sh
php artisan down
php artisan migrate --path=database/migrations/2026_10_06_000002_add_company_voucher_series.php --force
php artisan optimize:clear
php artisan up
```

Migration adds companies.voucher_prefix and company_voucher_counters. It changes global unique voucher indexes in sales, approval_headers and customer_advance_vouchers to company-scoped indexes, retaining existing data.

Files to upload:
- app/Services/VoucherSeries.php
- app/Services/CustomerSalesReturn.php
- app/Http/Controllers/Company/VoucherSeriesController.php
- app/Http/Controllers/Company/CompanyProfileController.php
- app/Http/Controllers/Company/ApprovalController.php
- app/Http/Controllers/Api/ApprovalApiController.php
- app/Http/Controllers/Company/SaleController.php
- app/Http/Controllers/Api/SaleApiController.php
- app/Http/Controllers/Company/SaleReturnController.php
- app/Http/Controllers/Api/SaleReturnApiController.php
- app/Http/Controllers/Company/CustomerAdvanceController.php
- app/Http/Controllers/Api/CustomerAdvanceApiController.php
- app/Http/Controllers/Company/JobworkIssueController.php
- app/Http/Controllers/Api/JobworkIssueApiController.php
- app/Http/Controllers/Company/JobworkReceiveController.php
- app/Http/Controllers/Api/JobworkReceiveApiController.php
- app/Http/Controllers/Company/VacuumVoucherController.php
- app/Http/Controllers/Api/VacuumVoucherApiController.php
- resources/views/company/profile/show.blade.php
- routes/web.php
- routes/api.php
- database/migrations/2026_10_06_000002_add_company_voucher_series.php

## Configuration API

Use existing Authorization: Bearer token and Accept: application/json.
GET /api/company/voucher-series returns effective/default/custom prefix and all next-number previews.
PUT /api/company/voucher-series with Content-Type: application/json:

```json
{"prefix":"SJ"}
```

Restore the automatic prefix:

```json
{"prefix":""}
```

Existing voucher creation APIs generate the number on the server. No number field needs to be supplied by the app. Their existing voucher_no, approval_no, return_voucher_no and receive_no response fields carry the new format.

Validation: 9 SQLite tests, 69 assertions passed (series, prefix configuration, scoped uniqueness and sold-item returns); Blade compilation and route registration passed. Concurrent MySQL requests have not been exercised locally; allocation uses a transaction and a company row lock. Production migration/deployment has not been performed.

