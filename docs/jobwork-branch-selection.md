# Jobwork web branch selection

Searchable multiple branch selection is available for Labour Formula, Production Cost, Production Step, Jobwork Issue and Jobwork Receive. Click View to load the selected branches. Each row shows its company/branch; actions use the owning branch. New records belong to the company in the current page URL. Only authorized active companies are selectable.

PDF and XLSX exports include the selected branch names and a Company / Branch column, honor applied list filters and include all matching rows rather than only the current page. Receive exports combine issue-linked and direct entries.

## Upload these files

- `app/Http/Controllers/Company/LabourFormulaController.php`
- `app/Http/Controllers/Company/ProductionCostController.php`
- `app/Http/Controllers/Company/ProductionStepController.php`
- `app/Http/Controllers/Company/JobworkIssueController.php`
- `app/Http/Controllers/Company/JobworkReceiveController.php`
- `app/Http/Middleware/EnsureCompanyIsActive.php`
- `app/Services/JobworkListExport.php`
- `app/Services/CastingListExport.php`
- `resources/views/company/labour_formula/index.blade.php`
- `resources/views/company/production_cost/index.blade.php`
- `resources/views/company/production_step/index.blade.php`
- `resources/views/company/jobwork_issue/index.blade.php`
- `resources/views/company/jobwork_receive/index.blade.php`
- `resources/views/company_layout/management_branch_selector.blade.php`
- `resources/views/company_layout/jobwork_exports.blade.php`

## Existing dependencies

These must also be deployed if the preceding Casting and User Management branch changes are not yet on the server:

- `app/Services/CompanyBranches.php`
- `app/Services/ReportXlsx.php`
- `resources/views/company/casting_exports/list.blade.php`
- `resources/views/company_layout/admin.blade.php`

No database migration or new route is required. Run `php artisan view:cache` after upload. Do not clear the application cache, which holds inactive-token responses.

## Tests

`tests/Feature/JobworkBranchSelectionTest.php` checks selection, owner actions, unrelated-company rejection, combined receive rows and full XLSX exports.
