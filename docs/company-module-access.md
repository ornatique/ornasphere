# Company module access

Super Admin selects Allowed Modules on Create Company and Edit Company. New
companies start with no optional modules selected. Existing companies with a null
selection retain all modules until edited. An explicitly empty selection disables
all optional modules. Branches inherit their main company's selection.

The sidebar and web/API permission lists use the same catalog. Protected web and
API requests enforce the selection before company-admin permission bypasses.
Disabled requests return HTTP 403; JSON includes code MODULE_NOT_ENABLED.
Submitting permissions for a disabled module is also rejected. Disabling a module
does not delete data or revoke stored role permissions. Re-enabling it restores
access subject to existing permissions.

API login, OTP verification, /api/company/me and /api/company/profile company
payloads include allowed_modules. Mobile clients must use this list to hide their
menus, and refresh it on resume. The server rejects disabled API requests regardless
of the mobile UI. Essential dashboard, profile, security and logout remain available.

## Files to deploy

- config/company_modules.php (new)
- database/migrations/2026_10_22_120000_add_allowed_modules_to_companies.php (new)
- app/Services/CompanyModules.php (new)
- app/Models/Company.php
- app/Http/Middleware/EnsureCompanyIsActive.php
- app/Http/Middleware/CompanyRoutePermission.php
- app/Http/Controllers/SuperAdmin/CompanyController.php
- app/Http/Controllers/Company/CompanyRoleController.php
- app/Http/Controllers/Company/CompanyPermissionController.php
- app/Http/Controllers/Api/RoleApiController.php
- app/Http/Controllers/Api/PermissionApiController.php
- app/Http/Controllers/Api/AuthController.php
- resources/views/superadmin/auth/company/allowed_modules.blade.php (new)
- resources/views/superadmin/auth/company/create.blade.php
- resources/views/superadmin/auth/company/edit.blade.php
- resources/views/company_layout/sidebar.blade.php

Run the migration before accepting company create/edit requests:

```sh
php artisan migrate --path=database/migrations/2026_10_22_120000_add_allowed_modules_to_companies.php --force
php artisan config:clear
php artisan view:clear
```

Rebuild config cache afterward if your deployment normally caches configuration.
The catalog must be available before serving requests. No existing company data
needs to be backfilled. Test coverage: tests/Feature/CompanyModulesTest.php.
