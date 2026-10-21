# Company branches

## Workflow

Superadmin creates the main company as before. Its company administrator creates branches in **Manage Profile → Branch Management**, choosing Manufacturing/Retail, branch address, administrator name/email and password. No invitation email is sent automatically. Share the login URL and credentials securely. Branch admins follow the existing two-factor login setup.

Each branch is a company workspace with `parent_company_id` pointing to the main company. Existing transactions remain unchanged. Branches start without inventory or transactions. Branch admins manage their users and roles in their own workspace; Manufacturing/Retail labels do not automatically limit features.

Main company admins can edit branch name/type/address and activate/deactivate branches in Profile. They cannot create nested branches. The main dashboard defaults to **All branches**, including the main company. A selector scopes all existing dashboard counts, sales/return charts and recent activity to a single owned branch. Recent activity identifies its branch. Ordinary main-company staff and branch staff only see their own workspace.

The dashboard selector is read-only and does not switch the active transaction workspace. Consolidated transaction report screens, cross-branch editing, stock transfer workflows and a new mobile branch management/dashboard endpoint are not included. Existing API users remain attached to their own company/branch.

## Deployment

Upload these new files:

- `database/migrations/2026_10_13_180000_add_company_branches.php`
- `app/Services/CompanyBranches.php`
- `app/Http/Controllers/Company/BranchController.php`
- `resources/views/company/profile/branches.blade.php`

Upload these updated files:

- `routes/web.php`
- `app/Http/Controllers/Company/CompanyProfileController.php`
- `app/Http/Controllers/Company/CompanyDashboardController.php`
- `app/Http/Middleware/EnsureCompanyIsActive.php`
- `app/Services/CompanyPlanService.php`
- `resources/views/company/profile/show.blade.php`
- `resources/views/company/dashboard.blade.php`

Run the branch migration before enabling branch creation:

```sh
php artisan migrate --path=database/migrations/2026_10_13_180000_add_company_branches.php --force
php artisan optimize:clear
```

Branch plan display follows the main company's plan; branch user limit initially copies the main company's limit. Inactive parent companies block child access. Inactive branches cannot use protected web/API routes. Protected company URLs must match the logged-in user's workspace. Migration rollback refuses to remove the hierarchy while branches exist.

Tests: `php vendor/phpunit/phpunit/phpunit tests/Feature/CompanyBranchesTest.php`.
