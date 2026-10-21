# Branch reports

Main company administrators can select their main company, one branch, several branches, or all owned companies from **Branch reports** at the top of reports. The default is the main company. Choose branches and click **View Report**. Customer lists refresh for that selection. Identically named customers remain separate records with a branch label.

The report scope applies to Sales Summary, Purchase / Receiver Summary, Stock Position, Approval & Return Register, Approval Summary, Approval Outstanding, Outstanding Amount, Barcode History, and Worker Loss / Worker Metal Ledger. Existing date, customer, search and other filters still apply. AJAX requests, report detail popups and exports carry the same selection.

Only a main company administrator can select branches. Branch administrators and staff retain their own company's scope and existing report permissions. Server validation rejects unrelated company slugs and cross-branch requests from staff. Transaction controllers and API permissions are not expanded.

## Deployment

No additional database migration is required beyond the existing company-branches migration.

Upload:

- `app/Services/ReportBranches.php` (new)
- `app/Http/Controllers/Company/ReportController.php`
- `app/Services/ApprovalReturnRegister.php`
- `app/Services/ApprovalSummaryReport.php`
- `app/Services/JobworkRawStock.php`
- `app/Services/WorkerMetalLedger.php`
- Updated templates in `resources/views/company/reports/`, including `partials/branch_filter.blade.php`, `partials/worker_metal_ledger.blade.php`, and the report PDF templates.

The previously deployed `CompanyBranches` service is also required. After uploading run `php artisan optimize:clear`.

Branch selection uses the `report_branches` query parameter (comma-separated owned company slugs, or `all`). It is validated on the server. These changes add read-only web reports, not new mobile API report endpoints or access to edit branch transactions.
