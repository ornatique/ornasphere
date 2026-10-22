# Casting branch selection and exports (web)

Main-company administrators can select multiple active branches across all 11 Casting submenu pages.
Branch staff remain restricted to their own company. All row actions and individual voucher PDFs
use the owning company's route. Stage totals correlate to the voucher company, not the page company.

The list toolbar downloads all matching records as PDF or XLSX, including the current date, worker
and table-search filters. Every exported row contains Company / Branch; the heading lists selected
companies. Export ignores pagination. Voucher History provides exports of all vouchers in the selected
branches with their stages and details; its existing per-voucher PDF remains available.

New records are created in the page company. For another destination, select only that branch first.

This change covers web pages. Casting API controllers are not changed in this update.
No migration or new route is required. Existing CompanyBranches, ReportXlsx services and the admin
layout from earlier updates must already be deployed.

## Upload these 27 files

- app/Http/Middleware/EnsureCompanyIsActive.php
- app/Services/CastingListExport.php
- resources/views/company_layout/management_branch_selector.blade.php
- resources/views/company_layout/casting_exports.blade.php
- resources/views/company/casting_exports/list.blade.php
- app/Http/Controllers/Company/VacuumBuchController.php
- app/Http/Controllers/Company/VacuumProcessController.php
- app/Http/Controllers/Company/VacuumVoucherController.php
- app/Http/Controllers/Company/CastingHeatingController.php
- app/Http/Controllers/Company/CastingMetalIssueController.php
- app/Http/Controllers/Company/CastingReleaseController.php
- app/Http/Controllers/Company/TreeCuttingOfficeController.php
- app/Http/Controllers/Company/TreeCuttingIssueController.php
- app/Http/Controllers/Company/TreeCuttingReceiveController.php
- app/Http/Controllers/Company/CastingSortingController.php
- app/Http/Controllers/Company/VoucherHistoryController.php
- resources/views/company/vacuum_buchs/index.blade.php
- resources/views/company/vacuum_processes/index.blade.php
- resources/views/company/vacuum_vouchers/index.blade.php
- resources/views/company/casting_heating/index.blade.php
- resources/views/company/casting_metal_issue/index.blade.php
- resources/views/company/casting_release/index.blade.php
- resources/views/company/tree_cutting_office/index.blade.php
- resources/views/company/tree_cutting_issue/index.blade.php
- resources/views/company/tree_cutting_receive/index.blade.php
- resources/views/company/casting_sorting/index.blade.php
- resources/views/company/voucher_history/index.blade.php

Then run `php artisan view:clear`. Do not clear the application's token-revocation cache.

Tests: `php vendor/bin/phpunit tests/Feature/CastingBranchSelectionTest.php`.
Tests and this documentation are not production runtime files.
